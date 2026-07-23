import { Router, Response } from 'express';
import { prisma } from '../config/database.js';
import {
  n8nAuthBridge,
  AuthenticatedAdminRequest,
} from '../middleware/n8n-auth-bridge.js';

const router = Router();

router.use(n8nAuthBridge);

// GET /stats - Aggregated control plane analytics
router.get('/', async (req: AuthenticatedAdminRequest, res: Response) => {
  try {
    const totalTenants = await prisma.tenant.count();
    const activeTenants = await prisma.tenant.count({
      where: { status: 'RUNNING' },
    });
    const stoppedTenants = await prisma.tenant.count({
      where: { status: 'STOPPED' },
    });
    const pendingApprovals = await prisma.tenant.count({
      where: { approvalStatus: 'pending' },
    });

    const invoiceSum = await prisma.invoice.aggregate({
      _sum: { amount: true },
      where: { status: 'paid' },
    });

    const totalRevenue = invoiceSum._sum.amount
      ? `INR ${invoiceSum._sum.amount.toFixed(2)}`
      : 'INR 0.00';

    const tenants = await prisma.tenant.findMany({
      select: { cpuLimit: true, memoryLimit: true },
    });

    let totalCpuAllocated = 0;
    let totalMemoryAllocatedMB = 0;

    for (const t of tenants) {
      totalCpuAllocated += t.cpuLimit;
      const memVal = parseInt(t.memoryLimit);
      if (!isNaN(memVal)) {
        totalMemoryAllocatedMB += memVal;
      }
    }

    return res.status(200).json({
      status: 'healthy',
      totalTenants,
      activeTenants,
      stoppedTenants,
      pendingApprovals,
      totalRevenue,
      tenants: {
        total: totalTenants,
        active: activeTenants,
        stopped: stoppedTenants,
      },
      allocations: {
        cpuCores: totalCpuAllocated,
        memoryMB: totalMemoryAllocatedMB,
      },
      timestamp: new Date().toISOString(),
    });
  } catch (error: any) {
    return res.status(500).json({ error: error.message });
  }
});

export default router;
