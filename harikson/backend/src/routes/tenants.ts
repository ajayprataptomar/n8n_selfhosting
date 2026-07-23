import { Router, Response } from 'express';
import { z } from 'zod';
import { prisma } from '../config/database.js';
import {
  n8nAuthBridge,
  AuthenticatedAdminRequest,
} from '../middleware/n8n-auth-bridge.js';
import { DockerService } from '../services/docker.service.js';
import { BillingService } from '../services/billing.service.js';

const router = Router();
const billingService = new BillingService();

// Secure all tenant endpoints under n8n auth
router.use(n8nAuthBridge);

const requireRole = (...allowedRoles: string[]) => {
  return (req: AuthenticatedAdminRequest, res: Response, next: any) => {
    if (!req.user || !allowedRoles.includes(req.user.role)) {
      return res
        .status(403)
        .json({ error: 'Access Denied: Insufficient permissions' });
    }
    next();
  };
};

const createTenantSchema = z.object({
  name: z
    .string()
    .min(2)
    .regex(/^[a-z0-9-]+$/),
  plan: z.enum(['STARTER', 'PRO', 'BUSINESS', 'ENTERPRISE']),
  agentType: z.enum(['CHAT', 'CODING', 'HYBRID']),
  model: z.string().default('qwen3-coder-8b'),
  email: z.string().email().optional(),
  phone: z.string().optional(),
  whiteLabelSettings: z.any().optional(),
});

// GET /tenants - List all tenants and fetch their live metrics
router.get('/', async (req: AuthenticatedAdminRequest, res: Response) => {
  try {
    const list = await prisma.tenant.findMany({
      orderBy: { createdAt: 'desc' },
    });

    const tenantsWithMetrics = await Promise.all(
      list.map(async (t) => {
        let cpuUsage = 0;
        let memoryUsage = 0;
        let diskUsage = '0 GB';

        if (t.status === 'RUNNING' && t.containerId) {
          try {
            const metrics = await DockerService.getTenantMetrics(
              t.name,
              t.containerId
            );
            cpuUsage = metrics.cpuUsage;
            memoryUsage = metrics.memoryUsage;
            diskUsage = metrics.diskUsage;
          } catch (err: any) {
            console.warn(
              `Warning getting tenant metrics for ${t.name}:`,
              err.message
            );
          }
        }

        return {
          ...t,
          cpuUsage,
          memoryUsage,
          diskUsage,
        };
      })
    );

    return res.status(200).json(tenantsWithMetrics);
  } catch (error: any) {
    return res.status(500).json({ error: error.message });
  }
});

// POST /tenants - Deploy new tenant multi-container pod
router.post('/', async (req: AuthenticatedAdminRequest, res: Response) => {
  try {
    const check = createTenantSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({
        error: 'Invalid request payload',
        details: check.error.format(),
      });
    }

    const { name } = check.data;

    // Check for collisions
    const existing = await prisma.tenant.findUnique({
      where: { domain: `${name}.neuravolt.cloud` },
    });
    if (existing) {
      return res
        .status(400)
        .json({ error: `Subdomain ${name}.neuravolt.cloud is already in use` });
    }

    const result = await billingService.handleSignup(check.data);
    return res.status(201).json(result);
  } catch (error: any) {
    return res.status(500).json({ error: error.message });
  }
});

