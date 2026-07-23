import { Router, Request, Response } from 'express';
import { z } from 'zod';
import { ContextBuilder } from '../../services/context/context-builder.js';

const router = Router();

const buildContextSchema = z.object({
  conversationId: z.string().uuid().nullable().optional(),
  userPrompt: z.string().min(1),
  workspacePath: z.string().min(1),
  currentFile: z.string().optional(),
  cursorPosition: z.number().optional(),
});

// POST /build - Assembles context-aware prompt
router.post('/build', async (req: Request, res: Response) => {
  try {
    const check = buildContextSchema.safeParse(req.body);
    if (!check.success) {
      return res
        .status(400)
        .json({ hariksonError: 'Invalid payload parameters.' });
    }

    const {
      conversationId,
      userPrompt,
      workspacePath,
      currentFile,
      cursorPosition,
    } = check.data;
    const tenantId = (req as any).tenantId || (req as any).tenant?.id || (req.headers['x-tenant-id'] as string);
    const userId = (req as any).userId || (req.headers['x-user-id'] as string);

    if (!tenantId || !userId) {
      return res.status(401).json({ hariksonError: 'Unauthorized: Missing tenant or user context' });
    }

    const result = await ContextBuilder.build(
      tenantId,
      userId,
      userPrompt,
      conversationId || null,
      workspacePath,
      currentFile,
      cursorPosition
    );

    return res.status(200).json({
      finalPrompt: result.finalPrompt,
      tokenBreakdown: result.tokenBreakdown,
      contextSources: result.contextSources,
      hariksonFinalPrompt: result.finalPrompt,
      hariksonTokenBreakdown: result.tokenBreakdown,
      hariksonContextSources: result.contextSources,
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

export default router;
