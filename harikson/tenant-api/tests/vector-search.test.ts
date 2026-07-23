import {
  VectorSearchService,
  SearchResult,
} from '../src/services/search/vector-search.js';
import { OllamaClient } from '../src/llm/ollama.js';

async function runSearchTests() {
  console.log('🧪 [Harikson Vector Search Test Suite] Initializing tests...');

  const tenantId = '00000000-0000-0000-0000-000000000000';

  // Mock Ollama Client embedding
  const originalEmbed = OllamaClient.embed;
  OllamaClient.embed = async () => new Array(1536).fill(0.25);

  // Mock database search calls
  const originalExecuteQuery = (VectorSearchService as any).executeQuery;
  (VectorSearchService as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async (sql: string, params?: any[]) => {
        const lower = sql.toLowerCase();

        // Mock file_chunks lookup
        if (lower.includes('from file_chunks')) {
          return {
            rows: [
              {
                file_path: 'src/middleware/auth.ts',
                chunk_number: 1,
                content:
                  'export function jwtMiddleware() { // Secret JWT implementation details }',
                score: 0.82,
              },
              {
                file_path: 'src/db/migrations.ts',
                chunk_number: 0,
                content:
                  'export function createMigration() { // How do I create a migration? }',
                score: 0.78,
              },
              {
                file_path: 'src/db/migrations.ts',
                chunk_number: 1,
                content:
                  'export function runMigration() { // Running a DB migration }',
                score: 0.75,
              },
            ],
          };
        }

        // Mock memories lookup
        if (lower.includes('from memories')) {
          return {
            rows: [
              {
                content: "User's company uses Laravel and PHP stacks.",
                user_id: 'user-uuid-1',
                importance: 0.9,
                score: 0.88,
              },
            ],
          };
        }

        return { rows: [] };
      },
    };
    return callback(mockClient);
  };

  // 🔹 Test 1: Query JWT Implementation (returns auth code chunks)
  try {
    console.log("\n🔹 Test 1: Query 'Where is JWT implemented?'...");
    const { results } = await VectorSearchService.search(
      'Where is JWT implemented?',
      tenantId
    );

    console.log(`👉 Total search hits returned: ${results.length}`);
    const jwtHits = results.filter((r) => r.content.includes('JWT'));

    if (jwtHits.length > 0 && jwtHits[0].source_path.includes('auth.ts')) {
      console.log(
        `✅ Pass: Correctly returned auth middleware file chunk. Score: ${jwtHits[0].similarity_score}`
      );
    } else {
      throw new Error(
        'Fail: Failed to match auth middleware chunks for JWT query.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 1 FAILED:', err.message);
  }

  // 🔹 Test 2: Query Memory facts ("My company uses Laravel")
  try {
    console.log("\n🔹 Test 2: Query 'My company uses Laravel'...");
    const { results } = await VectorSearchService.search(
      'My company uses Laravel',
      tenantId
    );

    const memoryHits = results.filter((r) => r.type === 'memory');
    if (memoryHits.length > 0 && memoryHits[0].content.includes('Laravel')) {
      console.log(
        `✅ Pass: Stored memory hit successfully retrieved. Score: ${memoryHits[0].similarity_score}`
      );
    } else {
      throw new Error('Fail: Laravel preference memory was not matched.');
    }
  } catch (err: any) {
    console.error('❌ Test 2 FAILED:', err.message);
  }

  // 🔹 Test 3: Query hybrid unified items ("How do I create a migration?")
  try {
    console.log("\n🔹 Test 3: Query 'How do I create a migration?'...");
    const { results } = await VectorSearchService.search(
      'How do I create a migration?',
      tenantId
    );

    const codeHits = results.filter((r) => r.type === 'code');
    const memoryHits = results.filter((r) => r.type === 'memory');

    console.log(
      `👉 Returned code chunks: ${codeHits.length}, memory chunks: ${memoryHits.length}`
    );

    if (codeHits.length > 0 && memoryHits.length > 0) {
      console.log(
        '✅ Pass: Retrieved both code chunks (migrations) AND memory (Laravel stack choice) in unified results.'
      );
    } else {
      throw new Error(
        'Fail: Failed to return unified code and memory results.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 3 FAILED:', err.message);
  }

  // 🔹 Test 4: Performance Verification (<500ms latency)
  try {
    console.log('\n🔹 Test 4: Vector search performance latency audit...');
    const start = Date.now();
    await VectorSearchService.search(
      'Test performance latency constraints',
      tenantId
    );
    const duration = Date.now() - start;

    console.log(`⏱️  Search query executed in: ${duration}ms`);
    if (duration < 500) {
      console.log('✅ Pass: Search completed in under 500ms safety limit.');
    } else {
      throw new Error('Fail: Search latency exceeds 500ms threshold.');
    }
  } catch (err: any) {
    console.error('❌ Test 4 FAILED:', err.message);
  }

  // Restore mocks
  OllamaClient.embed = originalEmbed;
  (VectorSearchService as any).executeQuery = originalExecuteQuery;

  console.log(
    '\n🏁 [Harikson Vector Search Test Suite] Verification completed.'
  );
}

runSearchTests().catch((err) =>
  console.error('Fatal search tests error:', err)
);
