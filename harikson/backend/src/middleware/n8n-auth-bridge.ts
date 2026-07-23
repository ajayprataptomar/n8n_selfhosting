import { Request, Response, NextFunction } from 'express';
import jwt from 'jsonwebtoken';
import { prisma } from '../config/database.js';

export interface AuthenticatedAdminRequest extends Request {
  user?: {
    id: string;
    email: string;
    name: string | null;
    role: string;
  };
}

export async function n8nAuthBridge(
  req: AuthenticatedAdminRequest,
  res: Response,
  next: NextFunction
) {
  // Manual cookie parser helper
  const cookieHeader = req.headers.cookie;
  const cookies: Record<string, string> = {};
  if (cookieHeader) {
    cookieHeader.split(';').forEach((c) => {
      const [key, val] = c.trim().split('=');
      if (key && val) {
        cookies[key] = decodeURIComponent(val);
      }
    });
  }

  // Check for JWT in cookies or authorization headers
  const authHeader = req.headers.authorization;
  const tokenFromHeader =
    authHeader && authHeader.startsWith('Bearer ')
      ? authHeader.split(' ')[1]
      : null;
  const rawToken =
    cookies.n8n_session || req.headers['x-n8n-token'] || tokenFromHeader;
  const n8nToken = Array.isArray(rawToken) ? rawToken[0] : rawToken;

  if (!n8nToken) {
    console.warn(
      '🔒 [Auth Bridge] Authentication failed: No session token provided.'
    );
    return res
      .status(401)
      .json({ error: 'Access Denied: No n8n session token found' });
  }

  try {
    const jwtSecret =
      process.env.N8N_JWT_SECRET || 'neuravolt_n8n_shared_jwt_secret_key';

    // Verify n8n JWT session
    const decoded = jwt.verify(n8nToken, jwtSecret) as any;

    if (!decoded || !decoded.email) {
      return res
        .status(401)
        .json({ error: 'Access Denied: Invalid token payload' });
    }

    const name = decoded.name || decoded.email.split('@')[0];
    const role = mapN8nRoleToNeuravolt(decoded.role || 'admin');

    // Upsert admin user into the isolated database
    const adminUser = await prisma.adminUser.upsert({
      where: { email: decoded.email },
      create: {
        email: decoded.email,
        name: name,
        passwordHash: 'n8n-managed-auth', // Auth is offloaded to the main n8n session
        role: role,
        lastLoginAt: new Date(),
      },
      update: {
        name: name,
        role: role,
        lastLoginAt: new Date(),
      },
    });

    req.user = {
      id: adminUser.id,
      email: adminUser.email,
      name: adminUser.name,
      role: adminUser.role,
    };

    next();
  } catch (error) {
    console.error('🔒 [Auth Bridge] JWT verification failed:', error);
    return res
      .status(401)
      .json({ error: 'Access Denied: Session token is invalid or expired' });
  }
}

function mapN8nRoleToNeuravolt(n8nRole: string): string {
  const roleMap: Record<string, string> = {
    owner: 'super',
    admin: 'ops',
    finance: 'billing',
    support: 'support',
  };
  return roleMap[n8nRole.toLowerCase()] || 'ops';
}
export default n8nAuthBridge;
