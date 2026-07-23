import { Router, Request, Response } from 'express';
import { MemoryStore } from '../../services/memory/store.js';

const router = Router();

// GET / - List all memories under active tenant/user scope
router.get('/', async (req: Request, res: Response) => {
  try {
    const tenantId = (req as any).tenantId || (req as any).tenant?.id || (req.headers['x-tenant-id'] as string);
    const userId = (req as any).userId || (req.headers['x-user-id'] as string);

    if (!tenantId || !userId) {
      return res.status(401).json({ hariksonError: 'Unauthorized: Missing tenant or user context' });
    }

    const memories = await MemoryStore.list(tenantId, userId);

    return res.status(200).json({
      hariksonMemories: memories.map((m) => ({
        hariksonMemoryId: m.id,
        hariksonMemoryText: m.memory,
        hariksonMemoryImportance: m.importance,
        hariksonCreatedAt: m.created_at,
        hariksonUpdatedAt: m.updated_at,
      })),
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

// DELETE /:id - Remove fact item from memory scope
router.delete('/:id', async (req: Request, res: Response) => {
  try {
    const { id } = req.params;
    const tenantId = (req as any).tenantId || (req as any).tenant?.id || (req.headers['x-tenant-id'] as string);
    const userId = (req as any).userId || (req.headers['x-user-id'] as string);

    if (!tenantId || !userId) {
      return res.status(401).json({ hariksonError: 'Unauthorized: Missing tenant or user context' });
    }

    const deleted = await MemoryStore.delete(tenantId, userId, id);
    if (!deleted) {
      return res.status(404).json({
        hariksonSuccess: false,
        hariksonMessage: 'Memory not found or access denied.',
      });
    }

    return res.status(200).json({
      hariksonSuccess: true,
      hariksonDeletedId: id,
      hariksonMessage:
        'Memory deleted successfully from Harikson memory store.',
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

export default router;
