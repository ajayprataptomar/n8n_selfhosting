import { Redis } from "ioredis";
import type { ConnectionOptions } from "bullmq";

const redisUrl = process.env.REDIS_URL || "redis://localhost:6379";

export const redis = new Redis(redisUrl, {
  maxRetriesPerRequest: null, // Critical requirement for BullMQ
});

export const bullConnection = redis as unknown as ConnectionOptions;

export default redis;
