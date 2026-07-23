import fs from 'fs';
import path from 'path';
import pg from 'pg';
import { pool } from '../../db/pool.js';
import { SYSTEM_PROMPT } from '../../prompts/system-prompt.js';
import { DEVELOPER_PROMPT } from '../../prompts/developer-prompt.js';
import { MemoryRetriever } from '../memory/retriever.js';
import { VectorSearchService } from '../search/vector-search.js';
import { TokenBudgetManager } from './token-budget.js';
import { OllamaClient } from '../../llm/ollama.js';

export interface ContextBuildResult {
  finalPrompt: string;
  tokenBreakdown: {
    systemPrompt: number;
    developerPromptRules: number;
    memories: number;
    codeChunks: number;
    history: number;
    currentFile: number;
    userPrompt: number;
    total: number;
  };
  contextSources: {
    memories: string[];
    codeFiles: string[];
    workspaceRulesLoaded: boolean;
    historySummarized: boolean;
  };
}

export class ContextBuilder {
  private static async executeQuery<T>(
    tenantId: string,
    callback: (client: pg.PoolClient) => Promise<T>
  ): Promise<T> {
    const client = await pool.connect();
    try {
      await client.query('SELECT set_tenant_context($1)', [tenantId]);
      const result = await callback(client);
      await client.query('SELECT set_tenant_context(NULL)');
      return result;
    } catch (err) {
      try {
        await client.query('SELECT set_tenant_context(NULL)');
      } catch (err: any) {
        console.warn('Failed to clear tenant context in catch fallback:', err.message);
      }
      throw err;
    } finally {
      client.release();
    }
  }

