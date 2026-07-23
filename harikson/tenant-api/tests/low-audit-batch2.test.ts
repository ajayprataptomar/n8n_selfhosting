import { describe, it, expect } from '@jest/globals';
import { RagService } from '../src/services/rag.service.js';

describe('LOW-035, LOW-036, LOW-038, LOW-040, LOW-046 Performance & Search Test Suite', () => {
  const tenantId = '00000000-0000-0000-0000-000000000000';

  it('1. LOW-046: Executes hybrid search with combined vector and text rank scores', async () => {
    const result = await RagService.queryContext(tenantId, 'security compliance and workflow automation', 3);
    expect(result).toBeDefined();
    expect(typeof result).toBe('string');
  });
});
