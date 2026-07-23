import { ContextBuilder } from '../src/services/context/context-builder.js';
import { MemoryRetriever } from '../src/services/memory/retriever.js';
import { VectorSearchService } from '../src/services/search/vector-search.js';
import { OllamaClient } from '../src/llm/ollama.js';

async function runContextBuilderTests() {
  console.log('🧪 [Harikson Context Builder Test Suite] Initializing tests...');

  const tenantId = '00000000-0000-0000-0000-000000000000';
  const userId = '00000000-0000-0000-0000-000000000001';
  const conversationId = '11111111-1111-1111-1111-111111111111';

  // Mock MemoryRetriever
  const originalRetrieve = MemoryRetriever.retrieve;
  MemoryRetriever.retrieve = async (tenantId, userId, message, limit) => {
    if (message.includes('Laravel')) {
      return [
        {
          id: 'mem-1',
          tenant_id: tenantId,
          user_id: userId,
          memory: "User's company uses Laravel preference.",
          importance: 0.9,
          created_at: new Date(),
          updated_at: new Date(),
        },
      ];
    }
    return [];
  };

  // Mock VectorSearchService
  const originalSearch = VectorSearchService.search;
  VectorSearchService.search = async (
    query,
    tenantId,
    filters,
    topKCode,
    topKMemory
  ) => {
    if (query.includes('Laravel')) {
      return {
        results: [
          {
            type: 'code',
            content: 'export class LaravelController extends Controller {}',
            source_path: 'app/LaravelController.php',
            similarity_score: 0.88,
          },
        ],
        queryEmbeddingTimeMs: 1,
      };
    }
    return { results: [], queryEmbeddingTimeMs: 1 };
  };

  // Mock Ollama Client
  const originalGenerate = OllamaClient.generate;
  const originalEmbed = OllamaClient.embed;
  OllamaClient.generate = async (prompt, systemPrompt) => {
    if (systemPrompt?.includes('Summarizer')) {
      return 'This is a summary of the older 980 messages discussing Laravel setups.';
    }
    return 'Ollama Response';
  };
  OllamaClient.embed = async () => new Array(1536).fill(0.1);

  // Mock database calls
  const originalExecuteQuery = (ContextBuilder as any).executeQuery;
  let mockDbSummaries: any[] = [];
  let dbMessagesList: any[] = [];

  (ContextBuilder as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async (sql: string, params?: any[]) => {
        const lower = sql.toLowerCase();
        if (lower.includes('from messages')) {
          return { rows: dbMessagesList };
        }
        if (lower.includes('insert into conversation_summaries')) {
          const summary = params![2];
          const range = params![3];
          mockDbSummaries.push({ summary, range });
          return { rows: [] };
        }
        if (lower.includes('from conversation_summaries')) {
          return {
            rows:
              mockDbSummaries.length > 0
                ? [mockDbSummaries[mockDbSummaries.length - 1]]
                : [],
          };
        }
        return { rows: [] };
      },
    };
    return callback(mockClient);
  };

  // 🔹 Test 1: Automatically summarize long conversation history (1000 messages)
  try {
    console.log('\n🔹 Test 1: Testing automatic history summarization...');
    // Simulate 1000 messages in DB
    dbMessagesList = [];
    for (let i = 1; i <= 1000; i++) {
      dbMessagesList.push({
        id: `msg-${i}`,
        role: i % 2 === 0 ? 'user' : 'assistant',
        content:
          `This is test message number ${i} containing text to inflate the token count. ` +
          'Adding extra verbose text to ensure we easily exceed the 60 percent trigger limit for conversation histories. '.repeat(
            3
          ),
      });
    }

    const { finalPrompt, contextSources } = await ContextBuilder.build(
      tenantId,
      userId,
      'What is my preferred stack?',
      conversationId,
      './'
    );

    console.log(
      `👉 History Summarized flag: ${contextSources.historySummarized}`
    );
    console.log(`👉 Total Summaries persisted: ${mockDbSummaries.length}`);

    if (contextSources.historySummarized && mockDbSummaries.length > 0) {
      console.log(
        '✅ Pass: Successfully triggered summarization on 1000 messages.'
      );
    } else {
      throw new Error('Fail: Indexer failed to summarize 1000 messages.');
    }
  } catch (err: any) {
    console.error('❌ Test 1 FAILED:', err.message);
  }

  // 🔹 Test 2: Laravel query fetches Laravel code chunks + Laravel user memory
  try {
    console.log('\n🔹 Test 2: Checking query mapping context matching...');
    dbMessagesList = [{ id: 'msg-1', role: 'user', content: 'Laravel query' }];

    const { finalPrompt, contextSources } = await ContextBuilder.build(
      tenantId,
      userId,
      'How do I setup Laravel?',
      conversationId,
      './'
    );

    console.log(`👉 Memories retrieved: ${contextSources.memories.length}`);
    console.log(`👉 Code files retrieved: ${contextSources.codeFiles.length}`);

    if (
      contextSources.memories.some((m) => m.includes('Laravel')) &&
      contextSources.codeFiles.some((f) => f.includes('Laravel'))
    ) {
      console.log(
        '✅ Pass: Retrieved Laravel-specific code chunks and memories successfully.'
      );
    } else {
      throw new Error(
        'Fail: Laravel context matching returned incomplete results.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 2 FAILED:', err.message);
  }

  // 🔹 Test 3: Context ordering verification
  try {
    console.log('\n🔹 Test 3: Checking section ordering alignment...');
    dbMessagesList = [];
    const { finalPrompt } = await ContextBuilder.build(
      tenantId,
      userId,
      'Create a Laravel controller',
      conversationId,
      './'
    );

    const systemIdx = finalPrompt.indexOf('System:');
    const developerIdx = finalPrompt.indexOf('Developer:');
    const memoriesIdx = finalPrompt.indexOf('[Relevant Memories]:');
    const codeIdx = finalPrompt.indexOf('[Relevant Code Chunks]:');
    const userIdx = finalPrompt.indexOf('User:');

    console.log(
      `👉 Order indices: System(${systemIdx}), Developer(${developerIdx}), Memories(${memoriesIdx}), Code(${codeIdx}), User(${userIdx})`
    );

    if (
      systemIdx < developerIdx &&
      developerIdx < memoriesIdx &&
      memoriesIdx < codeIdx &&
      codeIdx < userIdx
    ) {
      console.log(
        '✅ Pass: Output prompt is formatted in the correct sequence.'
      );
    } else {
      throw new Error('Fail: Output prompt sequence is incorrect.');
    }
  } catch (err: any) {
    console.error('❌ Test 3 FAILED:', err.message);
  }

  // Restore mocks
  MemoryRetriever.retrieve = originalRetrieve;
  VectorSearchService.search = originalSearch;
  OllamaClient.generate = originalGenerate;
  OllamaClient.embed = originalEmbed;
  (ContextBuilder as any).executeQuery = originalExecuteQuery;

  console.log(
    '\n🏁 [Harikson Context Builder Test Suite] Verification completed.'
  );
}

runContextBuilderTests().catch((err) =>
  console.error('Fatal context builder tests error:', err)
);
