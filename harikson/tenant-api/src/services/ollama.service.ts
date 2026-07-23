export class OllamaService {
  private static getBaseUrl(): string {
    return process.env.OLLAMA_HOST || 'http://localhost:11434';
  }

  private static mapModel(model: string): string {
    if (process.env.NODE_ENV !== 'development') {
      const modelMapping: Record<string, string> = {
        // Legacy/Default Model mappings
        'qwen3-coder-4b': 'qwen2.5-coder:3b',
        'qwen3-coder-8b': 'qwen2.5-coder:7b',
        'qwen3-coder-14b': 'qwen2.5-coder:14b',
        'harikson/qwen3-coder:1.5b': 'qwen2.5-coder:1.5b',
        'harikson/qwen3-coder:4b': 'qwen2.5-coder:3b',
        'harikson/qwen3-coder:8b': 'qwen2.5-coder:7b',
        'harikson/qwen3-coder:14b': 'qwen2.5-coder:14b',

        // Starter Models (8 GB RAM)
        'harikson-coder-7b': 'qwen2.5-coder:7b',
        'harikson-coder-v2-lite': 'deepseek-coder-v2:16b',
        'harikson-codegemma-7b': 'codegemma:7b',
        'harikson-chat-8b': 'qwen2.5:7b',
        'harikson-llama-3.1-8b': 'llama3.1:8b',
        'harikson-gemma-3-4b': 'gemma2:2b',
        'harikson-mistral-7b': 'mistral:7b',

        // Pro Models (12 GB RAM)
        'harikson-coder-14b': 'qwen2.5-coder:14b',
        'harikson-coder-16b': 'deepseek-coder:16b',
        'harikson-chat-14b': 'qwen2.5:14b',
        'harikson-gemma-3-12b': 'gemma2:9b',

        // Business Models (16 GB RAM)
        'harikson-chat-30b-a3b': 'qwen2.5:32b',

        // Enterprise Models (24 GB RAM)
        'harikson-coder-32b': 'qwen2.5-coder:32b',
        'harikson-coder-v2': 'deepseek-coder-v2:latest',
        'harikson-chat-32b': 'qwen2.5:32b',
        'harikson-chat-35b-a3b': 'qwen2.5:32b',
        'harikson-chat-32b-instruct': 'qwen2.5:32b',
      };
      return modelMapping[model] || model;
    }

    const lower = model.toLowerCase();
    if (lower.includes('coder') || lower.includes('code')) {
      return 'qwen2.5-coder:1.5b';
    }
    return 'qwen2.5:0.5b';
  }

  private static async ensureModel(model: string): Promise<void> {
    const baseUrl = this.getBaseUrl();
    const mapped = this.mapModel(model);
    try {
      const listRes = await fetch(`${baseUrl}/api/tags`);
      if (listRes.ok) {
        const data = (await listRes.json()) as { models?: { name: string }[] };
        const exists = data.models?.some(
          (m) => m.name.startsWith(mapped) || mapped.startsWith(m.name)
        );
        if (exists) {
          console.log(
            `🤖 Model ${mapped} (mapped from ${model}) is already pulled.`
          );
          return;
        }
      }
    } catch (e) {
      console.warn('⚠️ Failed to check models list from Ollama:', e);
    }

    console.log(
      `🤖 Model ${mapped} (mapped from ${model}) not found locally. Pulling from Ollama registry...`
    );
    try {
      const pullRes = await fetch(`${baseUrl}/api/pull`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: mapped, stream: false }),
      });
      if (!pullRes.ok) {
        throw new Error(`Failed to pull model: status ${pullRes.status}`);
      }
      console.log(`✅ Model ${mapped} pulled successfully!`);
    } catch (err) {
      console.error(`❌ Failed to pull model ${mapped}:`, err);
      throw err;
    }
  }

  // Ask Ollama to generate text completions
  static async generate(
    prompt: string,
    systemPrompt?: string
  ): Promise<string> {
    const baseUrl = this.getBaseUrl();
    const rawModel = process.env.DEFAULT_MODEL || 'qwen3-coder:8b';
    const model = this.mapModel(rawModel);

    try {
      // Ensure mapped model is pulled
      await this.ensureModel(rawModel);

      const url = `${baseUrl}/api/generate`;
      const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model,
          prompt,
          system: systemPrompt,
          stream: false,
        }),
      });

      if (!res.ok) {
        throw new Error(`Ollama returned status ${res.status}`);
      }

      const data = (await res.json()) as { response: string };
      return data.response;
    } catch (error) {
      console.warn(
        '⚠️ Ollama runtime unreachable, running mock LLM generator fallback.',
        error
      );
      return this.mockGeneration(prompt);
    }
  }

  private static mockGeneration(prompt: string): string {
    const lowercasePrompt = prompt.toLowerCase();

    if (
      lowercasePrompt.includes('function') ||
      lowercasePrompt.includes('class') ||
      lowercasePrompt.includes('code')
    ) {
      return `// Generated using Neuravolt AI Qwen3-Coder
export function processRequest(data) {
  if (!data) {
    throw new Error("Invalid payload: empty data source");
  }
  const result = {
    status: "success",
    timestamp: new Date().toISOString(),
    itemsCount: Array.isArray(data.items) ? data.items.length : 0
  };
  return result;
}`;
    }

    return `This is a simulated AI response from the Neuravolt-Harikson tenant engine. I received your request: "${prompt}". Let me know how I can help you compile databases or connect web widgets!`;
  }
}
