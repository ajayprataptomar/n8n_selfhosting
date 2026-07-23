import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import pg from 'pg';
import { pool } from '../../db/pool.js';
import { OllamaClient } from '../../llm/ollama.js';

export type ProgressCallback = (data: {
  phase: 'scanning' | 'indexing' | 'cleanup' | 'done';
  totalFiles: number;
  processedFiles: number;
  chunksCreated: number;
  skippedFiles: number;
  currentFile?: string;
}) => void;

export class IgnoreMatcher {
  private rules: RegExp[] = [];

  constructor(ignoreFilePath?: string) {
    let lines = [
      'node_modules',
      '.git',
      'build',
      'dist',
      '.env',
      '*.lock',
      'coverage',
      '.next',
      '.nuxt',
    ];

    if (ignoreFilePath && fs.existsSync(ignoreFilePath)) {
      try {
        const content = fs.readFileSync(ignoreFilePath, 'utf-8');
        lines = content
          .split('\n')
          .map((line) => line.trim())
          .filter((line) => line && !line.startsWith('#'));
      } catch (err) {
        console.warn('⚠️ Failed to read .hariksonignore, using defaults:', err);
      }
    }

    this.rules = lines.map((pattern) => {
      let regexStr = pattern
        .replace(/\./g, '\\.')
        .replace(/\*/g, '.*')
        .replace(/\?/g, '.');

      if (!pattern.startsWith('/')) {
        regexStr = '(^|/)' + regexStr;
      }
      if (pattern.endsWith('/')) {
        regexStr = regexStr + '.*';
      } else {
        regexStr = regexStr + '($|/)';
      }
      return new RegExp(regexStr);
    });
  }

  isIgnored(relativePath: string): boolean {
    const normalizedPath = relativePath.replace(/\\/g, '/');
    return this.rules.some((rule) => rule.test(normalizedPath));
  }
}

export class RepositoryIndexer {
  private static SUPPORTED_EXTENSIONS = new Set([
    '.js',
    '.ts',
    '.jsx',
    '.tsx',
    '.py',
    '.java',
    '.go',
    '.rs',
    '.php',
    '.rb',
    '.md',
    '.json',
    '.yaml',
    '.yml',
    '.sql',
    '.html',
    '.css',
    '.scss',
  ]);

  private static isBinary(filePath: string): boolean {
    const buffer = Buffer.alloc(1024);
    let fd;
    try {
      fd = fs.openSync(filePath, 'r');
      const bytesRead = fs.readSync(fd, buffer, 0, 1024, 0);
      for (let i = 0; i < bytesRead; i++) {
        if (buffer[i] === 0) {
          return true;
        }
      }
      return false;
    } catch {
      return true; // Skip if unable to read
    } finally {
      if (fd !== undefined) fs.closeSync(fd);
    }
  }

