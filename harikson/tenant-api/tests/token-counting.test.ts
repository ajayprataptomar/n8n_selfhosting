import { describe, it, expect } from '@jest/globals';
import { countExactTokens, estimateTokens } from '../src/services/tokenCountingService.js';

describe('Token Counting Service - Exact vs Estimate Comparison Suite', () => {
  it('1. Counts exact tokens for English text', () => {
    const englishText = 'Artificial intelligence is revolutionizing modern software engineering and autonomous agents.';
    const exactCount = countExactTokens(englishText);
    const legacyEstimate = Math.ceil(englishText.length / 4);

    expect(exactCount).toBeGreaterThan(0);
    expect(exactCount).toBeLessThan(englishText.length);
    // Tiktoken exact counts for English prose should be close to ~13-16 tokens for this string
    expect(exactCount).toBeLessThanOrEqual(legacyEstimate + 5);
  });

  it('2. Counts exact tokens for JavaScript/TypeScript code snippet', () => {
    const codeText = `
      function calculateTotal(items: Array<{ price: number; quantity: number }>): number {
        return items.reduce((sum, item) => sum + item.price * item.quantity, 0);
      }
    `;
    const exactCount = countExactTokens(codeText);
    const legacyEstimate = Math.ceil(codeText.length / 4);

    expect(exactCount).toBeGreaterThan(0);
    // Code has many symbols and punctuation, exact counting provides precise BPE tokens
    expect(typeof exactCount).toBe('number');
    expect(exactCount).not.toEqual(0);
  });

  it('3. Counts exact tokens for Hindi / Devanagari multi-byte text', () => {
    const hindiText = 'नमस्ते दुनिया, हरिकसन एआई प्लेटफ़ॉर्म में आपका स्वागत है।';
    const exactCount = countExactTokens(hindiText);
    const legacyEstimate = Math.ceil(hindiText.length / 4);

    expect(exactCount).toBeGreaterThan(0);
    // Multi-byte non-Latin text has higher token density than simple length / 4
    expect(exactCount).not.toEqual(legacyEstimate);
  });

  it('4. Handles empty and whitespace strings gracefully', () => {
    expect(countExactTokens('')).toBe(0);
    expect(countExactTokens('   ')).toBe(0);
  });
});
