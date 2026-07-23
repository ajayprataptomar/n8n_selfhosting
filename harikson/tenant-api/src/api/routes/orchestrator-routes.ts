import { Router, Request, Response } from 'express';
import { z } from 'zod';
import { HariksonOrchestrator } from '../../services/agents/orchestrator.js';
import { metrics } from '../../observability/metrics.js';

const router = Router();

const executeTaskSchema = z.object({
  userRequest: z.string().min(1),
  workspacePath: z.string().min(1),
  conversationId: z.string().uuid().optional(),
});

// POST /agents/execute - Triggers agent orchestrator subtasks
router.post('/execute', async (req: Request, res: Response) => {
  try {
    const check = executeTaskSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({
        hariksonError:
          "Invalid payload parameters. 'userRequest' and 'workspacePath' are required.",
      });
    }

    const { userRequest, workspacePath, conversationId } = check.data;
    const tenantId =
      (req.headers['x-tenant-id'] as string) ||
      '00000000-0000-0000-0000-000000000000';
    const activeConversationId =
      conversationId || '00000000-0000-0000-0000-000000000000'; // Fallback conversation

    const result = await HariksonOrchestrator.executeTask(
      tenantId,
      activeConversationId,
      workspacePath,
      userRequest
    );

    return res.status(200).json({
      planId: result.planId,
      finalOutput: result.finalOutput,
      steps: result.steps,
      hariksonPlanId: result.planId,
      hariksonFinalOutput: result.finalOutput,
      hariksonSteps: result.steps,
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

// GET /metrics - Exposes Prometheus metrics output
router.get('/metrics', (req: Request, res: Response) => {
  res.setHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
  return res.status(200).send(metrics.getPrometheusFormat());
});

export default router;
