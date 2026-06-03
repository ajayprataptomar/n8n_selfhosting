import { Router, Response } from "express";
import { prisma } from "../lib/prisma.js";
import { authMiddleware, adminMiddleware, AuthenticatedRequest } from "../middleware/auth.js";
import { DockerService } from "../services/docker.service.js";
import os from "os";

const router = Router();

// Apply auth checking
router.use(authMiddleware);

// Helper to calculate CPU usage over a short period
function getCpuUsage(): Promise<number> {
  return new Promise((resolve) => {
    const firstMeasure = os.cpus().map(cpu => cpu.times);
    
    setTimeout(() => {
      const secondMeasure = os.cpus().map(cpu => cpu.times);
      let totalDiff = 0;
      let idleDiff = 0;
      
      for (let i = 0; i < firstMeasure.length; i++) {
        const first = firstMeasure[i];
        const second = secondMeasure[i];
        
        const firstTotal = first.user + first.nice + first.sys + first.idle + first.irq;
        const secondTotal = second.user + second.nice + second.sys + second.idle + second.irq;
        
        totalDiff += secondTotal - firstTotal;
        idleDiff += second.idle - first.idle;
      }
      
      if (totalDiff === 0) {
        resolve(0);
      } else {
        const cpuPercentage = ((totalDiff - idleDiff) / totalDiff) * 100;
        resolve(+cpuPercentage.toFixed(2));
      }
    }, 200);
  });
}

// GET /monitoring/metrics - Get metrics for instances
router.get("/metrics", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    if (req.user?.role === "ADMIN") {
      // Return accumulated cluster stats
      const instancesCount = await prisma.instance.count();
      const activeInstancesCount = await prisma.instance.count({ where: { status: "RUNNING" } });
      const usersCount = await prisma.user.count();
      
      const systemCpuAverage = await getCpuUsage();
      const totalMem = os.totalmem();
      const freeMem = os.freemem();
      const usedMem = totalMem - freeMem;
      const systemMemoryUsageGB = +(usedMem / (1024 * 1024 * 1024)).toFixed(2);
      const systemMemoryTotalGB = +(totalMem / (1024 * 1024 * 1024)).toFixed(2);

      res.status(200).json({
        totalUsers: usersCount,
        activeContainers: activeInstancesCount,
        totalContainers: instancesCount,
        systemCpuAverage,
        systemMemoryUsageGB,
        systemMemoryTotalGB,
      });
      return;
    }

    // Standard user retrieves their own instance metrics
    const userInstance = await prisma.instance.findFirst({
      where: { userId: req.user?.userId },
    });

    if (!userInstance || !userInstance.containerId) {
      return res.status(200).json({ cpuUsage: 0.0, memoryUsage: 0.0, diskUsage: "0 GB" });
    }

    const metrics = await DockerService.getMetrics(userInstance.containerId);
    res.status(200).json(metrics);
  } catch (error) {
    next(error);
  }
});

// GET /monitoring/logs/:containerId - Stream logs from docker. Admin only
router.get("/logs/:containerId", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { containerId } = req.params;
    const logs = await DockerService.getLogs(containerId);
    res.status(200).json({ logs });
  } catch (error) {
    next(error);
  }
});

export default router;
