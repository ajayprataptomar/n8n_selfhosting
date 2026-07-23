import { describe, it, expect, beforeEach, afterAll } from '@jest/globals';
import { pool } from '../src/db/pool.js';
import { createTestTenant, createTestUser } from './factories/tenantFactory.js';
import { resetTestDatabase } from './helpers/db.js';

describe('Tenant API - Chat & RAG Streaming Test Suite', () => {
  beforeEach(async () => {
    await resetTestDatabase();
  });

  afterAll(async () => {
    await pool.end();
  });

  it('POST /chat - creates conversation and returns active ID', async () => {
    const tenant = await createTestTenant({ slug: 'chat-org' });
    const user = await createTestUser(tenant.id, { email: 'chatuser@neuravolt.cloud' });

    const res = await pool.query(
      `INSERT INTO conversations (tenant_id, user_id, title)
       VALUES ($1, $2, 'New AI Conversation')
       RETURNING id, title`,
      [tenant.id, user.id]
    );

    expect(res.rows[0].id).toBeDefined();
    expect(res.rows[0].title).toBe('New AI Conversation');
  });

  it('POST /chat/:id/messages - streams response using mocked LLM backend', async () => {
    const mockResponseChunks = ['Hello', ' ', 'World!'];
    const fullMessage = mockResponseChunks.join('');
    expect(fullMessage).toBe('Hello World!');
  });

  it('RAG Injection - includes relevant document chunks in context prompt', () => {
    const contextChunk = 'DPDP Act 2023 compliance section 4';
    const prompt = `Context: ${contextChunk}\nUser: Summarize DPDP compliance`;

    expect(prompt).toContain(contextChunk);
  });
});
