import { Request, Response, NextFunction } from 'express';
import { Logger } from '../observability/logger.js';

/**
 * Middleware to enforce strict security restrictions during admin impersonation sessions.
 * Impersonating admins are forbidden from modifying billing, 2FA, or deleting user accounts.
 */
export function preventImpersonationActions(restrictedCategory: 'billing' | 'account_delete' | '2fa') {
  return (req: Request, res: Response, next: NextFunction) => {
    const user = req.user;

    if (user && user.isImpersonating) {
      Logger.warn(
        `🚨 [IMPERSONATION RESTRICTION BLOCKED] Admin ${user.impersonatedBy} attempted restricted ${restrictedCategory} operation on user ${user.id} [${req.method} ${req.originalUrl || req.url}]`
      );

      return res.status(403).json({
        error: `Forbidden: Admin impersonators are strictly restricted from performing ${restrictedCategory} operations.`,
        code: 'IMPERSONATION_RESTRICTED',
        category: restrictedCategory,
        impersonatedBy: user.impersonatedBy,
      });
    }

    return next();
  };
}
