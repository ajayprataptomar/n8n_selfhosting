import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';

// Load local environment variables
dotenv.config();

// Connect configurations
import './config/redis.js';
import { prisma } from './config/database.js';

// Routes Import
import statsRouter from './routes/stats.js';
import tenantsRouter from './routes/tenants.js';
import vpsRouter from './routes/vps.js';
import trainingRouter from './routes/training.js';
import { securityMiddleware } from './middleware/security.middleware.js';

const app = express();
const port = process.env.PORT || 9000;

app.use(
  cors({
    origin: true,
    credentials: true,
  })
);

app.use(express.json());
app.use(securityMiddleware);

// Console request logger middleware
app.use((req, _res, next) => {
  console.log(`📡 [Harikson API] ${req.method} ${req.path}`);
  next();
});

// Health check endpoint
app.get('/health', async (_req, res) => {
  try {
    await prisma.$queryRaw`SELECT 1`;
    return res
      .status(200)
      .json({ status: 'healthy', timestamp: new Date().toISOString() });
  } catch (error: any) {
    return res.status(500).json({ status: 'unhealthy', error: error.message });
  }
});

// Map routes
app.use('/stats', statsRouter);
app.use('/tenants', tenantsRouter);
app.use('/vps', vpsRouter);
app.use('/training', trainingRouter);

// Start listening
app.listen(port, () => {
  console.log(
    `⚡ [Harikson Control Plane] Admin API operational on port ${port}`
  );
});
