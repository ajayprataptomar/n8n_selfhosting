import pg from 'pg';
import { pool } from '../db/pool.js';
import { OllamaClient } from '../llm/ollama.js';
import pdf from 'pdf-parse';
import crypto from 'crypto';
import pLimit from 'p-limit';
import { encryptDocumentContent } from './documentEncryptionService.js';

export class RagService {
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
          'Warning clearing tenant context on query error in RagService:',
          cleanupErr.message
        );
      }
      throw err;
    } finally {
      client.release();
    }
  }

  /**
   * Parallelized batch embedding generation with p-limit concurrency (max 5),
   * 30-second chunk timeout, single retry after 2s, chunk skipping on failure, and progress tracking.
   */
  private static async generateBatchEmbeddings(
    chunks: string[],
    concurrency = 5
  ): Promise<Array<{ chunk: string; embedding: number[] }>> {
    const validChunks = chunks.filter((c) => c && c.trim().length > 0);
    const total = validChunks.length;
    if (total === 0) return [];

    const limit = pLimit(concurrency);
    let completedCount = 0;

    const tasks = validChunks.map((chunk, index) =>
      limit(async () => {
        const timeoutMs = 30000;

        const embedSingleChunk = async (): Promise<number[]> => {
          let timer: NodeJS.Timeout | null = null;
          const timeoutPromise = new Promise<number[]>((_, reject) => {
            timer = setTimeout(() => reject(new Error(`Embedding timed out after 30s`)), timeoutMs);
          });

          try {
            const res = await Promise.race([OllamaClient.embed(chunk), timeoutPromise]);
            if (timer) clearTimeout(timer);
            return res;
          } catch (err) {
            if (timer) clearTimeout(timer);
            throw err;
          }
        };

        let embedding: number[] | null = null;
        try {
          embedding = await embedSingleChunk();
        } catch (firstErr: any) {
          console.warn(
            `⚠️ [Harikson RAG] Embed attempt 1 failed for chunk ${index + 1}/${total}: ${firstErr.message}. Retrying in 2s...`
          );
          await new Promise((res) => setTimeout(res, 2000));
          try {
            embedding = await embedSingleChunk();
          } catch (retryErr: any) {
            console.error(
              `❌ [Harikson RAG] Skipping chunk ${index + 1}/${total} after retry failure:`,
              retryErr.message
            );
            return null;
          }
        }

        completedCount++;
        const percent = Math.round((completedCount / total) * 100);
        console.log(`⏳ [Harikson RAG] Embedding chunk ${completedCount}/${total} (${percent}%)`);

        return { chunk, embedding };
      })
    );

    const results = await Promise.all(tasks);
    return results.filter((item): item is { chunk: string; embedding: number[] } => item !== null);
  }

  // Parse uploaded file buffers based on file type
  static async indexFile(
    tenantId: string,
    userId: string,
    name: string,
    buffer: Buffer,
    type: string
  ): Promise<number> {
    let text = '';

    try {
      if (type.toLowerCase() === 'pdf') {
        const parsed = await pdf(buffer);
        text = parsed.text;
      } else {
        // Fallback to text parsing (Markdown, plain text, txt, json)
        text = buffer.toString('utf-8');
      }

      if (!text.trim()) {
        throw new Error('Extracted document content is empty');
      }

      // Chunk the text
      const chunks = this.chunkText(text, 800, 150);

      // Save document to knowledge_documents first
      const newDocId = crypto.randomUUID();
      const fileType = type || 'txt';

      // Encrypt content at rest using AES-256-GCM
      const { encryptedContent, iv, authTag, keyId } = encryptDocumentContent(newDocId, text);

      await this.executeQuery(tenantId, async (client) => {
        await client.query(
          `INSERT INTO knowledge_documents (
            id, tenant_id, user_id, filename, file_type, file_size_bytes, 
            content, content_iv, content_tag, key_id, is_active, status
           )
           VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, 'indexed')`,
          [
            newDocId,
            tenantId,
            userId,
            name,
            fileType,
            buffer.length || 0,
            encryptedContent,
            iv,
            authTag,
            keyId,
            true,
          ]
        );
      });

      // Generate embeddings in parallel (max 5 concurrent calls)
      const chunkEmbeddings = await this.generateBatchEmbeddings(chunks, 5);

      // Save embeddings inside DB connection
      await this.executeQuery(tenantId, async (client) => {
        for (const item of chunkEmbeddings) {
          const embeddingString = `[${item.embedding.join(',')}]`;
          await client.query(
            `INSERT INTO document_embeddings (tenant_id, knowledge_document_id, content, embedding)
             VALUES ($1, $2, $3, $4::vector)`,
            [tenantId, newDocId, item.chunk, embeddingString]
          );
        }
      });

      console.log(
        `📂 [Harikson RAG] Indexed document ${name}: created ${chunkEmbeddings.length}/${chunks.length} chunks.`
      );
      return chunkEmbeddings.length;
    } catch (error) {
      console.error(
        `❌ [Harikson RAG] Ingestion error on document ${name}:`,
        error
      );
      throw error;
    }
  }

  // Crawl and index URL content
  static async indexUrl(
    tenantId: string,
    userId: string,
    url: string
  ): Promise<number> {
    try {
      // Mock crawler fetching content from remote webpage
      const text = `Neuravolt AI agent documentation for ${url}. This page details configuration, setup, widget integration, billing subscriptions, support guidelines, and deployment metrics. The platform executes on isolated VPS nodes using Qwen3-Coder models.`;

      const chunks = this.chunkText(text, 800, 150);
      const newDocId = crypto.randomUUID();

      const { encryptedContent, iv, authTag, keyId } = encryptDocumentContent(newDocId, text);

      await this.executeQuery(tenantId, async (client) => {
        await client.query(
          `INSERT INTO knowledge_documents (
            id, tenant_id, user_id, filename, file_type, file_size_bytes, 
            content, content_iv, content_tag, key_id, is_active, status
           )
           VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, 'indexed')`,
          [newDocId, tenantId, userId, url, 'url', text.length || 0, encryptedContent, iv, authTag, keyId, true]
        );
      });

      // Generate embeddings in parallel (max 5 concurrent calls)
      const chunkEmbeddings = await this.generateBatchEmbeddings(chunks, 5);

      await this.executeQuery(tenantId, async (client) => {
        for (const item of chunkEmbeddings) {
          const embeddingString = `[${item.embedding.join(',')}]`;
          await client.query(
            `INSERT INTO document_embeddings (tenant_id, knowledge_document_id, content, embedding)
             VALUES ($1, $2, $3, $4::vector)`,
            [tenantId, newDocId, item.chunk, embeddingString]
          );
        }
      });

      console.log(
        `📂 [Harikson RAG] Indexed URL ${url}: created ${chunkEmbeddings.length}/${chunks.length} chunks.`
      );
      return chunkEmbeddings.length;
    } catch (error) {
      console.error(`❌ [Harikson RAG] Crawl error on ${url}:`, error);
      throw error;
    }
  }

  // Hybrid (Semantic Vector + Full-Text BM25) search to find relevant context
  static async queryContext(
    tenantId: string,
    query: string,
    maxResults = 3
  ): Promise<string> {
    try {
      const queryEmbedding = await OllamaClient.embed(query);
      const embeddingString = `[${queryEmbedding.join(',')}]`;

      const ragRows = await this.executeQuery(tenantId, async (client) => {
        const res = await client.query(
          `SELECT de.content, kd.filename,
                  (1 - (de.embedding <=> $1::vector)) AS vector_score,
                  COALESCE(ts_rank(kd.tsv, plainto_tsquery('english', $2)), 0) AS text_score,
                  (0.7 * (1 - (de.embedding <=> $1::vector)) + 0.3 * COALESCE(ts_rank(kd.tsv, plainto_tsquery('english', $2)), 0)) AS final_score
           FROM document_embeddings de
           JOIN knowledge_documents kd ON de.knowledge_document_id = kd.id
           WHERE de.tenant_id = $3 AND kd.is_active = true
           ORDER BY (0.7 * (1 - (de.embedding <=> $1::vector)) + 0.3 * COALESCE(ts_rank(kd.tsv, plainto_tsquery('english', $2)), 0)) DESC
           LIMIT $4`,
          [embeddingString, query, tenantId, maxResults]
        );
        return res.rows as Array<{
          content: string;
          vector_score: number;
          text_score: number;
          final_score: number;
          filename: string;
        }>;
      });

      const matched = ragRows
        .filter((row) => (row.final_score || row.vector_score) > 0.25)
        .map((row) => `[Source: ${row.filename}]: ${row.content}`);

      if (matched.length === 0) {
        return 'No matching context found in knowledge base.';
      }

      return matched.join('\n\n');
    } catch (error: any) {
      console.warn(
        '⚠️ [Harikson RAG] Query failed, returning empty context:',
        error.message
      );
      return 'No matching context found in knowledge base.';
    }
  }

  // Split text into sliding chunks
  private static chunkText(
    text: string,
    size: number,
    overlap: number
  ): string[] {
    const words = text.split(/\s+/);
    const chunks: string[] = [];

    let i = 0;
    while (i < words.length) {
      const chunkWords = words.slice(i, i + size);
      if (chunkWords.length > 0) {
        chunks.push(chunkWords.join(' '));
      }
      i += size - overlap;
    }

    return chunks;
  }
}
