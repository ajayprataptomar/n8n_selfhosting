import { z } from 'zod';

export const planChangeSchema = z.object({
  planId: z.string().min(1, 'Plan ID is required'),
});
