import { z } from 'zod';

export const profileUpdateSchema = z.object({
  name: z.string().min(1, 'Name is required').optional().nullable(),
  username: z.string().optional().nullable(),
  phone: z.string().optional().nullable(),
  company: z.string().optional().nullable(),
  jobTitle: z.string().optional().nullable(),
  department: z.string().optional().nullable(),
  country: z.string().optional().nullable(),
  bio: z.string().optional().nullable(),
});

export const settingsUpdateSchema = z.record(z.any());
