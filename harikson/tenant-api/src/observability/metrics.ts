class MetricsStore {
  private tokensUsed = 0;
  private latencies: Record<string, number> = {};
  private qualityScoreSum = 0;
  private qualityScoreCount = 0;

  recordTokens(count: number) {
    this.tokensUsed += count;
  }

  recordLatency(operation: string, seconds: number) {
    this.latencies[operation] = seconds;
  }

  recordQuality(score: number) {
    this.qualityScoreSum += score;
    this.qualityScoreCount++;
  }

  getPrometheusFormat(): string {
    const averageQuality =
      this.qualityScoreCount > 0
        ? this.qualityScoreSum / this.qualityScoreCount
        : 1.0;

    let out = '';
    out += `# HELP harikson_token_usage_total Total tokens used by Harikson LLM calls\n`;
    out += `# TYPE harikson_token_usage_total counter\n`;
    out += `harikson_token_usage_total ${this.tokensUsed}\n\n`;

    out += `# HELP harikson_api_latency_seconds Latency of Harikson API operations\n`;
    out += `# TYPE harikson_api_latency_seconds gauge\n`;
    for (const [op, sec] of Object.entries(this.latencies)) {
      out += `harikson_api_latency_seconds{operation="${op}"} ${sec}\n`;
    }
    out += `\n`;

    out += `# HELP harikson_retrieval_quality Average score of retrieval quality evaluations\n`;
    out += `# TYPE harikson_retrieval_quality gauge\n`;
    out += `harikson_retrieval_quality ${averageQuality}\n`;

    return out;
  }
}

export const metrics = new MetricsStore();
export default metrics;
