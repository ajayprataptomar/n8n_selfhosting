import { encoding_for_model, TiktokenModel } from '@dqbd/tiktoken';
import { pool } from '../db/pool.js';
import { Logger } from '../observability/logger.js';

export interface TokenBreakdown {
  systemTokens: number;
  ragContextTokens: number;
  historyTokens: number;
  userTokens: number;
  promptTokens: number;
  completionTokens: number;
  contextTokens: number;
  totalTokens: number;
}

// Base system prompt tokens per model architecture
const MODEL_BASE_SYSTEM_TOKENS: Record<string, number> = {
  'qwen3-coder': 120,
  'qwen2.5-coder': 120,
  llama3: 100,
  mistral: 80,
  phi3: 75,
};

/**
 * Exact token count for text strings using tiktoken tokenizer (gpt-4 / cl100k_base).
 * Encodes input string and frees encoder memory allocation.
 */
export function countExactTokens(text: string, model: string = 'gpt-4'): number {
  if (!text || text.trim().length === 0) return 0;

  try {
    const validModel = (model as TiktokenModel) || 'gpt-4';
    const encoder = encoding_for_model(validModel);
    const tokens = encoder.encode(text);
    const count = tokens.length;
    encoder.free();
    return count;
  } catch (err) {
    try {
      const fallbackEncoder = encoding_for_model('gpt-4');
      const tokens = fallbackEncoder.encode(text);
      const count = tokens.length;
      fallbackEncoder.free();
      return count;
    } catch (fallbackErr) {
      return Math.ceil(text.trim().length / 4);
    }
  }
}

/**
 * Exact token count for text strings.
 */
export function estimateTokens(text: string): number {
  return countExactTokens(text, 'gpt-4');
}

/**
 * Calculate full prompt token breakdown before dispatching to LLM.
 */
export function calculatePromptBreakdown(
  modelName: string = 'qwen3-coder',
  systemPrompt: string = '',
  ragChunks: string[] = [],
  historyMessages: Array<{ role: string; content: string }> = [],
  userMessage: string = '',
  completionText: string = ''
): TokenBreakdown {
  const baseSystem = MODEL_BASE_SYSTEM_TOKENS[modelName.toLowerCase()] || 100;
  const systemTokens = baseSystem + estimateTokens(systemPrompt);

  const ragContextTokens = ragChunks.reduce(
    (sum, chunk) => sum + estimateTokens(chunk),
    0
  );

  const historyTokens = historyMessages.reduce(
    (sum, msg) => sum + estimateTokens(msg.content) + 4,
    0
  );

  const userTokens = estimateTokens(userMessage) + 4;

  const contextTokens = ragContextTokens + historyTokens;
  const promptTokens = systemTokens + contextTokens + userTokens;
  const completionTokens = estimateTokens(completionText);
  const totalTokens = promptTokens + completionTokens;

  return {
    systemTokens,
    ragContextTokens,
    historyTokens,
    userTokens,
    promptTokens,
    completionTokens,
    contextTokens,
    totalTokens,
  };
}

/**
 * Fetch token analytics by model (average tokens per request).
 */
export async function getModelTokenAnalytics(tenantId?: string) {
  let query = `
    SELECT 
      COALESCE(m.metadata->>'model', 'qwen3-coder') as model,
      COUNT(*)::int as request_count,
      ROUND(AVG(prompt_tokens))::int as avg_prompt_tokens,
      ROUND(AVG(completion_tokens))::int as avg_completion_tokens,
      ROUND(AVG(tokens_used))::int as avg_total_tokens,
      SUM(tokens_used)::bigint as total_tokens
    FROM messages m
  `;
  const params: any[] = [];
  if (tenantId) {
    query += ` WHERE m.tenant_id = $1`;
    params.push(tenantId);
  }
  query += ` GROUP BY 1 ORDER BY total_tokens DESC`;

  const result = await pool.query(query, params);
  return result.rows;
}

/**
 * Fetch monthly token usage per user.
 */
export async function getUserMonthlyTokenUsage(tenantId: string) {
  const query = `
    SELECT 
      u.id as user_id,
      u.email,
      u.name,
      DATE_TRUNC('month', m.created_at) as month,
      SUM(m.prompt_tokens)::bigint as prompt_tokens,
      SUM(m.completion_tokens)::bigint as completion_tokens,
      SUM(m.tokens_used)::bigint as total_tokens
    FROM messages m
    JOIN conversations c ON m.conversation_id = c.id
    JOIN users u ON c.user_id = u.id
    WHERE m.tenant_id = $1
    GROUP BY u.id, u.email, u.name, 4
    ORDER BY 4 DESC, total_tokens DESC
  `;
  const result = await pool.query(query, [tenantId]);
  return result.rows;
}

/**
 * Fetch token contributions per RAG document.
 */
export async function getRagDocumentTokenAnalytics(tenantId: string) {
  const query = `
    SELECT 
      kd.id as document_id,
      kd.filename,
      kd.file_type,
      COUNT(de.id)::int as chunk_count,
      COALESCE(SUM(LENGTH(de.content) / 4), 0)::bigint as estimated_tokens_contributed
    FROM knowledge_documents kd
    LEFT JOIN document_embeddings de ON de.knowledge_document_id = kd.id
    WHERE kd.tenant_id = $1
    GROUP BY kd.id, kd.filename, kd.file_type
    ORDER BY estimated_tokens_contributed DESC
  `;
  const result = await pool.query(query, [tenantId]);
  return result.rows;
}
