import { Worker } from "bullmq";
import { bullConnection } from "../lib/redis.js";
import { prisma } from "../lib/prisma.js";

export const backupWorker = new Worker("backup-tasks", async (job) => {
  console.log(`[Backup Worker] Running job: ${job.name} (ID: ${job.id})`);

  if (job.name === "daily-backup") {
    const activeInstances = await prisma.instance.findMany({
      where: { status: "RUNNING" },
    });

    console.log(`[Backup Worker] Found ${activeInstances.length} active instances to backup.`);

    for (const instance of activeInstances) {
      console.log(`[Backup Worker] Backing up volumes for ${instance.name} (${instance.domain})...`);
      
      // Simulate disk write / database backup
      await new Promise((resolve) => setTimeout(resolve, 500));

      await prisma.instance.update({
        where: { id: instance.id },
        data: { lastBackup: new Date() },
      });

      console.log(`[Backup Worker] Successfully completed backup for ${instance.name}.`);
    }
  }
}, { connection: bullConnection });

console.log("🟢 Backup worker initialized.");
