import { Worker } from "bullmq";
import { bullConnection } from "../lib/redis.js";
import { prisma } from "../lib/prisma.js";
import { InvoiceService } from "../services/invoice.service.js";

export const invoiceWorker = new Worker("invoice-tasks", async (job) => {
  console.log(`[Invoice Worker] Running job: ${job.name} (ID: ${job.id})`);

  if (job.name === "monthly-billing") {
    const activeUsers = await prisma.user.findMany({
      where: { status: "ACTIVE" },
    });

    console.log(`[Invoice Worker] Processing monthly billing for ${activeUsers.length} users.`);

    for (const user of activeUsers) {
      console.log(`[Invoice Worker] Generating invoice for user: ${user.email}...`);
      try {
        await InvoiceService.generateInvoice(user.id);
        console.log(`[Invoice Worker] Invoice generated & dispatched for ${user.email}.`);
      } catch (err) {
        console.error(`❌ [Invoice Worker] Failed to process billing for ${user.email}:`, err);
      }
    }
  }
}, { connection: bullConnection });

console.log("🟢 Invoice worker initialized.");
