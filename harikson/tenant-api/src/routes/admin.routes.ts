import { Router } from 'express';
import { pool, getCacheHitMetrics } from '../db/pool.js';
import { rotateDocumentEncryptionKeys } from '../services/documentEncryptionService.js';
import logger from '../utils/logger.js';

const router = Router();

// POST /api/admin/rotate-keys - Document encryption key rotation
router.post('/rotate-keys', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  try {
    const count = await rotateDocumentEncryptionKeys(req.tenant.id);
    res.json({ success: true, rotatedCount: count });
  } catch (err: any) {
    logger.error('Document key rotation error:', err);
    res.status(500).json({ error: 'Failed to rotate document encryption keys' });
  }
});

// GET /api/admin/metrics - Redis & DB performance metrics
router.get('/metrics', (_req, res) => {
  const cacheMetrics = getCacheHitMetrics();
  res.json({
    cache: cacheMetrics,
    status: 'healthy',
    timestamp: new Date().toISOString(),
  });
});

export default router;
