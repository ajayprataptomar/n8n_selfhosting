import pg from 'pg';
import { pool } from '../../db/pool.js';
import { OllamaClient } from '../../llm/ollama.js';

export interface SearchResult {
  type: 'code' | 'memory';
  content: string;
  source_path: string;
  similarity_score: number;
  chunk_number?: number;
}

export interface SearchFilters {
  file_types?: string[];
  memory_only?: boolean;
  code_only?: boolean;
  user_id?: string;
}

export class VectorSearchService {
  private static async executeQuery<T>(
    tenantId: string,
    callback: (client: pg.PoolClient) => Promise<T>
  ): Promise<T> {
    const client = await pool.connect();
    try {
      await client.query('SELECT set_tenant_context($1)', [tenantId]);
      const result = await callback(client);
      await client.query('SELECT set_tenant_context(NULL)');
      return result;
    } catch (err) {
      try {
        await client.query('SELECT set_tenant_context(NULL)');
      } catch (cleanupErr: any) {
        console.warn(
          'Warning clearing tenant context on query error in VectorSearchService:',
          cleanupErr.message
        );
      }
      throw err;
    } finally {
      client.release();
    }
  }

  static async search(
    query: string,
    tenantId: string,
    filters?: SearchFilters,
    topKCode = 20,
    topKMemory = 5
  ): Promise<{ results: SearchResult[]; queryEmbeddingTimeMs: number }> {
    const embedStart = Date.now();
    const queryEmbedding = await OllamaClient.embed(query);
    const queryEmbeddingTimeMs = Date.now() - embedStart;

    const vectorStr = `[${queryEmbedding.join(',')}]`;
    const results: SearchResult[] = [];

    const searchCode = !filters?.memory_only;
    const searchMemory = !filters?.code_only;

    // 1. Parallel queries
    const queries: Promise<void>[] = [];

    if (searchCode) {
      queries.push(
        this.executeQuery(tenantId, async (client) => {
          let fileTypeFilter = '';
          const params: any[] = [vectorStr, tenantId, topKCode];
          let paramIdx = 4;

          if (filters?.file_types && filters.file_types.length > 0) {
            const clauses = filters.file_types.map((ext) => {
              const cleanExt = ext.startsWith('.') ? ext : `.${ext}`;
              params.push(`%${cleanExt}`);
              const clause = `file_path LIKE $${paramIdx}`;
              paramIdx++;
              return clause;
            });
            fileTypeFilter = `AND (${clauses.join(' OR ')})`;
          }

          const sql = `
            SELECT file_path, chunk_number, content,
                   (1 - (embedding <=> $1::vector))::float as score
            FROM file_chunks
            WHERE tenant_id = $2 ${fileTypeFilter}
            ORDER BY embedding <=> $1::vector ASC
            LIMIT $3
          `;

          const res = await client.query(sql, params);
          res.rows.forEach((row) => {
            results.push({
              type: 'code',
              content: row.content,
              source_path: row.file_path,
              similarity_score: row.score,
              chunk_number: row.chunk_number,
            });
          });
        })
      );
    }

    if (searchMemory) {
      queries.push(
        this.executeQuery(tenantId, async (client) => {
          let userFilter = '';
          const params: any[] = [vectorStr, tenantId, topKMemory];

          if (filters?.user_id) {
            params.push(filters.user_id);
            userFilter = 'AND user_id = $4';
          }

          const sql = `
            SELECT memory as content, user_id, importance,
                   (1 - (embedding <=> $1::vector))::float as score
            FROM memories
            WHERE tenant_id = $2 ${userFilter}
            ORDER BY embedding <=> $1::vector ASC
            LIMIT $3
          `;

          const res = await client.query(sql, params);
          res.rows.forEach((row) => {
            results.push({
              type: 'memory',
              content: row.content,
              source_path: 'memories_table',
              similarity_score: row.score,
            });
          });
        })
      );
    }

    // Wait for DB fetches
    await Promise.all(queries);

    // 2. Apply Keyword Boosting
    const keywords = query
      .toLowerCase()
      .replace(/[^\w\s]/g, '')
      .split(/\s+/)
      .filter((word) => word.length >= 3);

    const scoredResults = results.map((res) => {
      let matchedCount = 0;
      if (keywords.length > 0) {
        const lowerContent = res.content.toLowerCase();
        keywords.forEach((kw) => {
          if (lowerContent.includes(kw)) {
            matchedCount++;
          }
        });
      }
      const keywordBoost =
        keywords.length > 0 ? matchedCount / keywords.length : 0.0;
      const finalScore = res.similarity_score * 0.7 + keywordBoost * 0.3;

      return {
        ...res,
        similarity_score: finalScore,
      };
    });

    // 3. Apply Deduplication / Chunks Merging
    const mergedResults = this.mergeAdjacentChunks(scoredResults);

    // Sort by final similarity score descending
    mergedResults.sort((a, b) => b.similarity_score - a.similarity_score);

    return {
      results: mergedResults,
      queryEmbeddingTimeMs,
    };
  }

  private static mergeAdjacentChunks(results: SearchResult[]): SearchResult[] {
    const codeResults = results.filter((r) => r.type === 'code');
    const memoryResults = results.filter((r) => r.type === 'memory');

    // Group code chunks by source_path
    const groups = new Map<string, SearchResult[]>();
    for (const r of codeResults) {
      if (!groups.has(r.source_path)) {
        groups.set(r.source_path, []);
      }
      groups.get(r.source_path)!.push(r);
    }

    const mergedCodeResults: SearchResult[] = [];

    for (const [sourcePath, chunks] of groups.entries()) {
      chunks.sort((a, b) => (a.chunk_number ?? 0) - (b.chunk_number ?? 0));

      let currentMerged: SearchResult | null = null;

      for (const chunk of chunks) {
        if (!currentMerged) {
          currentMerged = { ...chunk };
          continue;
        }

        const prevNum = currentMerged.chunk_number ?? 0;
        const currentNum = chunk.chunk_number ?? 0;

        if (currentNum === prevNum + 1) {
          const combinedText =
            currentMerged.content + '\n...\n' + chunk.content;
          const wordCount = combinedText.split(/\s+/).length;

          // Cap merged contents at ~2000 tokens (words)
          if (wordCount <= 2000) {
            currentMerged.content = combinedText;
            currentMerged.chunk_number = currentNum;
            currentMerged.similarity_score = Math.max(
              currentMerged.similarity_score,
              chunk.similarity_score
            );
          } else {
            mergedCodeResults.push(currentMerged);
            currentMerged = { ...chunk };
          }
        } else {
          mergedCodeResults.push(currentMerged);
          currentMerged = { ...chunk };
        }
      }

      if (currentMerged) {
        mergedCodeResults.push(currentMerged);
      }
    }

    return [...mergedCodeResults, ...memoryResults];
  }
}
