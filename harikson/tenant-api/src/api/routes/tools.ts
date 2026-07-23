import { Router, Request, Response } from 'express';
import { z } from 'zod';
import { ToolExecutor } from '../../services/tools/executor.js';

const router = Router();

const executeToolsSchema = z.object({
  conversationId: z.string().uuid(),
  workspacePath: z.string().min(1),
  toolCalls: z.array(
    z.object({
      name: z.string(),
      params: z.any(),
    })
  ),
});

// POST /execute - Execute batch of tool calls sequentially
router.post('/execute', async (req: Request, res: Response) => {
  try {
    const check = executeToolsSchema.safeParse(req.body);
    if (!check.success) {
      return res
        .status(400)
        .json({ hariksonError: 'Invalid payload parameters.' });
    }

    const { conversationId, workspacePath, toolCalls } = check.data;
    const tenantId =
      (req.headers['x-tenant-id'] as string) ||
      '00000000-0000-0000-0000-000000000000';

    const results = await ToolExecutor.executeAll(
      tenantId,
      conversationId,
      workspacePath,
      toolCalls as any
    );

    return res.status(200).json({
      results,
      hariksonResults: results,
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

export default router;
