import { Router, Response } from "express";
import { z } from "zod";
import { prisma } from "../lib/prisma.js";
import { authMiddleware, adminMiddleware, AuthenticatedRequest } from "../middleware/auth.js";
import { validate } from "../middleware/validate.js";
import { DockerService } from "../services/docker.service.js";

const router = Router();

// Apply auth checking to all instance operations
router.use(authMiddleware);

const scaleSchema = z.object({
  body: z.object({
    cpuLimit: z.number().min(0.1).max(8.0),
    memoryLimit: z.string().regex(/^\d+[mg]$/i), // e.g. 512m, 2g
  }),
});

const manualCreateSchema = z.object({
  body: z.object({
    userId: z.string(),
    name: z.string().min(2),
    plan: z.enum(["STARTER", "PRO", "BUSINESS", "ENTERPRISE"]),
    apps: z.array(z.string()).default(["n8n"]),
  }),
});

// GET /instances - retrieve instances (Admin: all, User: only owned)
router.get("/", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    if (req.user?.role === "ADMIN") {
      const instances = await prisma.instance.findMany({
        include: { user: { select: { email: true, name: true } } },
        orderBy: { createdAt: "desc" },
      });
      return res.status(200).json(instances);
    }

    const myInstances = await prisma.instance.findMany({
      where: { userId: req.user?.userId },
      orderBy: { createdAt: "desc" },
    });
    res.status(200).json(myInstances);
  } catch (error) {
    next(error);
  }
});

// POST /instances - Admin only manual instance create
router.post("/", adminMiddleware, validate(manualCreateSchema), async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { userId, name, plan, apps } = req.body;

    const user = await prisma.user.findUnique({ where: { id: userId } });
    if (!user) {
      return res.status(404).json({ error: "Target User not found" });
    }

    const safeName = name.replace(/[^a-zA-Z0-9]/g, "").toLowerCase();
    const containerInfo = await DockerService.createInstance(safeName, plan, apps);

    const cpu = plan === "HEAVY" ? 2.0 : plan === "PRO" ? 1.0 : 0.5;
    const memory = plan === "HEAVY" ? "2048m" : plan === "PRO" ? "1024m" : "512m";
    const storage = plan === "HEAVY" ? "50GB" : plan === "PRO" ? "25GB" : "10GB";

    const instance = await prisma.instance.create({
      data: {
        userId,
        name: safeName,
        domain: containerInfo.domain,
        containerId: containerInfo.containerId,
        status: "RUNNING",
        cpuLimit: cpu,
        memoryLimit: memory,
        storageLimit: storage,
        apps,
        agentType: user.agentType,
        model: user.model,
      },
    });

    res.status(201).json(instance);
  } catch (error) {
    next(error);
  }
});

// PATCH /instances/:id/scale - Admin scaling trigger
router.patch("/:id/scale", adminMiddleware, validate(scaleSchema), async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;
    const { cpuLimit, memoryLimit } = req.body;

    const instance = await prisma.instance.findUnique({ where: { id } });
    if (!instance) {
      return res.status(404).json({ error: "Instance not found" });
    }

    if (instance.containerId) {
      await DockerService.scaleInstance(instance.containerId, cpuLimit, memoryLimit);
    }

    const updatedInstance = await prisma.instance.update({
      where: { id },
      data: {
        cpuLimit,
        memoryLimit,
      },
    });

    res.status(200).json(updatedInstance);
  } catch (error) {
    next(error);
  }
});

// Helper check ownership or admin
async function checkOwnership(req: AuthenticatedRequest, instanceId: string): Promise<boolean> {
  if (req.user?.role === "ADMIN") return true;
  const instance = await prisma.instance.findFirst({
    where: { id: instanceId, userId: req.user?.userId },
  });
  return !!instance;
}

// POST /instances/:id/restart - Restart instance
router.post("/:id/restart", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;
    if (!(await checkOwnership(req, id))) {
      return res.status(403).json({ error: "Permission denied." });
    }

    const instance = await prisma.instance.findUnique({ where: { id } });
    if (!instance) {
      return res.status(404).json({ error: "Instance not found." });
    }

    if (instance.containerId) {
      try {
        await DockerService.restartInstance(instance.containerId);
      } catch (error: any) {
        if (error.statusCode === 404 || error.message?.includes("no such container")) {
          console.log(`Container ${instance.containerId} not found. Re-provisioning...`);
          const user = await prisma.user.findUnique({ where: { id: instance.userId } });
          const plan = user?.plan || "BASIC";
          const containerInfo = await DockerService.createInstance(instance.name, plan, instance.apps as string[]);
          
          await prisma.instance.update({
            where: { id },
            data: {
              containerId: containerInfo.containerId,
              status: "RUNNING"
            }
          });
          return res.status(200).json({ message: "Instance container re-provisioned and restarted." });
        }
        throw error;
      }
      await prisma.instance.update({
        where: { id },
        data: { status: "RUNNING" },
      });
    }

    res.status(200).json({ message: "Instance container restarted successfully." });
  } catch (error) {
    next(error);
  }
});

// POST /instances/:id/stop - Stop instance
router.post("/:id/stop", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;
    if (!(await checkOwnership(req, id))) {
      return res.status(403).json({ error: "Permission denied." });
    }

    const instance = await prisma.instance.findUnique({ where: { id } });
    if (!instance) {
      return res.status(404).json({ error: "Instance not found." });
    }

    if (instance.containerId) {
      try {
        await DockerService.stopInstance(instance.containerId);
      } catch (error: any) {
        if (error.statusCode === 404 || error.message?.includes("no such container")) {
          await prisma.instance.update({
            where: { id },
            data: { status: "STOPPED" },
          });
          return res.status(200).json({ message: "Instance container already stopped (does not exist)." });
        }
        throw error;
      }
      await prisma.instance.update({
        where: { id },
        data: { status: "STOPPED" },
      });
    }

    res.status(200).json({ message: "Instance container stopped." });
  } catch (error) {
    next(error);
  }
});

// POST /instances/:id/start - Start instance
router.post("/:id/start", async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;
    if (!(await checkOwnership(req, id))) {
      return res.status(403).json({ error: "Permission denied." });
    }

    const instance = await prisma.instance.findUnique({ where: { id } });
    if (!instance) {
      return res.status(404).json({ error: "Instance not found." });
    }

    if (instance.containerId) {
      try {
        await DockerService.startInstance(instance.containerId);
      } catch (error: any) {
        if (error.statusCode === 404 || error.message?.includes("no such container")) {
          console.log(`Container ${instance.containerId} not found. Re-provisioning...`);
          const user = await prisma.user.findUnique({ where: { id: instance.userId } });
          const plan = user?.plan || "BASIC";
          const containerInfo = await DockerService.createInstance(instance.name, plan, instance.apps as string[]);
          
          await prisma.instance.update({
            where: { id },
            data: {
              containerId: containerInfo.containerId,
              status: "RUNNING"
            }
          });
          return res.status(200).json({ message: "Instance container re-provisioned and started." });
        }
        throw error;
      }
      await prisma.instance.update({
        where: { id },
        data: { status: "RUNNING" },
      });
    }

    res.status(200).json({ message: "Instance container started." });
  } catch (error) {
    next(error);
  }
});

export default router;
