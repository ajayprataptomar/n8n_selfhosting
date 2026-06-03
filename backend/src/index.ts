import express from "express";
import cors from "cors";
import dotenv from "dotenv";

// Load configurations
dotenv.config();

import { prisma } from "./lib/prisma.js";
import { setupScheduledJobs } from "./jobs/queue.js";

// Initialize BullMQ workers so they listen to background events
import "./jobs/backup.js";
import "./jobs/invoice.js";
import "./jobs/monitor.js";

// Import Routes
import authRouter from "./routes/auth.js";
import usersRouter from "./routes/users.js";
import instancesRouter from "./routes/instances.js";
import billingRouter from "./routes/billing.js";
import monitoringRouter from "./routes/monitoring.js";

// Import Error Middleware
import { errorHandler } from "./middleware/error.js";

const app = express();
const port = process.env.PORT || 3000;

// Enable CORS with support for credentials
app.use(cors({
  origin: true,
  credentials: true,
}));

app.use(express.json());

// Request logger middleware
app.use((req, _res, next) => {
  console.log(`[HTTP Request] ${req.method} ${req.path}`);
  next();
});

// Traefik / health-check endpoint
app.get("/health", async (_req, res) => {
  try {
    // Check DB health
    await prisma.$queryRaw`SELECT 1`;
    res.status(200).json({ status: "healthy", timestamp: new Date().toISOString() });
  } catch (error) {
    res.status(500).json({ status: "unhealthy", error: String(error) });
  }
});

// Map routers
app.use("/auth", authRouter);
app.use("/users", usersRouter);
app.use("/instances", instancesRouter);
app.use("/billing", billingRouter);
app.use("/monitoring", monitoringRouter);

// Global Error Handler
app.use(errorHandler);

// Start server
app.listen(port, async () => {
  console.log(`⚡ [Neuravolt Backend] API Server listening on port ${port}`);
  
  // Register recurring background tasks
  try {
    await setupScheduledJobs();
  } catch (err) {
    console.error("⚠️ Failed to configure background scheduling:", err);
  }
});
