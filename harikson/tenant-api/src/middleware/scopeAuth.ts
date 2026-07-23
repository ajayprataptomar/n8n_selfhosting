import { Request, Response, NextFunction } from 'express';
import { Logger } from '../observability/logger.js';

export const VALID_SCOPES = [
  'chat:read',
  'chat:write',
  'documents:read',
  'documents:write',
  'user:read',
  'user:write',
  'billing:read',
  'admin:*',
] as const;

export type Scope = typeof VALID_SCOPES[number];

/**
 * Express middleware factory to enforce scope-based authorization for API keys.
 * Standard user JWT sessions automatically bypass scope checks (they possess full user privileges).
 * API keys MUST possess at least one matching scope or wildcard ('admin:*' / '*').
 */
export function requireScopes(...requiredScopes: Scope[]) {
  return (req: Request, res: Response, next: NextFunction) => {
    const user = req.user;

    // Standard session JWT logins have full user authorization
    if (!user || !user.isDeveloperKey) {
      return next();
    }

    const keyScopes: string[] = Array.isArray(user.scopes)
      ? user.scopes
      : ['chat:read', 'chat:write', 'documents:read', 'documents:write'];

    // Check if key has wildcard ('admin:*' or '*') or any required scope
    const hasWildcard = keyScopes.includes('*') || keyScopes.includes('admin:*');
    const hasRequiredScope = requiredScopes.some((reqScope) => keyScopes.includes(reqScope));

    if (!hasWildcard && !hasRequiredScope) {
      Logger.warn(
        `🚨 [SCOPE SECURITY ALERT] API Key ${user.apiKeyId || 'unknown'} attempted unauthorized operation [${req.method} ${req.originalUrl || req.url}] requiring scopes: [${requiredScopes.join(', ')}]. Key Scopes: [${keyScopes.join(', ')}]`
      );

      return res.status(403).json({
        error: 'Forbidden: Insufficient API key scopes',
        requiredScopes,
        grantedScopes: keyScopes,
        message: `This API key requires one of the following scopes: ${requiredScopes.join(', ')}`,
      });
    }

    // Log authorized scope usage
    Logger.info(
      `[SCOPE TRACKING] API Key ${user.apiKeyId} authorized for [${req.method} ${req.path}] with scopes: [${keyScopes.join(', ')}]`
    );

    return next();
  };
}
