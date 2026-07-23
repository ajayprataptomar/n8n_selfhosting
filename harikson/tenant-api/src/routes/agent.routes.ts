import { Router } from 'express';
import { executeTenantQuery } from '../db/pool.js';
import logger from '../utils/logger.js';

const router = Router();

// GET /api/agents
router.get('/', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  try {
    const agentsRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `SELECT id, name, model, system_prompt, status, total_requests, total_tokens, avg_response_time_ms, last_used_at, created_at
         FROM agents
         WHERE tenant_id = $1 AND status != 'deleted'
         ORDER BY created_at DESC`,
        [req.tenant.id]
      )
    );

    res.json({ agents: agentsRes.rows });
  } catch (err: any) {
    logger.error('Fetch agents error:', err);
    res.status(500).json({ error: 'Failed to fetch AI agents' });
  }
});

// POST /api/agents
router.post('/', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  const { name, model = 'qwen3-coder', systemPrompt = 'You are a helpful AI assistant.' } = req.body;

  try {
    const insertRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `INSERT INTO agents (tenant_id, name, model, system_prompt, status, created_at)
         VALUES ($1, $2, $3, $4, 'active', NOW())
         RETURNING id, name, model, system_prompt, status, created_at`,
        [req.tenant.id, name, model, systemPrompt]
      )
    );

    res.status(201).json({ agent: insertRes.rows[0] });
  } catch (err: any) {
    logger.error('Create agent error:', err);
    res.status(500).json({ error: 'Failed to create AI agent' });
  }
});

// GET /api/agents/:id
router.get('/:id', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;

  try {
    const agentRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `SELECT id, name, model, system_prompt, status, total_requests, total_tokens, avg_response_time_ms, last_used_at, created_at
         FROM agents
         WHERE id = $1 AND tenant_id = $2 AND status != 'deleted'`,
        [id, req.tenant.id]
      )
    );

    if (agentRes.rows.length === 0) {
      return res.status(404).json({ error: 'Agent not found' });
    }

    res.json({ agent: agentRes.rows[0] });
  } catch (err: any) {
    logger.error('Fetch agent details error:', err);
    res.status(500).json({ error: 'Failed to fetch agent details' });
  }
});

// PUT /api/agents/:id
router.put('/:id', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;
  const { name, model, systemPrompt, status } = req.body;

  try {
    const updateRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `UPDATE agents
         SET name = COALESCE($1, name),
             model = COALESCE($2, model),
             system_prompt = COALESCE($3, system_prompt),
             status = COALESCE($4, status),
             updated_at = NOW()
         WHERE id = $5 AND tenant_id = $6 AND status != 'deleted'
         RETURNING id, name, model, system_prompt, status, updated_at`,
        [name, model, systemPrompt, status, id, req.tenant.id]
      )
    );

    if (updateRes.rows.length === 0) {
      return res.status(404).json({ error: 'Agent not found or already deleted' });
    }

    res.json({ agent: updateRes.rows[0] });
  } catch (err: any) {
    logger.error('Update agent error:', err);
    res.status(500).json({ error: 'Failed to update AI agent' });
  }
});

// DELETE /api/agents/:id
router.delete('/:id', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;

  try {
    await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `UPDATE agents SET status = 'deleted', updated_at = NOW() WHERE id = $1 AND tenant_id = $2`,
        [id, req.tenant.id]
      )
    );

    res.json({ success: true, message: 'Agent deleted successfully' });
  } catch (err: any) {
    logger.error('Delete agent error:', err);
    res.status(500).json({ error: 'Failed to delete AI agent' });
  }
});

export default router;
