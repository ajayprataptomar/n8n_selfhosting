import { Worker } from "bullmq";
import { bullConnection } from "../lib/redis.js";
import { prisma } from "../lib/prisma.js";
import { DockerService } from "../services/docker.service.js";

export const monitorWorker = new Worker("monitor-tasks", async (job) => {
  console.log(`[Monitor Worker] Running job: ${job.name} (ID: ${job.id})`);

  if (job.name === "health-check") {
    const instances = await prisma.instance.findMany({
      where: { NOT: { containerId: null } },
    });

    console.log(`[Monitor Worker] Checking resource health of ${instances.length} containers.`);

    for (const instance of instances) {
      if (!instance.containerId) continue;
      
      try {
        const metrics = await DockerService.getMetrics(instance.containerId);
        
        // Cache stats in DB
        await prisma.instance.update({
          where: { id: instance.id },
          data: {
            cpuUsage: metrics.cpuUsage,
            memoryUsage: metrics.memoryUsage,
            diskUsage: metrics.diskUsage,
          },
        });

        // Trigger alert warnings in logs if resource utilization exceeds thresholds
        if (metrics.cpuUsage > 80.0) {
          console.warn(`🚨 [ALERT] High CPU usage detected on instance ${instance.name} (${instance.domain}): ${metrics.cpuUsage}%`);
        }
        if (metrics.memoryUsage > 900.0) { // e.g., > 90% of 1024m
          console.warn(`🚨 [ALERT] High Memory usage detected on instance ${instance.name} (${instance.domain}): ${metrics.memoryUsage} MB`);
        }
      } catch (err) {
        console.error(`❌ [Monitor Worker] Failed to grab metrics for container ${instance.containerId}:`, err);
      }
    }
  }
}, { connection: bullConnection });

console.log("🟢 Monitor worker initialized.");
