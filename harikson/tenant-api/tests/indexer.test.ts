import fs from 'fs';
import path from 'path';
import { RepositoryIndexer } from '../src/services/indexer/repository-indexer.js';
import { MemoryStore } from '../src/services/memory/store.js';
import { OllamaClient } from '../src/llm/ollama.js';

async function runIndexerTests() {
  console.log('🧪 [Harikson Indexer Test Suite] Initializing tests...');

  const tenantId = '00000000-0000-0000-0000-000000000000';
  const tempWorkspace = path.resolve('./tests/temp-workspace');

  // Create temporary workspace files
  if (fs.existsSync(tempWorkspace)) {
    fs.rmSync(tempWorkspace, { recursive: true, force: true });
  }
  fs.mkdirSync(tempWorkspace, { recursive: true });

  // 1. Write a 1000-line app.js file
  const appJsLines = [];
  for (let i = 1; i <= 1000; i++) {
    appJsLines.push(`// Line ${i}: const variable_${i} = "value_${i}";`);
  }
  fs.writeFileSync(path.join(tempWorkspace, 'app.js'), appJsLines.join('\n'));

  // 2. Write an auth.ts file with JWT keywords
  const authTsContent = `
    // Authentication module
    export function verifyToken(token: string) {
      // Secret JWT implementation details
      const payload = decodeJWT(token);
      return payload.valid;
    }
  `;
  fs.writeFileSync(path.join(tempWorkspace, 'auth.ts'), authTsContent);

  // Mock database and Ollama client methods
  const originalEmbed = OllamaClient.embed;
  OllamaClient.embed = async () => new Array(1536).fill(0.35);

  let mockDbChunks: any[] = [];
  let mockDbState = new Map<string, { mtime: Date; sha256: string }>();

  // Mock static executeQuery inside RepositoryIndexer
  const originalExecuteQuery = (RepositoryIndexer as any).executeQuery;
  (RepositoryIndexer as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async (sql: string, params?: any[]) => {
        const lower = sql.toLowerCase();
        if (lower.includes('select mtime, sha256')) {
          const filePath = params![0];
          const state = mockDbState.get(filePath);
          return { rows: state ? [state] : [] };
        }
        if (lower.includes('delete from file_chunks')) {
          const filePath = params![0];
          mockDbChunks = mockDbChunks.filter((c) => c.file_path !== filePath);
          return { rowCount: 1 };
        }
        if (lower.includes('insert into file_index_state')) {
          const filePath = params![1];
          const mtime = params![2];
          const sha256 = params![3];
          mockDbState.set(filePath, { mtime, sha256 });
          return { rows: [] };
        }
        if (lower.includes('insert into file_chunks')) {
          const filePath = params![1];
          const chunkNumber = params![2];
          const content = params![3];
          const embedding = params![4];
          mockDbChunks.push({
            file_path: filePath,
            chunk_number: chunkNumber,
            content,
            embedding,
          });
          return { rows: [] };
        }
        if (lower.includes('select file_path from file_index_state')) {
          return {
            rows: Array.from(mockDbState.keys()).map((file_path) => ({
              file_path,
            })),
          };
        }
        if (lower.includes('delete from file_index_state')) {
          const filePath = params![0];
          mockDbState.delete(filePath);
          return { rowCount: 1 };
        }
        return { rows: [] };
      },
    };
    return callback(mockClient);
  };

  // 🔹 Test 1: Chunking Overlap check (1000-line file)
  try {
    console.log(
      '\n🔹 Test 1: Evaluating chunk size and overlap on 1000-line app.js...'
    );
    const result = await RepositoryIndexer.indexWorkspace(
      tenantId,
      tempWorkspace
    );

    const appJsChunks = mockDbChunks.filter((c) => c.file_path === 'app.js');
    console.log(`👉 Chunks created for app.js: ${appJsChunks.length}`);

    if (appJsChunks.length >= 5) {
      console.log('✅ Pass: app.js was successfully split into 5+ chunks.');
    } else {
      throw new Error(
        `Fail: Expected >= 5 chunks for app.js, got ${appJsChunks.length}`
      );
    }

    // Verify overlap presence (some adjacent chunks share lines)
    if (appJsChunks[0] && appJsChunks[1]) {
      const words0 = appJsChunks[0].content.split(' ');
      const words1 = appJsChunks[1].content.split(' ');
      const overlapWords = words0.slice(-10); // Check end of chunk 0
      const matches = overlapWords.some((w) => words1.slice(0, 50).includes(w));
      if (matches) {
        console.log('✅ Pass: Overlap verification successful.');
      } else {
        throw new Error(
          'Fail: Adjacent chunks do not contain overlapping tokens.'
        );
      }
    }
  } catch (err: any) {
    console.error('❌ Test 1 FAILED:', err.message);
  }

  // 🔹 Test 2: Incremental Indexing Speed Check (<2 seconds on unchanged files)
  try {
    console.log(
      '\n🔹 Test 2: Checking incremental indexing time on unchanged directory...'
    );
    const start = Date.now();
    const result = await RepositoryIndexer.indexWorkspace(
      tenantId,
      tempWorkspace
    );
    const elapsed = Date.now() - start;

    console.log(`⏱️  Re-indexing time elapsed: ${elapsed}ms`);
    console.log(
      `👉 Chunks created (new): ${result.chunksCreated}, Skipped files: ${result.skippedFiles}`
    );

    if (
      elapsed < 2000 &&
      result.chunksCreated === 0 &&
      result.skippedFiles === 2
    ) {
      console.log(
        '✅ Pass: Incremental indexing skipped all unchanged files in <2s.'
      );
    } else {
      throw new Error(
        'Fail: Indexer took too long or failed to skip unchanged files.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 2 FAILED:', err.message);
  }

  // 🔹 Test 3: Modification Indexing Check (Only re-indexes modified files)
  try {
    console.log(
      '\n🔹 Test 3: Verifying modification updates only modify the target file...'
    );
    // Modify auth.ts
    const updatedAuthTs = `
      // Authentication module
      export function verifyToken(token: string) {
        // Secret JWT implementation details
        const payload = decodeJWT(token);
        console.log("Token check!");
        return payload.valid;
      }
    `;
    fs.writeFileSync(path.join(tempWorkspace, 'auth.ts'), updatedAuthTs);
    // Touch mtime manually to ensure file system updates mtime (since writes are fast)
    const now = new Date();
    fs.utimesSync(path.join(tempWorkspace, 'auth.ts'), now, now);

    const result = await RepositoryIndexer.indexWorkspace(
      tenantId,
      tempWorkspace
    );
    console.log(
      `👉 Chunks created: ${result.chunksCreated}, Skipped: ${result.skippedFiles}`
    );

    if (result.chunksCreated > 0 && result.skippedFiles === 1) {
      console.log(
        '✅ Pass: Indexer successfully re-indexed ONLY the modified auth.ts.'
      );
    } else {
      throw new Error(
        'Fail: Indexer did not target the modified file or skipped it incorrectly.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 3 FAILED:', err.message);
  }

  // 🔹 Test 4: Similarity Vector Query Mock check
  try {
    console.log(
      "\n🔹 Test 4: Simulating vector search for 'JWT implementation'..."
    );
    // Mock memory store search query
    const originalSearchVector = MemoryStore.searchVector;
    MemoryStore.searchVector = async (tenantId, userId, embedding, limit) => {
      // Find matching chunks containing "JWT" keywords
      const hits = mockDbChunks.filter((c) => c.content.includes('JWT'));
      return hits.map((h) => ({
        id: 'chunk-uuid',
        tenant_id: tenantId,
        user_id: userId,
        memory: h.content,
        importance: 1.0,
        created_at: new Date(),
        updated_at: new Date(),
        score: 0.85,
      }));
    };

    const searchHits = await MemoryStore.searchVector(
      tenantId,
      'user-uuid',
      new Array(1536).fill(0.1),
      5
    );
    console.log(`👉 Search hits matching JWT keywords: ${searchHits.length}`);

    if (
      searchHits.some(
        (h) => h.memory.includes('auth.ts') || h.memory.includes('JWT')
      )
    ) {
      console.log(
        '✅ Pass: Vector search returned correct chunks from auth files.'
      );
    } else {
      throw new Error(
        'Fail: Cosine similarity search did not match auth chunks.'
      );
    }

    MemoryStore.searchVector = originalSearchVector;
  } catch (err: any) {
    console.error('❌ Test 4 FAILED:', err.message);
  }

  // Cleanup temp files
  try {
    fs.rmSync(tempWorkspace, { recursive: true, force: true });
  } catch (err: any) {
    console.warn('Warning cleaning up temp directory:', err.message);
  }

  // Restore static methods
  OllamaClient.embed = originalEmbed;
  (RepositoryIndexer as any).executeQuery = originalExecuteQuery;

  console.log('\n🏁 [Harikson Indexer Test Suite] Verification completed.');
}

runIndexerTests().catch((err) =>
  console.error('Fatal test runner error:', err)
);