  static async build(
    tenantId: string,
    userId: string,
    userPrompt: string,
    conversationId: string | null,
    workspacePath: string,
    currentFilePath?: string,
    cursorPosition?: number, // index in string character-wise
    contextWindow = 128000
  ): Promise<ContextBuildResult> {
    const budgets = TokenBudgetManager.getBudgets(contextWindow);
    const contextSources = {
      memories: [] as string[],
      codeFiles: [] as string[],
      workspaceRulesLoaded: false,
      historySummarized: false,
    };

    // 1. System Prompt
    const systemPromptText = SYSTEM_PROMPT;
    const systemTokens = TokenBudgetManager.estimateTokens(systemPromptText);

    // 2. Developer Prompt + Workspace Rules
    let rulesText = '';
    if (workspacePath) {
      const rulePath = path.join(
        path.resolve(workspacePath),
        '.harikson',
        'rules.md'
      );
      if (fs.existsSync(rulePath)) {
        try {
          rulesText = fs.readFileSync(rulePath, 'utf-8');
          contextSources.workspaceRulesLoaded = true;
        } catch (err: any) {
          console.warn(
            `Warning reading workspace rules at ${rulePath}:`,
            err.message
          );
        }
      }
    }
    const devRulesCombined = `${DEVELOPER_PROMPT}\n\n${rulesText ? `[Workspace Rules]:\n${rulesText}` : ''}`;
    const devRulesText = TokenBudgetManager.truncateFromEnd(
      devRulesCombined,
      budgets.developerPromptRules
    );
    const devRulesTokens = TokenBudgetManager.estimateTokens(devRulesText);

    // 3. User Prompt
    const userPromptText = TokenBudgetManager.truncateFromEnd(
      userPrompt,
      budgets.userPrompt
    );
    const userPromptTokens = TokenBudgetManager.estimateTokens(userPromptText);

    // 4. Retrieve Memories (2k budget)
    let memoriesText = '';
    try {
      const memories = await MemoryRetriever.retrieve(
        tenantId,
        userId,
        userPromptText,
        5
      );
      if (memories.length > 0) {
        memoriesText =
          '[Relevant Memories]:\n' +
          memories
            .map((m) => {
              contextSources.memories.push(m.memory);
              return `- ${m.memory}`;
            })
            .join('\n');
      }
    } catch (err: any) {
      console.warn('Warning retrieving memories for context:', err.message);
    }
    memoriesText = TokenBudgetManager.truncateFromEnd(
      memoriesText,
      budgets.memories
    );
    const memoriesTokens = TokenBudgetManager.estimateTokens(memoriesText);

    // 5. Retrieve Code Chunks (40k budget)
    let codeChunksText = '';
    try {
      const { results } = await VectorSearchService.search(
        userPromptText,
        tenantId,
        { code_only: true },
        20,
        0
      );
      if (results.length > 0) {
        codeChunksText =
          '[Relevant Code Chunks]:\n' +
          results
            .map((r) => {
              contextSources.codeFiles.push(r.source_path);
              return `--- File: ${r.source_path} ---\n${r.content}`;
            })
            .join('\n\n');
      }
    } catch (err: any) {
      console.warn(
        'Warning performing vector search for context:',
        err.message
      );
    }
    codeChunksText = TokenBudgetManager.truncateFromEnd(
      codeChunksText,
      budgets.codeChunks
    );
    const codeChunksTokens = TokenBudgetManager.estimateTokens(codeChunksText);

    // 6. Current File Context
    let currentFileText = '';
    if (currentFilePath && workspacePath) {
      const absolutePath = path.resolve(workspacePath, currentFilePath);
      if (fs.existsSync(absolutePath)) {
        try {
          const fileContent = fs.readFileSync(absolutePath, 'utf-8');
          // If cursor position is provided, grab ~150 lines around it, otherwise read whole file
          if (cursorPosition !== undefined) {
            const startIdx = Math.max(0, cursorPosition - 4000);
            const endIdx = Math.min(fileContent.length, cursorPosition + 4000);
            currentFileText = `[Current Open File Context: ${currentFilePath}]:\n${fileContent.substring(startIdx, endIdx)}`;
          } else {
            currentFileText = `[Current Open File Context: ${currentFilePath}]:\n${fileContent}`;
          }
        } catch (err: any) {
          console.warn(
            `Warning reading open file context at ${absolutePath}:`,
            err.message
          );
        }
      }
    }
    // Cap open file context at remaining budget space or ~4000 tokens maximum
    currentFileText = TokenBudgetManager.truncateFromEnd(currentFileText, 4000);
    const currentFileTokens =
      TokenBudgetManager.estimateTokens(currentFileText);

    // 7. Conversation History & Summarization
    let historyText = '';
    if (conversationId) {
      try {
        const messages = await this.executeQuery(tenantId, async (client) => {
          const res = await client.query(
            'SELECT id, role, content FROM messages WHERE conversation_id = $1 ORDER BY created_at ASC',
            [conversationId]
          );
          return res.rows as { id: string; role: string; content: string }[];
        });

        const historyEstimate = messages
          .map((m) => `${m.role}: ${m.content}`)
          .join('\n');
        const historyTokens =
          TokenBudgetManager.estimateTokens(historyEstimate);
        const triggerLimit = budgets.history * 0.6; // 60% budget limit

        if (historyTokens > triggerLimit && messages.length > 20) {
          // Trigger summarization on older messages (keeping last 20 messages intact)
          const messagesToSummarize = messages.slice(0, messages.length - 20);
          const messagesToKeep = messages.slice(messages.length - 20);

          const rawSummaryPrompt = messagesToSummarize
            .map(
              (m) => `${m.role === 'user' ? 'User' : 'Assistant'}: ${m.content}`
            )
            .join('\n');

          console.log(
            `🧠 [Harikson Context] Summarization triggered. Processing ${messagesToSummarize.length} old messages...`
          );

          const summarySystemPrompt =
            'You are the Harikson Conversation Summarizer. Condense the conversation history into a 1-2 paragraph summary capturing all names, stack details, preferences, and important facts.';
          const summaryText = await OllamaClient.generate(
            `Summarize this chat:\n\n${rawSummaryPrompt}`,
            summarySystemPrompt
          );

          // Save summary in database
          await this.executeQuery(tenantId, async (client) => {
            const range = {
              firstMessageId: messagesToSummarize[0].id,
              lastMessageId:
                messagesToSummarize[messagesToSummarize.length - 1].id,
            };
            await client.query(
              `INSERT INTO conversation_summaries (tenant_id, conversation_id, summary, message_range)
               VALUES ($1, $2, $3, $4)`,
              [tenantId, conversationId, summaryText, JSON.stringify(range)]
            );
          });

          contextSources.historySummarized = true;

          // Build history with summary + last 20 messages
          historyText =
            `[Conversation History Summary]:\n${summaryText}\n\n[Recent Conversation History]:\n` +
            messagesToKeep
              .map(
                (m) =>
                  `${m.role === 'user' ? 'User' : 'Assistant'}: ${m.content}`
              )
              .join('\n');
        } else {
          // Retrieve latest summary if any exists to prepend to messages
          const latestSummary = await this.executeQuery(
            tenantId,
            async (client) => {
              const res = await client.query(
                'SELECT summary FROM conversation_summaries WHERE conversation_id = $1 ORDER BY created_at DESC LIMIT 1',
                [conversationId]
              );
              return res.rows[0]?.summary as string | undefined;
            }
          );

          historyText =
            (latestSummary
              ? `[Conversation History Summary]:\n${latestSummary}\n\n`
              : '') +
            '[Conversation History]:\n' +
            messages
              .map(
                (m) =>
                  `${m.role === 'user' ? 'User' : 'Assistant'}: ${m.content}`
              )
              .join('\n');
        }
      } catch (err) {
        console.error(
          '⚠️ [Harikson Context] History retrieval / summary failed:',
          err
        );
      }
    }

    historyText = TokenBudgetManager.truncateFromStart(
      historyText,
      budgets.history
    );
    const historyTokens = TokenBudgetManager.estimateTokens(historyText);

    // 8. Assemble final prompt in strict order
    const finalPrompt = [
      `System:\n${systemPromptText}`,
      `Developer:\n${devRulesText}`,
      memoriesText,
      codeChunksText,
      historyText,
      currentFileText,
      `User:\n${userPromptText}`,
    ]
      .filter(Boolean)
      .join('\n\n');

    const total =
      systemTokens +
      devRulesTokens +
      memoriesTokens +
      codeChunksTokens +
      historyTokens +
      currentFileTokens +
      userPromptTokens;

    return {
      finalPrompt,
      tokenBreakdown: {
        systemPrompt: systemTokens,
        developerPromptRules: devRulesTokens,
        memories: memoriesTokens,
        codeChunks: codeChunksTokens,
        history: historyTokens,
        currentFile: currentFileTokens,
        userPrompt: userPromptTokens,
        total,
      },
      contextSources,
    };
  }
}
