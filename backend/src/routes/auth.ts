import { Router, Response } from "express";
import { z } from "zod";
import { prisma } from "../lib/prisma.js";
import { AuthUtils } from "../lib/auth.js";
import { authMiddleware, AuthenticatedRequest } from "../middleware/auth.js";
import { validate } from "../middleware/validate.js";
import { EmailService } from "../services/email.service.js";
import { DockerService } from "../services/docker.service.js";

const router = Router();

const signupSchema = z.object({
  body: z.object({
    email: z.string().email(),
    password: z.string().min(6),
    name: z.string().min(2),
    company: z.string().optional(),
    plan: z.enum(["STARTER", "PRO", "BUSINESS", "ENTERPRISE"]).default("STARTER"),
    aiPlan: z.enum(["STARTER", "PRO", "BUSINESS", "ENTERPRISE"]).default("STARTER"),
    agentType: z.enum(["CHAT", "CODING", "HYBRID"]).default("CHAT"),
    model: z.string().default("harikson-chat-8b"),
    n8nEnabled: z.boolean().default(true),
    aiEnabled: z.boolean().default(false),
  }),
});

const loginSchema = z.object({
  body: z.object({
    email: z.string().email(),
    password: z.string(),
  }),
});

// POST /auth/signup
router.post("/signup", validate(signupSchema), async (req, res, next) => {
  try {
    const { email, password, name, company, plan, aiPlan, agentType, model, n8nEnabled, aiEnabled } = req.body;

    const existingUser = await prisma.user.findUnique({ where: { email } });
    if (existingUser) {
      return res.status(400).json({ error: "Email is already registered" });
    }

    const hashedPassword = AuthUtils.hashPassword(password);
    
    const userCount = await prisma.user.count();
    const role = userCount === 0 ? "ADMIN" : "USER";
    const autoApprove = process.env.AUTO_APPROVE_USERS !== "false";
    const status = (role === "ADMIN" || autoApprove) ? "ACTIVE" : "PENDING";

    const user = await prisma.user.create({
      data: {
        email,
        password: hashedPassword,
        name,
        company,
        plan,
        aiPlan,
        role,
        status,
        agentType,
        model,
        n8nEnabled,
        aiEnabled,
      },
    });

    // Auto-provision instance if active
    if (status === "ACTIVE") {
      try {
        const safeName = user.email.split("@")[0].replace(/[^a-zA-Z0-9]/g, "").toLowerCase();
        const apps: string[] = [];
        if (user.n8nEnabled) apps.push("n8n");
        if (user.aiEnabled) apps.push("openwebui");

        const containerInfo = await DockerService.createInstance(safeName, user.plan, apps);
        await prisma.instance.create({
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
      } catch (provErr) {
        console.warn("Could not auto-provision instance on signup:", provErr);
      }
    }

    // Send welcome email
    try {
      await EmailService.sendWelcomeEmail(email, name);
    } catch (err) {
      console.warn("Could not dispatch welcome email:", err);
    }

    res.status(201).json({
      message: status === "ACTIVE" 
        ? "Registration successful! You can now log in." 
        : "Registration successful. Pending admin activation.",
      userId: user.id,
      role: user.role,
      status: user.status,
    });
  } catch (error) {
    next(error);
  }
});

// POST /auth/login
router.post("/login", validate(loginSchema), async (req, res, next) => {
  try {
    const { email, password } = req.body;

    const user = await prisma.user.findUnique({ where: { email } });
    if (!user || !user.password) {
      return res.status(401).json({ error: "Invalid email or password" });
    }

    if (user.status === "PENDING") {
      return res.status(403).json({ error: "Your account is pending administrator approval." });
    }

    if (user.status === "SUSPENDED") {
      return res.status(403).json({ error: "Your account has been suspended. Please contact support." });
    }

    const isMatch = AuthUtils.comparePassword(password, user.password);
    if (!isMatch) {
      return res.status(401).json({ error: "Invalid email or password" });
    }

    // Sign session token
    const token = AuthUtils.generateToken({ userId: user.id, role: user.role });

    // Store Prisma Session
    await prisma.session.create({
      data: {
        userId: user.id,
        expiresAt: new Date(Date.now() + 7 * 24 * 60 * 60 * 1000), // 7 days
      },
    });

    res.status(200).json({
      token,
      user: {
        id: user.id,
        email: user.email,
        name: user.name,
        role: user.role,
        plan: user.plan,
        aiPlan: user.aiPlan,
        status: user.status,
        n8nEnabled: user.n8nEnabled,
        aiEnabled: user.aiEnabled,
      },
    });
  } catch (error) {
    next(error);
  }
});

// GET /auth/me
router.get("/me", authMiddleware, async (req: AuthenticatedRequest, res: Response, next) => {
  try {
    const user = await prisma.user.findUnique({
      where: { id: req.user?.userId },
      select: {
        id: true,
        email: true,
        name: true,
        company: true,
        role: true,
        plan: true,
        aiPlan: true,
        status: true,
        createdAt: true,
        n8nEnabled: true,
        aiEnabled: true,
      },
    });

    if (!user) {
      return res.status(404).json({ error: "User not found" });
    }

    res.status(200).json(user);
  } catch (error) {
    next(error);
  }
});

export default router;
