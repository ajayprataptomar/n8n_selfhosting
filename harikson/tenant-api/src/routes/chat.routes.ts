import { Router } from 'express';
import axios from 'axios';
import { Redis } from 'ioredis';
import { pool, executeTenantQuery } from '../db/pool.js';
import { RagService } from '../services/rag.service.js';
import { countExactTokens } from '../services/tokenCountingService.js';
import logger from '../utils/logger.js';

const router = Router();
const redis = new Redis(process.env.REDIS_URL || 'redis://redis:6379', {
  retryStrategy: (times) => Math.min(times * 50, 2000),
  maxRetriesPerRequest: 3,
  enableReadyCheck: true,
});

// Helper: Context-aware Mock LLM response fallback
function getMockResponse(history: any[], lastUserMsg: string, model: string): string {
  const msgLower = lastUserMsg.toLowerCase();
  if (msgLower.includes('hello') || msgLower.includes('hi') || msgLower.includes('hey')) {
    return `Hello! How can I assist you with your project using ${model} today?`;
  }
  if (msgLower.includes('pricing') || msgLower.includes('plan') || msgLower.includes('cost')) {
    return `Neuravolt offers Starter, Growth, and Enterprise tier subscriptions tailored to your active user and token volume.`;
  }
  if (msgLower.includes('help') || msgLower.includes('support')) {
    return `I can help you configure AI agents, inspect knowledge documents, manage billing invoices, and test RAG pipeline embeddings.`;
  }
  return `Thank you for your message. As an AI assistant powered by ${model}, I am processing your request: "${lastUserMsg}".`;
}

// GET /api/chat/conversations
router.get('/conversations', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const userId = req.user?.userId;

  try {
    const convRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `SELECT c.id, c.title, c.agent_id, c.created_at, c.updated_at,
                COUNT(m.id)::int as message_count
         FROM conversations c
         LEFT JOIN messages m ON m.conversation_id = c.id
         WHERE c.tenant_id = $1 ${userId ? 'AND c.user_id = $2' : ''}
         GROUP BY c.id
         ORDER BY c.updated_at DESC`,
        userId ? [req.tenant.id, userId] : [req.tenant.id]
      )
    );

    res.json({ conversations: convRes.rows });
  } catch (err: any) {
    logger.error('Fetch conversations error:', err);
    res.status(500).json({ error: 'Failed to fetch conversations' });
  }
});

// GET /api/chat/conversations/:id/messages
router.get('/conversations/:id/messages', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;

  try {
    const msgRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `SELECT id, conversation_id, role, content, tokens_used, created_at
         FROM messages
         WHERE conversation_id = $1 AND tenant_id = $2
         ORDER BY created_at ASC`,
        [id, req.tenant.id]
      )
    );

    res.json({ messages: msgRes.rows });
  } catch (err: any) {
    logger.error('Fetch messages error:', err);
    res.status(500).json({ error: 'Failed to fetch conversation messages' });
  }
});

// DELETE /api/chat/conversations/:id
router.delete('/conversations/:id', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;

  try {
    await executeTenantQuery(req.tenant.id, async (client) => {
      await client.query('DELETE FROM messages WHERE conversation_id = $1 AND tenant_id = $2', [id, req.tenant.id]);
      await client.query('DELETE FROM conversations WHERE id = $1 AND tenant_id = $2', [id, req.tenant.id]);
    });

    res.json({ success: true, message: 'Conversation deleted successfully' });
  } catch (err: any) {
    logger.error('Delete conversation error:', err);
    res.status(500).json({ error: 'Failed to delete conversation' });
  }
});

