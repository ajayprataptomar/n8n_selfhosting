import { OllamaClient } from '../../llm/ollama.js';
import { MemoryStore, Memory } from './store.js';

export class MemoryRetriever {
  static async retrieve(
    tenantId: string,
    userId: string,
    message: string,
    limit = 5
  ): Promise<Memory[]> {
    try {
      console.log(
        `🧠 [Harikson Memory] Retrieving context for user message...`
      );
      const embedding = await OllamaClient.embed(message);

      let results = await MemoryStore.searchVector(
        tenantId,
        userId,
        embedding,
        limit
      );

      // Fallback: If vector search yields no hits, query by keyword matching
      if (results.length === 0) {
        console.log(
          `🧠 [Harikson Memory] No vector hits found. Attempting keyword search fallback...`
        );
        const keywords = message
          .toLowerCase()
          .replace(/[^\w\s]/g, '')
          .split(/\s+/)
          .filter((word) => word.length >= 4);

        if (keywords.length > 0) {
          results = await MemoryStore.searchKeyword(
            tenantId,
            userId,
            keywords,
            limit
          );
        }
      }

      console.log(
        `🧠 [Harikson Memory] Retrieved ${results.length} relevant memories.`
      );
      return results;
    } catch (error) {
      console.error('❌ [Harikson Memory] Retrieval failed:', error);
      return [];
    }
  }
}