  private static chunkText(
    text: string,
    chunkSize = 500,
    overlap = 50
  ): string[] {
    const words = text.split(/\s+/).filter(Boolean);
    const chunks: string[] = [];

    let i = 0;
    while (i < words.length) {
      const chunkWords = words.slice(i, i + chunkSize);
      if (chunkWords.length > 0) {
        chunks.push(chunkWords.join(' '));
      }
      i += chunkSize - overlap;

      // Prevent infinite loops if params are misconfigured
      if (chunkSize <= overlap) break;
    }
    return chunks;
  }

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
          'Warning clearing tenant context on query error in RepositoryIndexer:',
          cleanupErr.message
        );
      }
      throw err;
    } finally {
      client.release();
    }
  }

  static async indexWorkspace(
    tenantId: string,
    workspacePath: string,
    onProgress?: ProgressCallback
  ): Promise<{
    chunksCreated: number;
    skippedFiles: number;
    totalFiles: number;
  }> {
    const absoluteRoot = path.resolve(workspacePath);
    if (!fs.existsSync(absoluteRoot)) {
      throw new Error(`Workspace path does not exist: ${absoluteRoot}`);
    }

    onProgress?.({
      phase: 'scanning',
      totalFiles: 0,
      processedFiles: 0,
      chunksCreated: 0,
      skippedFiles: 0,
    });

    const ignoreFilePath = path.join(absoluteRoot, '.hariksonignore');
    const matcher = new IgnoreMatcher(ignoreFilePath);
    const filesToIndex: string[] = [];

    // Helper: recursive file scanner
    const scanDir = (currentDir: string) => {
      const items = fs.readdirSync(currentDir);
      for (const item of items) {
        const fullPath = path.join(currentDir, item);
        const relativePath = path.relative(absoluteRoot, fullPath);

        if (matcher.isIgnored(relativePath)) {
          continue;
        }

        const stat = fs.statSync(fullPath);
        if (stat.isDirectory()) {
          scanDir(fullPath);
        } else if (stat.isFile()) {
          const ext = path.extname(item).toLowerCase();
          if (this.SUPPORTED_EXTENSIONS.has(ext) && !this.isBinary(fullPath)) {
            filesToIndex.push(fullPath);
          }
        }
      }
    };

    scanDir(absoluteRoot);
    const totalFiles = filesToIndex.length;
    let processedFiles = 0;
    let chunksCreated = 0;
    let skippedFiles = 0;

    onProgress?.({
      phase: 'indexing',
      totalFiles,
      processedFiles,
      chunksCreated,
      skippedFiles,
    });

    // Run indexing
    for (const filePath of filesToIndex) {
      const relativePath = path.relative(absoluteRoot, filePath);
      onProgress?.({
        phase: 'indexing',
        totalFiles,
        processedFiles,
        chunksCreated,
        skippedFiles,
        currentFile: relativePath,
      });

      try {
        const stat = fs.statSync(filePath);
        const mtime = stat.mtime;
        const content = fs.readFileSync(filePath, 'utf-8');
        const sha256 = crypto
          .createHash('sha256')
          .update(content, 'utf-8')
          .digest('hex');

        // Query existing state
        const state = await this.executeQuery(tenantId, async (client) => {
          const res = await client.query(
            'SELECT mtime, sha256 FROM file_index_state WHERE file_path = $1',
            [relativePath]
          );
          return res.rows[0];
        });

        if (state) {
          const dbMtime = new Date(state.mtime).getTime();
          const localMtime = mtime.getTime();

          // If mtime and sha256 match, skip re-indexing
          if (dbMtime === localMtime || state.sha256 === sha256) {
            processedFiles++;
            skippedFiles++;
            continue;
          }
        }

        // Delete old chunks and index state if mtime/hash differs
        await this.executeQuery(tenantId, async (client) => {
          await client.query('DELETE FROM file_chunks WHERE file_path = $1', [
            relativePath,
          ]);
          await client.query(
            `INSERT INTO file_index_state (tenant_id, file_path, mtime, sha256)
             VALUES ($1, $2, $3, $4)
             ON CONFLICT (tenant_id, file_path) DO UPDATE
             SET mtime = EXCLUDED.mtime, sha256 = EXCLUDED.sha256`,
            [tenantId, relativePath, mtime, sha256]
          );
        });

        // Split and embed
        const chunks = this.chunkText(content);
        let chunkNumber = 0;

        for (const chunkContent of chunks) {
          const embedding = await OllamaClient.embed(chunkContent);
          await this.executeQuery(tenantId, async (client) => {
            const vectorStr = `[${embedding.join(',')}]`;
            await client.query(
              `INSERT INTO file_chunks (tenant_id, file_path, chunk_number, content, embedding)
               VALUES ($1, $2, $3, $4, $5::vector)`,
              [tenantId, relativePath, chunkNumber, chunkContent, vectorStr]
            );
          });
          chunkNumber++;
          chunksCreated++;
        }

        processedFiles++;
      } catch (err) {
        console.error(
          `❌ [Harikson Indexer] Failed to index ${relativePath}:`,
          err
        );
        processedFiles++;
        skippedFiles++;
      }
    }

    // Cleanup: Delete DB records for files no longer present in workspace
    onProgress?.({
      phase: 'cleanup',
      totalFiles,
      processedFiles,
      chunksCreated,
      skippedFiles,
    });

    try {
      const relativeIndexedPaths = filesToIndex.map((p) =>
        path.relative(absoluteRoot, p)
      );

      await this.executeQuery(tenantId, async (client) => {
        // Fetch all indexed files in DB
        const dbFilesRes = await client.query(
          'SELECT file_path FROM file_index_state'
        );
        const dbFiles = dbFilesRes.rows.map((row) => row.file_path);
        const presentSet = new Set(relativeIndexedPaths);

        for (const dbFile of dbFiles) {
          if (!presentSet.has(dbFile)) {
            console.log(
              `🧹 [Harikson Indexer] Cleaning up removed file: ${dbFile}`
            );
            await client.query('DELETE FROM file_chunks WHERE file_path = $1', [
              dbFile,
            ]);
            await client.query(
              'DELETE FROM file_index_state WHERE file_path = $1',
              [dbFile]
            );
          }
        }
      });
    } catch (cleanupErr) {
      console.error(
        '⚠️ [Harikson Indexer] Database cleanup error:',
        cleanupErr
      );
    }

    onProgress?.({
      phase: 'done',
      totalFiles,
      processedFiles,
      chunksCreated,
      skippedFiles,
    });

    return { chunksCreated, skippedFiles, totalFiles };
  }
}