// POST /api/chat & POST /api/v1/chat
async function handleChat(req: any, res: any) {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  // Lock chat after Day 14 grace period if tenant is past_due
  if (req.tenant.status === 'past_due' && (req.tenant.metadata?.dunning_stage || 0) >= 4) {
    return res.status(403).json({
      error: 'Payment grace period expired. Please update your payment method to resume AI chat service.',
      lockReason: 'dunning_grace_period_expired',
      requireBillingUpdate: true,
    });
  }

  const { message, conversationId, agentId, model = 'qwen3-coder', stream = true } = req.body;
  if (!message) return res.status(400).json({ error: 'Message text is required' });

  const userId = req.user?.userId || '00000000-0000-0000-0000-000000000000';

  try {
    let currentConvId = conversationId;
    if (!currentConvId) {
      const title = message.length > 30 ? message.substring(0, 30) + '...' : message;
      const convRes = await executeTenantQuery(req.tenant.id, (client) =>
        client.query(
          `INSERT INTO conversations (tenant_id, user_id, agent_id, title, created_at, updated_at)
           VALUES ($1, $2, $3, $4, NOW(), NOW())
           RETURNING id`,
          [req.tenant.id, userId, agentId || null, title]
        )
      );
      currentConvId = convRes.rows[0].id;
    }

    // RAG Context retrieval
    const ragContext = await RagService.queryContext(req.tenant.id, message, 3);
    const promptTokens = countExactTokens(message) + countExactTokens(ragContext);

    // Save user message
    await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        'INSERT INTO messages (tenant_id, conversation_id, role, content, tokens_used) VALUES ($1, $2, $3, $4, $5)',
        [req.tenant.id, currentConvId, 'user', message, countExactTokens(message)]
      )
    );

    if (stream) {
      res.setHeader('Content-Type', 'text/event-stream');
      res.setHeader('Cache-Control', 'no-cache');
      res.setHeader('Connection', 'keep-alive');

      const ollamaUrl = process.env.OLLAMA_URL || 'http://ollama:11434';
      let fullResponseText = '';

      try {
        const ollamaRes = await axios.post(
          `${ollamaUrl}/api/chat`,
          {
            model,
            messages: [
              { role: 'system', content: `Context:\n${ragContext}` },
              { role: 'user', content: message },
            ],
            stream: true,
          },
          { responseType: 'stream', timeout: 30000 }
        );

        ollamaRes.data.on('data', (chunk: Buffer) => {
          const lines = chunk.toString().split('\n').filter(Boolean);
          for (const line of lines) {
            try {
              const parsed = JSON.parse(line);
              if (parsed.message?.content) {
                fullResponseText += parsed.message.content;
                res.write(`data: ${JSON.stringify({ content: parsed.message.content, conversationId: currentConvId })}\n\n`);
              }
            } catch (e) {
              // Ignore line parse edge cases
            }
          }
        });

        ollamaRes.data.on('end', async () => {
          const completionTokens = countExactTokens(fullResponseText);
          await executeTenantQuery(req.tenant.id, (client) =>
            client.query(
              'INSERT INTO messages (tenant_id, conversation_id, role, content, tokens_used) VALUES ($1, $2, $3, $4, $5)',
              [req.tenant.id, currentConvId, 'assistant', fullResponseText, completionTokens]
            )
          );
          res.write(`data: [DONE]\n\n`);
          res.end();
        });

        ollamaRes.data.on('error', async () => {
          const fallback = getMockResponse([], message, model);
          await executeTenantQuery(req.tenant.id, (client) =>
            client.query(
              'INSERT INTO messages (tenant_id, conversation_id, role, content, tokens_used) VALUES ($1, $2, $3, $4, $5)',
              [req.tenant.id, currentConvId, 'assistant', fallback, countExactTokens(fallback)]
            )
          );
          res.write(`data: ${JSON.stringify({ content: fallback, conversationId: currentConvId })}\n\n`);
          res.write(`data: [DONE]\n\n`);
          res.end();
        });
      } catch (ollamaErr) {
        const fallback = getMockResponse([], message, model);
        await executeTenantQuery(req.tenant.id, (client) =>
          client.query(
            'INSERT INTO messages (tenant_id, conversation_id, role, content, tokens_used) VALUES ($1, $2, $3, $4, $5)',
            [req.tenant.id, currentConvId, 'assistant', fallback, countExactTokens(fallback)]
          )
        );
        res.write(`data: ${JSON.stringify({ content: fallback, conversationId: currentConvId })}\n\n`);
        res.write(`data: [DONE]\n\n`);
        res.end();
      }
    } else {
      const fallback = getMockResponse([], message, model);
      const completionTokens = countExactTokens(fallback);
      await executeTenantQuery(req.tenant.id, (client) =>
        client.query(
          'INSERT INTO messages (tenant_id, conversation_id, role, content, tokens_used) VALUES ($1, $2, $3, $4, $5)',
          [req.tenant.id, currentConvId, 'assistant', fallback, completionTokens]
        )
      );

      res.json({
        conversationId: currentConvId,
        message: fallback,
        tokensUsed: promptTokens + completionTokens,
      });
    }
  } catch (err: any) {
    logger.error('Chat processing error:', err);
    res.status(500).json({ error: 'Failed to process chat message' });
  }
}

router.post('/', handleChat);
router.post('/v1', handleChat);

export default router;
