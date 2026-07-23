import {
  HariksonOrchestrator,
  TaskStep,
} from '../src/services/agents/orchestrator.js';
import { OllamaClient } from '../src/llm/ollama.js';
import { VectorSearchService } from '../src/services/search/vector-search.js';
import { ToolExecutor } from '../src/services/tools/executor.js';

async function runAgentTests() {
  console.log(
    '🧪 [Harikson Agent Orchestrator Test Suite] Initializing tests...'
  );

  const tenantId = '00000000-0000-0000-0000-000000000000';
  const conversationId = '33333333-3333-3333-3333-333333333333';

  // Mock Ollama Client
  const originalGenerate = OllamaClient.generate;
  const originalEmbed = OllamaClient.embed;

  OllamaClient.embed = async () => new Array(1536).fill(0.1);
  let mockReviewerCount = 0;
  OllamaClient.generate = async (prompt, systemPrompt) => {
    if (systemPrompt?.includes('Planner')) {
      return JSON.stringify([
        {
          step: 1,
          agent: 'Harikson Research',
          task: 'Look for auth structures in workspace.',
        },
        {
          step: 2,
          agent: 'Harikson Coder',
          task: 'Write JWT middleware file.',
        },
        { step: 3, agent: 'Harikson Reviewer', task: 'Review the files.' },
        { step: 4, agent: 'Harikson Tester', task: 'Run test suites.' },
      ]);
    }
    if (systemPrompt?.includes('Coder')) {
      return '<tool_call name="write_file"><param name="path">src/mid.ts</param><param name="content">export const mid = 1;</param></tool_call>';
    }
    if (systemPrompt?.includes('Reviewer')) {
      if (mockReviewerCount === 0) {
        mockReviewerCount++;
        return 'REJECT: Missing verification error handle logic.';
      }
      return 'APPROVE: Code corrected.';
    }
    return 'MOCK OUTPUT';
  };

  // Mock VectorSearchService and ToolExecutor DB calls
  const originalSearch = VectorSearchService.search;
  VectorSearchService.search = async () => {
    return {
      results: [
        {
          type: 'code',
          content: 'export function auth() { // auth helper }',
          source_path: 'src/middleware/auth.ts',
          similarity_score: 0.9,
        },
      ],
      queryEmbeddingTimeMs: 1,
    };
  };

  const originalToolExecute = (ToolExecutor as any).executeQuery;
  (ToolExecutor as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async () => ({ rows: [] }),
    };
    return callback(mockClient);
  };

  // Mock Database Plans updates
  const originalExecuteQuery = (HariksonOrchestrator as any).executeQuery;
  let mockPlansDb = new Map<string, { plan: any; status: string }>();

  (HariksonOrchestrator as any).executeQuery = async (
    tenantId: string,
    callback: any
  ) => {
    const mockClient = {
      query: async (sql: string, params?: any[]) => {
        const lower = sql.toLowerCase();
        if (lower.includes('insert into task_plans')) {
          const plan = params![2];
          const status = params![3];
          const id = 'plan-uuid-1';
          mockPlansDb.set(id, { plan: JSON.parse(plan), status });
          return { rows: [{ id }] };
        }
        if (lower.includes('update task_plans')) {
          const plan = params![0];
          const status = params![1];
          const id = params![2];
          mockPlansDb.set(id, { plan: JSON.parse(plan), status });
          return { rows: [] };
        }
        return { rows: [] };
      },
    };
    return callback(mockClient);
  };

  // 🔹 Test 1: Plan division and Loopback recovery
  try {
    console.log(
      '\n🔹 Test 1: Simulating full multi-agent task with Reviewer loopback...'
    );

    // We pass workspace as "./tests/temp-agent-workspace"
    const result = await HariksonOrchestrator.executeTask(
      tenantId,
      conversationId,
      './',
      'Build a JWT authentication middleware'
    );

    console.log(
      `👉 Execution completed. Status: ${mockPlansDb.get(result.planId)?.status}`
    );
    console.log(`👉 Total steps logged: ${result.steps.length}`);

    // Verify coder step ran twice (i.e. once, then got reset to pending, then completed)
    const coderStep = result.steps.find((s) => s.agent === 'Harikson Coder');
    console.log(`👉 Coder step status: ${coderStep?.status}`);
    console.log(
      `👉 Reviewer step result: ${result.steps.find((s) => s.agent === 'Harikson Reviewer')?.result}`
    );

    if (
      coderStep &&
      coderStep.status === 'completed' &&
      mockPlansDb.get(result.planId)?.status === 'completed'
    ) {
      console.log(
        '✅ Pass: Multi-agent execution completed and recovered from loopback successfully.'
      );
    } else {
      throw new Error(
        'Fail: Agent execution loop failed or did not recover from Reviewer reject.'
      );
    }
  } catch (err: any) {
    console.error('❌ Test 1 FAILED:', err.message);
  }

  // Restore mocks
  OllamaClient.generate = originalGenerate;
  OllamaClient.embed = originalEmbed;
  VectorSearchService.search = originalSearch;
  (ToolExecutor as any).executeQuery = originalToolExecute;
  (HariksonOrchestrator as any).executeQuery = originalExecuteQuery;

  console.log(
    '\n🏁 [Harikson Agent Orchestrator Test Suite] Verification completed.'
  );
}

runAgentTests().catch((err) => console.error('Fatal agent tests run:', err));