// POST /tenants/:id/approve - Approve pending tenant deployment
router.post(
  '/:id/approve',
  requireRole('super', 'ops'),
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const tenant = await billingService.approveAndDeploy(
        req.params.id,
        req.user!.id
      );
      return res.json({
        message: 'Approved and deployment triggered',
        tenant,
      });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /tenants/:id/reject - Reject pending tenant deployment request
router.post(
  '/:id/reject',
  requireRole('super', 'ops'),
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { id } = req.params;
      const tenant = await prisma.tenant.update({
        where: { id },
        data: {
          approvalStatus: 'rejected',
          approvedBy: req.user!.id,
          approvedAt: new Date(),
          status: 'STOPPED',
        },
      });

      await billingService.emailUser(tenant.email || 'user@neuravolt.cloud', {
        subject: 'Harikson Request Update',
        body: `Your request has been rejected. Reason: ${req.body.reason || 'Not specified'}`,
      });

      return res.json({ message: 'Tenant request rejected' });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// GET /tenants/pending - List all pending approvals
router.get(
  '/pending',
  requireRole('super', 'ops'),
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const pending = await prisma.tenant.findMany({
        where: { approvalStatus: 'pending' },
        orderBy: { requestedAt: 'asc' },
      });
      return res.json(pending);
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// GET /tenants/billing/mode - Fetch system billing mode settings
router.get(
  '/billing/mode',
  requireRole('super'),
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const config = await prisma.systemSetting.findUnique({
        where: { key: 'billing_mode' },
      });
      return res.json(config?.value || { mode: 'manual' });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /tenants/billing/mode - Update system billing mode settings
router.post(
  '/billing/mode',
  requireRole('super'),
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { mode, gateway } = req.body;

      if (!['manual', 'auto'].includes(mode)) {
        return res.status(400).json({ error: 'Invalid mode' });
      }

      if (mode === 'auto' && !['razorpay', 'cashfree'].includes(gateway)) {
        return res.status(400).json({ error: 'Invalid gateway' });
      }

      const result = await billingService.switchBillingMode(mode, gateway);
      return res.json(result);
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /tenants/:id/start
router.post(
  '/:id/start',
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { id } = req.params;
      const tenant = await prisma.tenant.findUnique({ where: { id } });
      if (!tenant) return res.status(404).json({ error: 'Tenant not found' });

      await DockerService.startTenantStack(tenant.name);
      await prisma.tenant.update({
        where: { id },
        data: { status: 'RUNNING' },
      });

      return res
        .status(200)
        .json({ message: `Agent stack ${tenant.name} started successfully` });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /tenants/:id/stop
router.post(
  '/:id/stop',
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { id } = req.params;
      const tenant = await prisma.tenant.findUnique({ where: { id } });
      if (!tenant) return res.status(404).json({ error: 'Tenant not found' });

      await DockerService.stopTenantStack(tenant.name);
      await prisma.tenant.update({
        where: { id },
        data: { status: 'STOPPED' },
      });

      return res
        .status(200)
        .json({ message: `Agent stack ${tenant.name} stopped` });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /tenants/:id/restart
router.post(
  '/:id/restart',
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { id } = req.params;
      const tenant = await prisma.tenant.findUnique({ where: { id } });
      if (!tenant) return res.status(404).json({ error: 'Tenant not found' });

      await DockerService.restartTenantStack(tenant.name);
      await prisma.tenant.update({
        where: { id },
        data: { status: 'RUNNING' },
      });

      return res
        .status(200)
        .json({ message: `Agent stack ${tenant.name} restarted` });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// DELETE /tenants/:id
router.post(
  '/:id/delete',
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { id } = req.params;
      const tenant = await prisma.tenant.findUnique({ where: { id } });
      if (!tenant) return res.status(404).json({ error: 'Tenant not found' });

      // Destroy container group and volumes
      if (tenant.containerId) {
        await DockerService.destroyTenantStack(tenant.name, tenant.containerId);
      }

      await prisma.tenant.delete({ where: { id } });
      return res.status(200).json({
        message: `Tenant ${tenant.name} and compute stack deleted successfully`,
      });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /tenants/:id/update - Update tenant plan and/or model config
router.post(
  '/:id/update',
  requireRole('super', 'ops'),
  async (req: AuthenticatedAdminRequest, res: Response) => {
    try {
      const { id } = req.params;
      const { plan, model, agentType } = req.body;

      const tenant = await prisma.tenant.findUnique({ where: { id } });
      if (!tenant) return res.status(404).json({ error: 'Tenant not found' });

      // Map limits based on Plan Configs
      const cpu =
        plan === 'ENTERPRISE'
          ? 4.0
          : plan === 'BUSINESS'
            ? 2.0
            : plan === 'PRO'
              ? 1.0
              : 0.5;
      const ram =
        plan === 'ENTERPRISE'
          ? '4096m'
          : plan === 'BUSINESS'
            ? '2048m'
            : plan === 'PRO'
              ? '1024m'
              : '512m';
      const ssd =
        plan === 'ENTERPRISE'
          ? '50GB'
          : plan === 'BUSINESS'
            ? '25GB'
            : plan === 'PRO'
              ? '15GB'
              : '10GB';

      const updated = await prisma.tenant.update({
        where: { id },
        data: {
          plan: plan || tenant.plan,
          model: model || tenant.model,
          agentType: agentType || tenant.agentType,
          cpuLimit: cpu,
          memoryLimit: ram,
          storageLimit: ssd,
        },
      });

      // If tenant stack exists and is running, recreate/restart it with new plan allocations
      if (updated.status === 'RUNNING' && updated.containerId) {
        try {
          await DockerService.destroyTenantStack(
            updated.name,
            updated.containerId
          );
          const deployment = await DockerService.createTenantStack(
            updated.name,
            updated.plan,
            updated.agentType
          );
          await prisma.tenant.update({
            where: { id },
            data: {
              containerId: deployment.containerId,
              domain: deployment.domain,
            },
          });
        } catch (err: any) {
          console.warn(
            '⚠️ Failed to hot-reload Docker containers for plan change:',
            err
          );
        }
      }

      return res.json({
        message: 'Tenant updated successfully',
        tenant: updated,
      });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

export default router;
