import { z } from 'zod';

export const chatMessageSchema = z.object({
  message: z.string().min(1, 'Message content is required'),
  model: z.string().optional(),
  conversationId: z
    .string()
    .uuid('Invalid conversation ID')
    .or(z.string().regex(/^[0-9a-fA-F-]{36}$/))
    .optional()
    .nullable(),
  clientHistory: z
    .array(
      z.object({
        role: z.string(),
        content: z.string(),
      })
    )
    .optional(),
  agent_id: z.string().uuid('Invalid agent ID').optional().nullable(),
  deepSearch: z.boolean().optional(),
  reasoning: z.boolean().optional(),
});
