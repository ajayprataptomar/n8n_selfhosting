import { Router, Request, Response } from 'express';
import { z } from 'zod';
import { VectorSearchService } from '../../services/search/vector-search.js';

const router = Router();

const searchSchema = z.object({
  query: z.string().min(1),
  topK: z.number().optional(),
  filters: z
    .object({
      file_types: z.array(z.string()).optional(),
      memory_only: z.boolean().optional(),
      code_only: z.boolean().optional(),
      user_id: z.string().optional(),
    })
    .optional(),
});

// Helper to map and brand search results
const mapResults = (results: any[]) => {
  return results.map((r) => ({
    type: r.type,
    content: r.content,
    source: r.source_path,
    score: r.similarity_score,
    hariksonSourceType: r.type,
    hariksonContent: r.content,
    hariksonSource: r.source_path,
    hariksonScore: r.similarity_score,
  }));
};

// POST /search - Main unified semantic search route
router.post('/', async (req: Request, res: Response) => {
  try {
    const check = searchSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({
        hariksonError:
          "Invalid request payload. 'query' parameter is required.",
      });
    }

    const { query, topK, filters } = check.data;
    const tenantId =
      (req.headers['x-tenant-id'] as string) ||
      '00000000-0000-0000-0000-000000000000';

    const topKCode = topK || 20;
    const topKMemory = topK || 5;

    const { results, queryEmbeddingTimeMs } = await VectorSearchService.search(
      query,
      tenantId,
      filters,
      topKCode,
      topKMemory
    );

    const formatted = mapResults(results);

    return res.status(200).json({
      results: formatted,
      totalResults: formatted.length,
      queryEmbeddingTimeMs,
      hariksonResults: formatted,
      hariksonTotalResults: formatted.length,
      hariksonQueryEmbeddingTimeMs: queryEmbeddingTimeMs,
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

// POST /search/code-only - Convenience code filter endpoint
router.post('/code-only', async (req: Request, res: Response) => {
  try {
    const check = searchSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({
        hariksonError:
          "Invalid request payload. 'query' parameter is required.",
      });
    }

    const { query, topK, filters } = check.data;
    const tenantId =
      (req.headers['x-tenant-id'] as string) ||
      '00000000-0000-0000-0000-000000000000';

    // Set code_only to true
    const activeFilters = {
      ...filters,
      code_only: true,
    };

    const topKCode = topK || 20;

    const { results, queryEmbeddingTimeMs } = await VectorSearchService.search(
      query,
      tenantId,
      activeFilters,
      topKCode,
      0
    );

    const formatted = mapResults(results);

    return res.status(200).json({
      results: formatted,
      totalResults: formatted.length,
      queryEmbeddingTimeMs,
      hariksonResults: formatted,
      hariksonTotalResults: formatted.length,
      hariksonQueryEmbeddingTimeMs: queryEmbeddingTimeMs,
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

// POST /search/memory-only - Convenience memory filter endpoint
router.post('/memory-only', async (req: Request, res: Response) => {
  try {
    const check = searchSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({
        hariksonError:
          "Invalid request payload. 'query' parameter is required.",
      });
    }

    const { query, topK, filters } = check.data;
    const tenantId =
      (req.headers['x-tenant-id'] as string) ||
      '00000000-0000-0000-0000-000000000000';

    // Set memory_only to true
    const activeFilters = {
      ...filters,
      memory_only: true,
    };

    const topKMemory = topK || 5;

    const { results, queryEmbeddingTimeMs } = await VectorSearchService.search(
      query,
      tenantId,
      activeFilters,
      0,
      topKMemory
    );

    const formatted = mapResults(results);

    return res.status(200).json({
      results: formatted,
      totalResults: formatted.length,
      queryEmbeddingTimeMs,
      hariksonResults: formatted,
      hariksonTotalResults: formatted.length,
      hariksonQueryEmbeddingTimeMs: queryEmbeddingTimeMs,
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

export default router;
