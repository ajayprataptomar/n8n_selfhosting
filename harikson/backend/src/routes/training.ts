import { Router, Response } from 'express';
import { prisma } from '../config/database.js';
import {
  n8nAuthBridge,
  AuthenticatedAdminRequest,
} from '../middleware/n8n-auth-bridge.js';

const router = Router();

router.use(n8nAuthBridge);

// GET /training - Retrieve global fine-tuning runs across all tenants
router.get('/', async (req: AuthenticatedAdminRequest, res: Response) => {
  try {
    const jobs = await prisma.fineTuneJob.findMany({
      include: {
        tenant: {
          select: { name: true, domain: true },
        },
      },
      orderBy: { createdAt: 'desc' },
    });

    return res.status(200).json(jobs);
  } catch (error: any) {
    return res.status(500).json({ error: error.message });
  }
});

// POST /training/:tenantId - Trigger a custom QLoRA fine-tuning run
router.post(
  '/:tenantId',
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { tenantId } = req.params;
      const { baseModel } = req.body;

      const tenant = await prisma.tenant.findUnique({
        where: { id: tenantId },
      });
      if (!tenant) return res.status(404).json({ error: 'Tenant not found' });

      // Insert new fine-tune run
      const job = await prisma.fineTuneJob.create({
        data: {
          tenantId,
          status: 'TRAINING',
          baseModel: baseModel || tenant.model,
          adapterName: `nv-adapter-${tenant.name}-${Date.now().toString().substring(8)}`,
          metrics: {
            epoch: 0.1,
            loss: 2.45,
            etaSeconds: 1200,
          },
        },
      });

      // Background job simulation: complete training in 20 seconds
      setTimeout(async () => {
        try {
          await prisma.fineTuneJob.update({
            where: { id: job.id },
            data: {
              status: 'COMPLETED',
              completedAt: new Date(),
              metrics: {
                epoch: 3,
                loss: 0.82,
                finalAccuracy: 0.94,
              },
            },
          });
        } catch (err) {
          console.error('Failed to complete mock fine tune task:', err);
        }
      }, 20000);

      return res.status(202).json(job);
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

export default router;
