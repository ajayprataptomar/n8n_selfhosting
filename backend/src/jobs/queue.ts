import { Queue } from "bullmq";
import { bullConnection } from "../lib/redis.js";

// Initialize BullMQ queues
export const backupQueue = new Queue("backup-tasks", { connection: bullConnection });
export const invoiceQueue = new Queue("invoice-tasks", { connection: bullConnection });
export const monitorQueue = new Queue("monitor-tasks", { connection: bullConnection });

export async function setupScheduledJobs() {
  console.log("⏰ Setting up Neuravolt Cloud scheduled cron jobs...");
  
  // Daily backup job: Run every night at midnight (0 0 * * *)
  await backupQueue.add("daily-backup", {}, {
    repeat: { pattern: "0 0 * * *" },
    jobId: "backup-cron",
  });

  // Monthly billing job: Run on the 1st of every month at midnight (0 0 1 * *)
  await invoiceQueue.add("monthly-billing", {}, {
    repeat: { pattern: "0 0 1 * *" },
    jobId: "billing-cron",
  });

  // Health-check monitoring job: Run every 5 minutes (*/5 * * * *)
  await monitorQueue.add("health-check", {}, {
    repeat: { pattern: "*/5 * * * *" },
    jobId: "monitoring-cron",
  });

  console.log("⏰ Scheduled jobs registered successfully.");
}
