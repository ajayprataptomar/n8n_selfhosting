import { Router, Response } from "express";
import { z } from "zod";
import { prisma } from "../lib/prisma.js";
import { AuthUtils } from "../lib/auth.js";
import { authMiddleware, AuthenticatedRequest } from "../middleware/auth.js";
import { validate } from "../middleware/validate.js";
import { EmailService } from "../services/email.service.js";

const router = Router();

const signupSchema = z.object({
  body: z.object({
    email: z.string().email(),
    password: z.string().min(6),
    name: z.string().min(2),
    company: z.string().optional(),
    plan: z.enum(["LITE", "BASIC", "PRO", "HEAVY"]).default("BASIC"),
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
    const { email, password, name, company, plan } = req.body;

    const existingUser = await prisma.user.findUnique({ where: { email } });
    if (existingUser) {
      return res.status(400).json({ error: "Email is already registered" });
    }

    const hashedPassword = AuthUtils.hashPassword(password);
    
    // Check if it's the very first user; if so, default to ADMIN role to ease setup
    const userCount = await prisma.user.count();
    const role = userCount === 0 ? "ADMIN" : "USER";
    const status = role === "ADMIN" ? "ACTIVE" : "PENDING";

    const user = await prisma.user.create({
      data: {
        email,
        password: hashedPassword,
        name,
        company,
        plan,
        role,
        status,
      },
    });

    // Send welcome email
    try {
      await EmailService.sendWelcomeEmail(email, name);
    } catch (err) {
      console.warn("Could not dispatch welcome email:", err);
    }

    res.status(201).json({
      message: "Registration successful. Pending admin activation.",
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
        status: user.status,
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
        status: true,
        createdAt: true,
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
