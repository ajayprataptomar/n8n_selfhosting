import helmet from 'helmet';
import rateLimit, { ipKeyGenerator } from 'express-rate-limit';
import hpp from 'hpp';

export const securityMiddleware = [
  helmet({
    contentSecurityPolicy: {
      directives: {
        defaultSrc: ["'self'"],
        styleSrc: ["'self'", "'unsafe-inline'"],
        scriptSrc: ["'self'"],
        imgSrc: ["'self'", 'data:', 'https:'],
        connectSrc: ["'self'", 'wss:', 'https:'],
      },
    },
    hsts: {
      maxAge: 31536000,
      includeSubDomains: true,
      preload: true,
    },
  }),
  hpp(),
  rateLimit({
    windowMs: 60 * 1000,
    limit: (req: any) => {
      const plan = req.tenant?.plan || req.user?.plan;
      if (!plan) return 100; // default limit
      return plan === 'STARTER'
        ? 100
        : plan === 'PRO'
          ? 1000
          : plan === 'BUSINESS'
            ? 5000
            : 10000;
    },
    keyGenerator: (req: any) =>
      req.tenant?.id || req.user?.id || ipKeyGenerator(req.ip || ''),
    standardHeaders: true,
    legacyHeaders: false,
  }),
];
