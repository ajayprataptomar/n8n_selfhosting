import { Router, Response } from "express";
import { prisma } from "../lib/prisma.js";
import { authMiddleware, adminMiddleware, AuthenticatedRequest } from "../middleware/auth.js";
import { DockerService } from "../services/docker.service.js";
import { EmailService } from "../services/email.service.js";

const router = Router();

// Apply global auth check
router.use(authMiddleware);

// GET /users - Admin only list users
router.get("/", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const users = await prisma.user.findMany({
      include: {
        instances: true,
        invoices: true,
      },
      orderBy: { createdAt: "desc" },
    });
    res.status(200).json(users);
  } catch (error) {
    next(error);
  }
});

// PATCH /users/:id/approve - Admin only approval trigger
router.patch("/:id/approve", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;

    const user = await prisma.user.findUnique({ where: { id }, include: { instances: true } });
    if (!user) {
      return res.status(404).json({ error: "User not found" });
    }

    // Check if client instance already provisioned; if not, create one!
    let instance = user.instances[0];
    if (user.status === "ACTIVE" && instance) {
      return res.status(400).json({ error: "User is already active" });
    }

    // Activate the user. If a previous provisioning attempt failed after activation,
    // this keeps retry approval idempotent for active users without an instance.
    await prisma.user.update({
      where: { id },
      data: { status: "ACTIVE" },
    });

    if (!instance) {
      // Clean name from email
      const safeName = user.email.split("@")[0].replace(/[^a-zA-Z0-9]/g, "").toLowerCase();
      
      // Map apps based on entitlements
      const apps: string[] = [];
      if (user.n8nEnabled) apps.push("n8n");
      if (user.aiEnabled) apps.push("openwebui");
      
      // Auto-deploy instance
      const containerInfo = await DockerService.createInstance(safeName, user.plan, apps);
      
      instance = await prisma.instance.create({
        data: {
          userId: user.id,
          name: safeName,
          domain: containerInfo.domain,
          containerId: containerInfo.containerId,
          status: "RUNNING",
          cpuLimit: user.plan === "BUSINESS" ? 2.0 : user.plan === "PRO" ? 1.0 : 0.5,
          memoryLimit: user.plan === "BUSINESS" ? "2048m" : user.plan === "PRO" ? "1024m" : "512m",
          storageLimit: user.plan === "BUSINESS" ? "50GB" : user.plan === "PRO" ? "25GB" : "10GB",
          apps: apps,
          agentType: user.agentType,
          model: user.model,
        },
      });
    } else {
      // If stopped, boot it up
      if (instance.containerId) {
        await DockerService.startInstance(instance.containerId);
        await prisma.instance.update({
          where: { id: instance.id },
          data: { status: "RUNNING" },
        });
      }
    }

    // Send credentials email
    try {
      await EmailService.sendCredentialsEmail(user.email, user.name || "Valued Member", instance.domain);
    } catch (err) {
      console.warn("Could not dispatch welcome credentials email:", err);
    }

    res.status(200).json({ message: "User approved and instance container provisioned.", instance });
  } catch (error) {
    next(error);
  }
});

// PATCH /users/:id/suspend - Admin only suspend user
router.patch("/:id/suspend", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;

    const user = await prisma.user.findUnique({ where: { id }, include: { instances: true } });
    if (!user) {
      return res.status(404).json({ error: "User not found" });
    }

    await prisma.user.update({
      where: { id },
      data: { status: "SUSPENDED" },
    });

    // Suspend customer instance workload
    for (const instance of user.instances) {
      if (instance.containerId) {
        await DockerService.stopInstance(instance.containerId);
        await prisma.instance.update({
          where: { id: instance.id },
          data: { status: "STOPPED" },
        });
      }
    }

    res.status(200).json({ message: "User suspended and containers halted." });
  } catch (error) {
    next(error);
  }
});

// PATCH /users/:id/unsuspend - Admin only unsuspend user
router.patch("/:id/unsuspend", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;

    const user = await prisma.user.findUnique({ where: { id }, include: { instances: true } });
    if (!user) {
      return res.status(404).json({ error: "User not found" });
    }

    await prisma.user.update({
      where: { id },
      data: { status: "ACTIVE" },
    });

    // Boot up workload again
    for (const instance of user.instances) {
      if (instance.containerId) {
        await DockerService.startInstance(instance.containerId);
        await prisma.instance.update({
          where: { id: instance.id },
          data: { status: "RUNNING" },
        });
      }
    }

    res.status(200).json({ message: "User unsuspended and workloads resumed." });
  } catch (error) {
    next(error);
  }
});

// DELETE /users/:id - Admin only delete user and destroy workloads
router.delete("/:id", adminMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const { id } = req.params;

    const user = await prisma.user.findUnique({ where: { id }, include: { instances: true } });
    if (!user) {
      return res.status(404).json({ error: "User not found" });
    }

    // Destroy all client containers & data volumes on host
    for (const instance of user.instances) {
      if (instance.containerId) {
        try {
          await DockerService.deleteInstance(instance.containerId);
        } catch (err) {
          console.warn(`Failed to destroy container ${instance.containerId}:`, err);
        }
      }
      try {
        await DockerService.deleteVolume(instance.name);
      } catch (err) {
        console.warn(`Failed to remove volume for instance ${instance.name}:`, err);
      }
    }

    // Delete user from DB (Prisma cascade delete will clean up instances, invoices, and sessions)
    await prisma.user.delete({ where: { id } });

    res.status(200).json({ message: "User account deleted and all workloads destroyed." });
  } catch (error) {
    next(error);
  }
});

export default router;
