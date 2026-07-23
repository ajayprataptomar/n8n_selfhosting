import { countExactTokens } from '../tokenCountingService.js';

export interface BudgetAllocation {
  systemPrompt: number; // 4k (fixed)
  developerPromptRules: number; // 4k (fixed)
  memories: number; // 2k (variable max)
  codeChunks: number; // 40k (variable max)
  userPrompt: number; // 2k (fixed)
  toolBuffer: number; // 4k (fixed)
  history: number; // Remaining (variable max)
}

export class TokenBudgetManager {
  private static DEFAULT_CONTEXT_WINDOW = 128000; // 128k tokens

  static estimateTokens(text: string): number {
    if (!text) return 0;
    return countExactTokens(text);
  }

  static getBudgets(
    contextWindow = this.DEFAULT_CONTEXT_WINDOW
  ): BudgetAllocation {
    const systemPrompt = 4000;
    const developerPromptRules = 4000;
    const memories = 2000;
    const codeChunks = 40000;
    const userPrompt = 2000;
    const toolBuffer = 4000;

    // Remaining tokens go to conversation history
    const fixedReserve =
      systemPrompt +
      developerPromptRules +
      memories +
      codeChunks +
      userPrompt +
      toolBuffer;
    const history = Math.max(0, contextWindow - fixedReserve);

    return {
      systemPrompt,
      developerPromptRules,
      memories,
      codeChunks,
      userPrompt,
      toolBuffer,
      history,
    };
  }

  static truncateFromStart(text: string, maxTokens: number): string {
    const tokens = this.estimateTokens(text);
    if (tokens <= maxTokens) return text;
    // Truncate from the start (useful to keep the tail/end of conversation history)
    const allowedChars = maxTokens * 4;
    return text.substring(text.length - allowedChars);
  }

  static truncateFromEnd(text: string, maxTokens: number): string {
    const tokens = this.estimateTokens(text);
    if (tokens <= maxTokens) return text;
    // Truncate from the end (useful for documents or rules)
    const allowedChars = maxTokens * 4;
    return text.substring(0, allowedChars);
  }
}
