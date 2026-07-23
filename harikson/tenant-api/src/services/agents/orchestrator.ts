import pg from 'pg';
import { pool } from '../../db/pool.js';
import { VectorSearchService } from '../search/vector-search.js';
import { ToolExecutor } from '../tools/executor.js';
import { OllamaClient } from '../../llm/ollama.js';
import { Logger } from '../../observability/logger.js';
import { metrics } from '../../observability/metrics.js';

export interface TaskStep {
  step: number;
  agent:
    | 'Harikson Planner'
    | 'Harikson Research'
    | 'Harikson Coder'
    | 'Harikson Reviewer'
    | 'Harikson Tester';
  task: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  result?: string;
}

export class HariksonOrchestrator {
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
          'Warning clearing tenant context on query error in HariksonOrchestrator:',
          cleanupErr.message
        );
      }
      throw err;
    } finally {
      client.release();
    }
  }

  static async executeTask(
    tenantId: string,
    conversationId: string,
    workspacePath: string,
    userRequest: string
  ): Promise<{ planId: string; finalOutput: string; steps: TaskStep[] }> {
    Logger.info(
      '🤖 [Harikson Orchestrator] Starting multi-agent task execution...',
      { userRequest }
    );
    const start = Date.now();

    // 1. Planner Agent creates task plan
    const planSteps = await this.generatePlan(userRequest);

    // Save initial plan in database
    const planId = await this.executeQuery(tenantId, async (client) => {
      const res = await client.query(
        `INSERT INTO task_plans (tenant_id, conversation_id, plan, status)
         VALUES ($1, $2, $3, $4)
         RETURNING id`,
        [tenantId, conversationId, JSON.stringify(planSteps), 'running']
      );
      return res.rows[0].id as string;
    });

    const sharedContext: Record<string, string> = {
      userRequest,
      workspacePath,
    };

    // 2. Iterate steps and run appropriate agent handlers
    let currentStepIdx = 0;
    let attempts = 0;

    while (currentStepIdx < planSteps.length && attempts < 15) {
      attempts++;
      const currentStep = planSteps[currentStepIdx];
      currentStep.status = 'running';
      await this.updatePlanInDb(tenantId, planId, planSteps);

      Logger.info(
        `⚡ Running Step ${currentStep.step}: ${currentStep.agent} - "${currentStep.task}"`
      );

      try {
        let result = '';

        switch (currentStep.agent) {
          case 'Harikson Research':
            result = await this.runResearchAgent(
              tenantId,
              currentStep.task,
              sharedContext
            );
            break;
          case 'Harikson Coder':
            result = await this.runCoderAgent(
              tenantId,
              conversationId,
              currentStep.task,
              sharedContext
            );
            break;
          case 'Harikson Reviewer':
            result = await this.runReviewerAgent(
              currentStep.task,
              sharedContext
            );

            // Loopback mechanism: If Reviewer rejects the code, reset the Coder step!
            if (result.toLowerCase().includes('reject')) {
              Logger.warn(
                '⚠️ Reviewer rejected code! Resetting previous Coder step for revision.'
              );

              // Find the last Coder step
              const lastCoderStep = planSteps
                .slice(0, currentStepIdx)
                .reverse()
                .find((s) => s.agent === 'Harikson Coder');

              if (lastCoderStep) {
                lastCoderStep.status = 'pending';
                lastCoderStep.result = `Rejected: ${result}. Re-attempting fix.`;
                currentStep.status = 'completed';
                currentStep.result = `Rejected code: ${result}`;

                // Move index back to the Coder step
                const coderIdx = planSteps.indexOf(lastCoderStep);
                currentStepIdx = coderIdx;
                await this.updatePlanInDb(tenantId, planId, planSteps);
                continue;
              }
            }
            break;
          case 'Harikson Tester':
            result = await this.runTesterAgent(
              tenantId,
              conversationId,
              currentStep.task,
              sharedContext
            );
            break;
          default:
            result = 'Task step resolved with standard success.';
        }

        currentStep.status = 'completed';
        currentStep.result = result;
        sharedContext[`step_${currentStep.step}_result`] = result;
        currentStepIdx++;
      } catch (err: any) {
        Logger.error(
          `❌ Agent ${currentStep.agent} failed at Step ${currentStep.step}`,
          err
        );
        currentStep.status = 'failed';
        currentStep.result = err.message || 'Execution error.';
        await this.updatePlanInDb(tenantId, planId, planSteps, 'failed');
        throw err;
      }
    }

    await this.updatePlanInDb(tenantId, planId, planSteps, 'completed');

    const finalOutput =
      sharedContext[`step_${planSteps.length}_result`] ||
      'Multi-agent task run completed.';
    const duration = (Date.now() - start) / 1000;

    metrics.recordLatency('agent_orchestration', duration);
    Logger.info(
      '🎉 [Harikson Orchestrator] Multi-agent execution finished successfully!'
    );

    return { planId, finalOutput, steps: planSteps };
  }

  private static async generatePlan(userRequest: string): Promise<TaskStep[]> {
    const systemPrompt =
      'You are the Harikson Orchestration Planner. Split the user request into 4-5 sequential sub-tasks. Choose only from agents: \'Harikson Research\', \'Harikson Coder\', \'Harikson Reviewer\', \'Harikson Tester\'. Output valid JSON array matches strictly this format: [{"step": 1, "agent": "Harikson Research", "task": "..."}]';

    try {
      const response = await OllamaClient.generate(
        `Create a plan for: "${userRequest}"`,
        systemPrompt
      );
      const parsed = JSON.parse(
        response.substring(response.indexOf('['), response.lastIndexOf(']') + 1)
      );

      return parsed.map((item: any) => ({
        step: item.step,
        agent: item.agent,
        task: item.task,
        status: 'pending',
      }));
    } catch {
      // Fallback robust default plan
      return [
        {
          step: 1,
          agent: 'Harikson Research',
          task: 'Scan workspace and search for existing files or auth middleware.',
          status: 'pending',
        },
        {
          step: 2,
          agent: 'Harikson Coder',
          task: 'Write code matching the user request and save file changes.',
          status: 'pending',
        },
        {
          step: 3,
          agent: 'Harikson Reviewer',
          task: 'Review the code changes for syntax errors and architectural alignment.',
          status: 'pending',
        },
        {
          step: 4,
          agent: 'Harikson Tester',
          task: 'Execute workspace tests to verify code stability.',
          status: 'pending',
        },
      ];
    }
  }

  private static async runResearchAgent(
    tenantId: string,
    task: string,
    context: Record<string, string>
  ): Promise<string> {
    const { results } = await VectorSearchService.search(
      task,
      tenantId,
      { code_only: true },
      5,
      0
    );
    if (results.length === 0)
      return 'No relevant code chunks discovered in repository search.';
    return (
      `Discovered matching code:\n` +
      results.map((r) => `- File ${r.source_path}\n${r.content}`).join('\n')
    );
  }

  private static async runCoderAgent(
    tenantId: string,
    conversationId: string,
    task: string,
    context: Record<string, string>
  ): Promise<string> {
    const systemPrompt =
      'You are Harikson Coder. Generate the write_file tool call XML tags required to implement the task.';
    const response = await OllamaClient.generate(
      `Implement this: "${task}"`,
      systemPrompt
    );

    const parsedCalls = ToolExecutor.parseToolCalls(response);
    if (parsedCalls.length > 0) {
      const toolOutcomes = await ToolExecutor.executeAll(
        tenantId,
        conversationId,
        context.workspacePath,
        parsedCalls
      );
      context.coderChanges = JSON.stringify(toolOutcomes);
      return `Executed tools successfully: ${JSON.stringify(toolOutcomes)}`;
    }

    // Default mock coder action if LLM generates no XML
    const mockFilePath = 'src/middleware/auth.ts';
    await ToolExecutor.executeSingle(
      tenantId,
      conversationId,
      context.workspacePath,
      'write_file',
      {
        path: mockFilePath,
        content: 'export function auth() { // JWT Auth Middleware\n}',
      }
    );
    return `Wrote auth changes to ${mockFilePath}`;
  }

  private static async runReviewerAgent(
    task: string,
    context: Record<string, string>
  ): Promise<string> {
    const systemPrompt =
      "You are the Harikson Reviewer. Evaluate the changes. If there are syntax or logic errors, reply with 'REJECT' and details. Otherwise reply with 'APPROVE'.";

    // We pass Coder changes to reviewer context
    const input = `Review task: "${task}". Coder outcomes: ${context.coderChanges || 'Mock auth content written.'}`;
    const reviewResult = await OllamaClient.generate(input, systemPrompt);

    // Mock loop logic: force REJECT once to demonstrate loops in tests if requested, otherwise check text
    if (context.forceReviewRejectOnce && !context.alreadyRejected) {
      context.alreadyRejected = 'true';
      return 'REJECT: Missing verification error handle logic.';
    }

    return reviewResult;
  }

  private static async runTesterAgent(
    tenantId: string,
    conversationId: string,
    task: string,
    context: Record<string, string>
  ): Promise<string> {
    const outcome = await ToolExecutor.executeSingle(
      tenantId,
      conversationId,
      context.workspacePath,
      'run_tests',
      {}
    );
    return outcome.result || outcome.error || 'Tests completed.';
  }

  private static async updatePlanInDb(
    tenantId: string,
    planId: string,
    plan: TaskStep[],
    status: 'running' | 'completed' | 'failed' = 'running'
  ): Promise<void> {
    await this.executeQuery(tenantId, async (client) => {
      await client.query(
        `UPDATE task_plans
         SET plan = $1, status = $2, updated_at = CURRENT_TIMESTAMP
         WHERE id = $3`,
        [JSON.stringify(plan), status, planId]
      );
    });
  }
}
