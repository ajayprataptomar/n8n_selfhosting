import pg from 'pg';
import { pool } from '../../db/pool.js';
import { ToolRegistry } from './registry.js';

export interface ToolCallResult {
  tool: string;
  result?: string;
  error?: string;
}

export class ToolExecutor {
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
      } catch (cleanupErr: any) {
        console.warn(
          'Warning clearing tenant context on query error in ToolExecutor:',
          cleanupErr.message
        );
      }
      throw err;
    } finally {
      client.release();
    }
  }

  // Parses XML tool call protocol tags from LLM responses
  static parseToolCalls(text: string): Array<{ name: string; params: any }> {
    const toolCallRegex =
      /<tool_call\s+name="([^"]+)">([\s\S]*?)<\/tool_call>/g;
    const paramRegex = /<param\s+name="([^"]+)">([\s\S]*?)<\/param>/g;
    const calls: Array<{ name: string; params: any }> = [];

    let match;
    while ((match = toolCallRegex.exec(text)) !== null) {
      const name = match[1];
      const paramsContent = match[2];
      const params: any = {};

      let paramMatch;
      const localParamRegex = new RegExp(paramRegex);
      while ((paramMatch = localParamRegex.exec(paramsContent)) !== null) {
        const paramName = paramMatch[1];
        const paramValue = paramMatch[2].trim();

        // Convert types dynamically
        if (/^\d+$/.test(paramValue)) {
          params[paramName] = parseInt(paramValue, 10);
        } else if (/^\d*\.\d+$/.test(paramValue)) {
          params[paramName] = parseFloat(paramValue);
        } else if (paramValue === 'true') {
          params[paramName] = true;
        } else if (paramValue === 'false') {
          params[paramName] = false;
        } else {
          params[paramName] = paramValue;
        }
      }

      calls.push({ name, params });
    }

    return calls;
  }

  // Executes a parsed tool call sequentially
  static async executeSingle(
    tenantId: string,
    conversationId: string,
    workspacePath: string,
    name: string,
    params?: any
  ): Promise<ToolCallResult> {
    const start = Date.now();
    const toolDef = ToolRegistry.get(name);
    const activeParams = params || {};

    if (!toolDef) {
      const elapsed = Date.now() - start;
      const errorMsg = `Tool "${name}" is not registered in Harikson framework.`;

      // Log failure in DB
      await this.logExecution(
        tenantId,
        conversationId,
        name,
        activeParams,
        errorMsg,
        'error',
        elapsed
      );
      return { tool: name, error: errorMsg };
    }

    try {
      // Validate required parameters
      for (const [paramName, paramSpec] of Object.entries(toolDef.parameters)) {
        if (
          paramSpec.required &&
          (activeParams[paramName] === undefined ||
            activeParams[paramName] === null)
        ) {
          throw new Error(`Missing required parameter: "${paramName}"`);
        }
      }

      console.log(
        `⚡ [Harikson Tool] Executing: ${name} (Params: ${JSON.stringify(activeParams)})`
      );
      const result = await toolDef.handler(workspacePath, activeParams);
      const elapsed = Date.now() - start;

      // Sanitize result string
      const sanitizedResult =
        typeof result === 'object' ? JSON.stringify(result) : String(result);

      // Log success in DB
      await this.logExecution(
        tenantId,
        conversationId,
        name,
        activeParams,
        sanitizedResult,
        'success',
        elapsed
      );
      return { tool: name, result: sanitizedResult };
    } catch (err: any) {
      const elapsed = Date.now() - start;
      const errorMsg = err.message || 'Unknown tool execution error.';

      // Log error in DB
      await this.logExecution(
        tenantId,
        conversationId,
        name,
        activeParams,
        errorMsg,
        'error',
        elapsed
      );
      return { tool: name, error: errorMsg };
    }
  }

  static async executeAll(
    tenantId: string,
    conversationId: string,
    workspacePath: string,
    toolCalls: Array<{ name: string; params?: any }>
  ): Promise<ToolCallResult[]> {
    const results: ToolCallResult[] = [];

    // Execute sequentially to avoid race conditions
    for (const call of toolCalls) {
      const result = await this.executeSingle(
        tenantId,
        conversationId,
        workspacePath,
        call.name,
        call.params
      );
      results.push(result);
    }
    return results;
  }

  private static async logExecution(
    tenantId: string,
    conversationId: string,
    toolName: string,
    params: any,
    result: string,
    status: 'success' | 'error',
    elapsedTimeMs: number
  ): Promise<void> {
    try {
      await this.executeQuery(tenantId, async (client) => {
        const query = `
          INSERT INTO tool_executions (tenant_id, conversation_id, tool_name, params, result, status, execution_time_ms)
          VALUES ($1, $2, $3, $4, $5, $6, $7)
        `;
        await client.query(query, [
          tenantId,
          conversationId,
          toolName,
          JSON.stringify(params),
          result.substring(0, 10000), // Cap logged outputs to protect db storage
          status,
          elapsedTimeMs,
        ]);
      });
    } catch (err) {
      console.error('⚠️ [Harikson Tool] Failed to save execution log:', err);
    }
  }
}
