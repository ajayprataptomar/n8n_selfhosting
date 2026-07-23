import { pool } from '../../db/pool.js';
import pg from 'pg';

export interface Memory {
  id: string;
  tenant_id: string;
  user_id: string;
  memory: string;
  importance: number;
  created_at: Date;
  updated_at: Date;
  score?: number;
}

export class MemoryStore {
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
          'Warning clearing tenant context on query error in MemoryStore:',
          cleanupErr.message
        );
      }
      throw err;
    } finally {
      client.release();
    }
  }

  static async save(
    tenantId: string,
    userId: string,
    memory: string,
    importance: number,
    embedding: number[]
  ): Promise<Memory> {
    return this.executeQuery(tenantId, async (client) => {
      const vectorStr = `[${embedding.join(',')}]`;
      const query = `
        INSERT INTO memories (tenant_id, user_id, memory, importance, embedding)
        VALUES ($1, $2, $3, $4, $5::vector)
        RETURNING id, tenant_id, user_id, memory, importance, created_at, updated_at
      `;
      const result = await client.query(query, [
        tenantId,
        userId,
        memory,
        importance,
        vectorStr,
      ]);
      return result.rows[0];
    });
  }

  static async searchVector(
    tenantId: string,
    userId: string,
    embedding: number[],
    limit = 5
  ): Promise<Memory[]> {
    return this.executeQuery(tenantId, async (client) => {
      const vectorStr = `[${embedding.join(',')}]`;
      const query = `
        SELECT id, tenant_id, user_id, memory, importance, created_at, updated_at,
               (1 - (embedding <=> $1::vector))::float as score
        FROM memories
        WHERE user_id = $2
        ORDER BY embedding <=> $1::vector ASC
        LIMIT $3
      `;
      const result = await client.query(query, [vectorStr, userId, limit]);
      return result.rows;
    });
  }

  static async searchKeyword(
    tenantId: string,
    userId: string,
    keywords: string[],
    limit = 5
  ): Promise<Memory[]> {
    return this.executeQuery(tenantId, async (client) => {
      if (keywords.length === 0) return [];
      const clauses = keywords
        .map((_, idx) => `memory ILIKE $${idx + 2}`)
        .join(' OR ');
      const query = `
        SELECT id, tenant_id, user_id, memory, importance, created_at, updated_at,
               0.5::float as score
        FROM memories
        WHERE user_id = $1 AND (${clauses})
        LIMIT $${keywords.length + 2}
      `;
      const params = [userId, ...keywords.map((kw) => `%${kw}%`)];
      const result = await client.query(query, params);
      return result.rows;
    });
  }

  static async list(tenantId: string, userId: string): Promise<Memory[]> {
    return this.executeQuery(tenantId, async (client) => {
      const query = `
        SELECT id, tenant_id, user_id, memory, importance, created_at, updated_at
        FROM memories
        WHERE user_id = $1
        ORDER BY created_at DESC
      `;
      const result = await client.query(query, [userId]);
      return result.rows;
    });
  }

  static async delete(
    tenantId: string,
    userId: string,
    id: string
  ): Promise<boolean> {
    return this.executeQuery(tenantId, async (client) => {
      const query = `
        DELETE FROM memories
        WHERE id = $1 AND user_id = $2
        RETURNING id
      `;
      const result = await client.query(query, [id, userId]);
      return result.rowCount ? result.rowCount > 0 : false;
    });
  }
}
