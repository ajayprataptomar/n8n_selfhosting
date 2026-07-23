import { Router, Request, Response } from 'express';
import { z } from 'zod';
import { RagService } from '../services/rag.service.js';
import { OllamaService } from '../services/ollama.service.js';
import { ValidationService } from '../services/validation.service.js';
import { MemoryRetriever } from '../services/memory/retriever.js';
import { MemoryExtractor } from '../services/memory/extractor.js';

const router = Router();

const chatSchema = z.object({
  message: z.string().min(1),
  useRag: z.boolean().default(true),
});

router.post('/', async (req: Request, res: Response) => {
  const redis = req.app.get('redis');
  const idempotencyKey = req.headers['idempotency-key'] as string;
  let dedupKey = '';

  try {
    const check = chatSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({ error: 'Message content is required' });
    }

    if (idempotencyKey && redis) {
      const cached = await redis.get(`dedup:response:${idempotencyKey}`);
      if (cached) {
        return res.status(200).json(JSON.parse(cached));
      }
      
      dedupKey = `dedup:chat:${idempotencyKey}`;
      const exists = await redis.set(dedupKey, '1', 'EX', 300, 'NX');
      if (!exists) {
        return res.status(409).json({ error: 'Duplicate request' });
      }
    }

    const { message, useRag } = check.data;
    const tenantId = (req as any).tenantId || (req as any).tenant?.id || (req.headers['x-tenant-id'] as string);
    const userId = (req as any).userId || (req.headers['x-user-id'] as string);

    if (!tenantId || !userId) {
      return res.status(401).json({ error: 'Unauthorized: Missing tenant or user context' });
    }

    let context = '';

    if (useRag) {
      context = await RagService.queryContext(tenantId, message);
    }

    // Retrieve memories
    const memories = await MemoryRetriever.retrieve(tenantId, userId, message);
    let memoryContext = '';
    if (memories.length > 0) {
      memoryContext =
        '\nRelevant Memories about User:\n' +
        memories.map((m) => `- ${m.memory}`).join('\n');
    }

    // Enrich prompt with both document chunks and memory items
    let enrichedPrompt = message;
    if (context || memoryContext) {
      enrichedPrompt = `Use the following context to answer the user request:\n\n[CONTEXT]${context ? `\nDocuments:\n${context}` : ''}${memoryContext}\n\n[USER REQUEST]\n${message}`;
    }

    const systemPrompt =
      'You are a professional, white-labeled AI support agent deployed via Neuravolt Cloud. Help customers with their requests.';
    const response = await OllamaService.generate(enrichedPrompt, systemPrompt);

    // Call memory extraction in a background fire-and-forget task
    const conversationContextText = `User: ${message}\nAssistant: ${response}`;
    MemoryExtractor.extractAndSave(
      tenantId,
      userId,
      message,
      conversationContextText
    ).catch((err) =>
      console.error('⚠️ [Harikson Memory] Background extraction failed:', err)
    );

    // Run response through toxicity and PII validation gates
    const validation = ValidationService.validateChat(response);
    if (!validation.isValid) {
      console.warn(
        '⚠️ [Chat Router] Blocked unsafe generated output:',
        validation.reason
      );
      if (idempotencyKey && redis && dedupKey) {
        await redis.del(dedupKey);
      }
      return res.status(400).json({
        error: 'Generation Blocked',
        reason:
          validation.reason ||
          'Output failed safety checks (potential toxicity or PII leak detected)',
      });
    }

    if (idempotencyKey && redis && dedupKey) {
      const payload = { response };
      await redis.set(`dedup:response:${idempotencyKey}`, JSON.stringify(payload), 'EX', 300);
      await redis.del(dedupKey);
    }

    return res.status(200).json({ response });
  } catch (error: any) {
    if (idempotencyKey && redis && dedupKey) {
      await redis.del(dedupKey);
    }
    return res.status(500).json({ error: error.message });
  }
});

export default router;
