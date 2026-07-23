import { OllamaService } from '../services/ollama.service.js';

export class OllamaClient {
  static async generate(
    prompt: string,
    systemPrompt?: string
  ): Promise<string> {
    return OllamaService.generate(prompt, systemPrompt);
  }

  static async embed(text: string): Promise<number[]> {
    const baseUrl = process.env.OLLAMA_HOST || 'http://localhost:11434';
    const rawModel = process.env.DEFAULT_MODEL || 'qwen3-coder:8b';

    // Perform standard fallback model mapping
    const lower = rawModel.toLowerCase();
    let mappedModel = 'qwen2.5:0.5b';
    if (lower.includes('coder') || lower.includes('code')) {
      mappedModel = 'qwen2.5-coder:1.5b';
    }
    if (process.env.NODE_ENV !== 'development') {
      mappedModel = 'qwen2.5-coder:7b';
    }

    try {
      const res = await fetch(`${baseUrl}/api/embeddings`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model: mappedModel,
          prompt: text,
        }),
      });

      if (!res.ok) {
        throw new Error(`Ollama embeddings returned status ${res.status}`);
      }

      const data = (await res.json()) as { embedding: number[] };
      let embedding = data.embedding || [];

      // Ensure length is exactly 1536 (pgvector target)
      if (embedding.length < 1536) {
        const pad = new Array(1536 - embedding.length).fill(0.0);
        embedding = embedding.concat(pad);
      } else if (embedding.length > 1536) {
        embedding = embedding.slice(0, 1536);
      }
      return embedding;
    } catch (error) {
      console.warn(
        '⚠️ Ollama embeddings error, returning fallback mock vector.',
        error
      );
      return this.generateMockEmbedding(text);
    }
  }

  private static generateMockEmbedding(text: string): number[] {
    const embedding = new Array(1536).fill(0.0);
    let hash = 0;
    for (let i = 0; i < text.length; i++) {
      hash = text.charCodeAt(i) + ((hash << 5) - hash);
    }
    for (let j = 0; j < 1536; j++) {
      embedding[j] = Math.sin(hash + j) * 0.1;
    }
    return embedding;
  }
}
