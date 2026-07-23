import { Redis } from 'ioredis';
import { OllamaClient } from '../../llm/ollama.js';
import { MemoryStore } from './store.js';

const redis = new Redis(process.env.REDIS_URL || 'redis://localhost:6379', {
  maxRetriesPerRequest: 3,
});

let totalProcessedCount = 0;
let lastHourProcessedCount = 0;
let metricsInterval: NodeJS.Timeout | null = null;

export class MemoryExtractor {
  private static redisClient = redis;

  /**
   * Check if a message ID has already been processed using Redis Set (SISMEMBER).
   */
  static async isProcessed(tenantId: string, messageId: string): Promise<boolean> {
    try {
      const key = `memory:processed:${tenantId}`;
      const exists = await this.redisClient.sismember(key, messageId);
      return exists === 1;
    } catch (err) {
      console.warn('Redis deduplication check error:', err);
      return false;
    }
  }

  /**
   * Mark a message ID as processed in Redis Set (SADD) with 7-day TTL (604,800 seconds).
   */
  static async markProcessed(tenantId: string, messageId: string): Promise<void> {
    try {
      const key = `memory:processed:${tenantId}`;
      const pipeline = this.redisClient.pipeline();
      pipeline.sadd(key, messageId);
      pipeline.expire(key, 7 * 86400); // 7-day TTL auto-expire
      await pipeline.exec();

      totalProcessedCount++;

      // Monitor set size for alert
      const setSize = await this.redisClient.scard(key);
      if (setSize > 100000) {
        console.warn(
          `🚨 [ALERT] Memory Extractor processed set for tenant ${tenantId} exceeds 100,000 entries (Current: ${setSize})`
        );
      }
    } catch (err) {
      console.error('Failed to mark message as processed in Redis:', err);
    }
  }

  /**
   * Retrieve deduplication and processing metrics.
   */
  static async getMetrics(tenantId = 'system'): Promise<{
    processedSetSize: number;
    totalProcessedCount: number;
    processingRatePerMin: number;
  }> {
    try {
      const key = `memory:processed:${tenantId}`;
      const setSize = await this.redisClient.scard(key);
      return {
        processedSetSize: setSize,
        totalProcessedCount,
        processingRatePerMin: Math.round((totalProcessedCount - lastHourProcessedCount) / 60),
      };
    } catch (err) {
      return {
        processedSetSize: 0,
        totalProcessedCount,
        processingRatePerMin: 0,
      };
    }
  }

  static async extractAndSave(
    tenantId: string,
    userId: string,
    message: string,
    conversationContextText: string,
    messageId?: string
  ): Promise<void> {
    if (messageId && (await this.isProcessed(tenantId, messageId))) {
      console.log(`🧠 [Harikson Memory] Skipping already processed message ${messageId}`);
      return;
    }

    try {
      const systemPrompt = `You are the Harikson Memory Service. Your job is to extract important, permanent facts about the user (their preferences, their tech stack, their name, their project, their company) from the user's message.
Do NOT extract transient information like greetings, temporary states, weather, or conversational filler.
Always write extracted facts in the third person (e.g., 'User's company uses Laravel' instead of 'My company uses Laravel').
Rate the importance on a scale of 0.0 to 1.0.

Respond ONLY with a JSON object. No other text, no markdown backticks. Example:
{"should_remember": true, "fact": "User's company uses Laravel", "importance": 0.9}
or
{"should_remember": false, "fact": "", "importance": 0.0}`;

      const prompt = `User Message: "${message}"\nContext:\n${conversationContextText}`;
      const response = await OllamaClient.generate(prompt, systemPrompt);

      let text = response.trim();
      if (text.startsWith('```')) {
        text = text
          .replace(/```[a-z]*\n?/gi, '')
          .replace(/```/g, '')
          .trim();
      }

      const parsed = JSON.parse(text) as {
        should_remember: boolean;
        fact: string;
        importance: number;
      };

      if (parsed.should_remember && parsed.fact && parsed.fact.trim()) {
        console.log(
          `🧠 [Harikson Memory] Extracted fact: "${parsed.fact}" (Importance: ${parsed.importance})`
        );
        const embedding = await OllamaClient.embed(parsed.fact);
        await MemoryStore.save(
          tenantId,
          userId,
          parsed.fact.trim(),
          parsed.importance,
          embedding
        );
      } else {
        console.log(
          `🧠 [Harikson Memory] Message not deemed worthy of permanent memory.`
        );
      }

      if (messageId) {
        await this.markProcessed(tenantId, messageId);
      }
    } catch (error) {
      console.error('❌ [Harikson Memory] Extraction failed:', error);
    }
  }
}

// Hourly memory usage monitoring & Set size log
if (!metricsInterval) {
  metricsInterval = setInterval(async () => {
    try {
      const key = `memory:processed:system`;
      const size = await redis.scard(key);
      console.log(
        `📊 [Memory Extractor Monitor] Redis Processed Set size: ${size} entries. Total processed since boot: ${totalProcessedCount}`
      );
      lastHourProcessedCount = totalProcessedCount;
    } catch (e) {
      // Ignored
    }
  }, 3600000);
}
