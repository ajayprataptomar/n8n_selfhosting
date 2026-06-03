import { Request, Response, NextFunction } from "express";
import { AuthUtils } from "../lib/auth.js";

export interface AuthenticatedRequest extends Request {
  user?: {
    userId: string;
    role: string;
  };
}

export function authMiddleware(req: AuthenticatedRequest, res: Response, next: NextFunction) {
  const authHeader = req.headers.authorization;
  if (!authHeader || !authHeader.startsWith("Bearer ")) {
    return res.status(401).json({ error: "Access denied. No token provided." });
  }

  const token = authHeader.split(" ")[1];
  const decoded = AuthUtils.verifyToken(token);
  if (!decoded) {
    return res.status(401).json({ error: "Invalid or expired token." });
  }

  req.user = decoded;
  next();
}

export function adminMiddleware(req: AuthenticatedRequest, res: Response, next: NextFunction) {
  if (!req.user || req.user.role !== "ADMIN") {
    return res.status(403).json({ error: "Access restricted to administrators only." });
  }
  next();
}
