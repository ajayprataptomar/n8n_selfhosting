import fs from 'fs';
import path from 'path';
import { HariksonScheduler } from '../src/workers/scheduler.js';
import { RepositoryIndexer } from '../src/services/indexer/repository-indexer.js';
import { MemoryExtractor } from '../src/services/memory/extractor.js';
import { ContextBuilder } from '../src/services/context/context-builder.js';
import { OllamaClient } from '../src/llm/ollama.js';

async function runWorkerTests() {
  console.log(
    '🧪 [Harikson Background Workers Test Suite] Initializing tests...'
  );

  const tenantId = '00000000-0000-0000-0000-000000000000';
  const userId = '00000000-0000-0000-0000-000000000001';
  const workerWorkspace = path.resolve('./tests/worker-workspace');

  // Create temporary workspace files
  if (fs.existsSync(workerWorkspace)) {
    fs.rmSync(workerWorkspace, { recursive: true, force: true });
  }
  fs.mkdirSync(workerWorkspace, { recursive: true });

  // Mocks
  const originalIndexWorkspace = RepositoryIndexer.indexWorkspace;
  let indexerCallCount = 0;
  RepositoryIndexer.indexWorkspace = async () => {
    indexerCallCount++;
    return { chunksCreated: 1, skippedFiles: 0, totalFiles: 1 };
  };

  const originalExtractFacts = MemoryExtractor.extractAndSave;
  let memoryExtractCallCount = 0;
  MemoryExtractor.extractAndSave = async () => {
    memoryExtractCallCount++;
  };

  const originalContextBuild = ContextBuilder.build;
  let summarizerCallCount = 0;
  ContextBuilder.build = async () => {
    summarizerCallCount++;
    return {} as any;
  };

  const originalEmbed = OllamaClient.embed;
  let embeddingWarmCount = 0;
  OllamaClient.embed = async () => {
    embeddingWarmCount++;
    return new Array(1536).fill(0.1);
  };

  const originalExecuteQuery = (HariksonScheduler as any).executeQuery;
  let mockDbMessagesList: any[] = [];
  let mockDbConversationsList: any[] = [];
  let mockDbSummaries: any[] = [];

  (HariksonScheduler as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async (sql: string, params?: any[]) => {
        const lower = sql.toLowerCase();
        if (
          lower.includes('from messages') &&
          lower.includes("role = 'user'")
        ) {
          return { rows: mockDbMessagesList };
        }
        if (
          lower.includes('group by conversation_id') &&
          lower.includes('having count(*) > 50')
        ) {
          return { rows: mockDbConversationsList };
        }
        if (lower.includes('from conversation_summaries')) {
          return { rows: mockDbSummaries };
        }
        return { rows: [] };
      },
    };
    return callback(mockClient);
  };

  // 🔹 Test 1: Incremental Indexer Watcher trigger
  try {
    console.log(
      '\n🔹 Test 1: Testing file change watcher hot index trigger...'
    );

    // Start watcher
    (HariksonScheduler as any).startIndexerWorker(tenantId, workerWorkspace);

    // Create a file in workspace to trigger change
    fs.writeFileSync(path.join(workerWorkspace, 'test.ts'), 'const a = 1;');

    // Wait 4 seconds for throttle timer cooldown
    await new Promise((resolve) => setTimeout(resolve, 4000));

    console.log(`👉 Indexer execution triggers: ${indexerCallCount}`);

    if (indexerCallCount > 0) {
      console.log(
        '✅ Pass: Incremental file change correctly triggered hot re-indexing.'
      );
    } else {
      throw new Error(
        'Fail: File change watcher did not invoke RepositoryIndexer.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 1 FAILED:', err.message);
  }

  // 🔹 Test 2: MemoryExtractor poller
  try {
    console.log('\n🔹 Test 2: Testing MemoryExtractor polling task...');

    mockDbMessagesList = [
      {
        id: 'msg-101',
        content: 'Remember I love Laravel',
        conversation_id: 'conv-1',
      },
    ];

    await (HariksonScheduler as any).runMemoryExtractorWorker(tenantId, userId);
    console.log(`👉 Fact extraction polls: ${memoryExtractCallCount}`);

    if (memoryExtractCallCount === 1) {
      console.log(
        '✅ Pass: Memory Extractor polled and processed newly logged messages.'
      );
    } else {
      throw new Error(
        'Fail: Memory Extractor worker did not process polled user prompts.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 2 FAILED:', err.message);
  }

  // 🔹 Test 3: Conversation Summarizer poller (>50 messages)
  try {
    console.log(
      '\n🔹 Test 3: Testing Conversation Summarizer polling limits...'
    );

    mockDbConversationsList = [{ conversation_id: 'conv-long-1', count: 72 }];
    mockDbSummaries = []; // No summaries exist

    await (HariksonScheduler as any).runConversationSummarizerWorker(
      tenantId,
      userId
    );
    console.log(`👉 Context build summarizer triggers: ${summarizerCallCount}`);

    if (summarizerCallCount === 1) {
      console.log(
        '✅ Pass: Worker correctly triggered summarization on active thread with >50 messages.'
      );
    } else {
      throw new Error('Fail: Summarization poller did not run.');
    }
  } catch (err: any) {
    console.error('❌ Test 3 FAILED:', err.message);
  }

  // 🔹 Test 4: Cache Warmer traversal
  try {
    console.log('\n🔹 Test 4: Testing Cache Warmer workspace traversal...');

    // Write 2 valid extension files
    fs.writeFileSync(path.join(workerWorkspace, 'file1.ts'), 'const x = 1;');
    fs.writeFileSync(path.join(workerWorkspace, 'file2.js'), 'const y = 2;');

    await (HariksonScheduler as any).runCacheWarmerWorker(
      tenantId,
      workerWorkspace
    );
    console.log(`👉 Embed cache warm executions: ${embeddingWarmCount}`);

    if (embeddingWarmCount >= 2) {
      console.log(
        '✅ Pass: Cache Warmer precomputed vector embeddings for workspace files.'
      );
    } else {
      throw new Error('Fail: Cache Warmer skipped code extension paths.');
    }
  } catch (err: any) {
    console.error('❌ Test 4 FAILED:', err.message);
  }

  // Cleanup temp files
  try {
    HariksonScheduler.stopAll();
    fs.rmSync(workerWorkspace, { recursive: true, force: true });
  } catch (err: any) {
    console.warn('Warning cleaning up temp directory:', err.message);
  }

  // Restore mocks
  RepositoryIndexer.indexWorkspace = originalIndexWorkspace;
  MemoryExtractor.extractAndSave = originalExtractFacts;
  ContextBuilder.build = originalContextBuild;
  OllamaClient.embed = originalEmbed;
  (HariksonScheduler as any).executeQuery = originalExecuteQuery;

  console.log(
    '\n🏁 [Harikson Background Workers Test Suite] Verification completed.'
  );
}

runWorkerTests().catch((err) => console.error('Fatal worker tests run:', err));
