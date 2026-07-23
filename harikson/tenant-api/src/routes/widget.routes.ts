import { Router } from 'express';
import { pool, executeCachedQuery } from '../db/pool.js';
import logger from '../utils/logger.js';

const router = Router();

// GET /api/widget/config - Fetch widget embed configuration for tenant domain
router.get('/config', async (req: any, res) => {
  const origin = req.headers.origin || req.headers.referer || '';
  const tenantKey = (req.query.key as string) || '';

  try {
    const tenantRes = await executeCachedQuery(
      'SELECT id, name, slug, widget_config FROM tenants WHERE id = $1 OR slug = $2 OR api_key = $3',
      [tenantKey, tenantKey, tenantKey],
      60
    );

    const tenant = tenantRes.rows?.[0];
    if (!tenant) {
      return res.status(404).json({ error: 'Tenant configuration not found for key' });
    }

    res.json({
      tenantId: tenant.id,
      name: tenant.name,
      slug: tenant.slug,
      config: tenant.widget_config || {
        title: 'Harikson AI Support',
        color: '#4f46e5',
        greeting: 'Hello! How can I assist you today?',
      },
    });
  } catch (err: any) {
    logger.error('Widget config error:', err);
    res.status(500).json({ error: 'Failed to fetch widget configuration' });
  }
});

// GET /api/widget/script.js - Deliver lightweight chat widget JS bundle
router.get('/script.js', (_req, res) => {
  const scriptContent = `
    (function() {
      console.log('🤖 [Harikson AI] Chat widget initialized.');
    })();
  `;
  res.setHeader('Content-Type', 'application/javascript');
  res.send(scriptContent);
});

export default router;
