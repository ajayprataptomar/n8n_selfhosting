import { Router, Request, Response } from 'express';
import { z } from 'zod';
import crypto from 'crypto';
import { RepositoryIndexer } from '../../services/indexer/repository-indexer.js';

const router = Router();

interface IndexJob {
  id: string;
  workspacePath: string;
  status: 'pending' | 'running' | 'completed' | 'failed';
  progress: number;
  totalFiles: number;
  processedFiles: number;
  chunksCreated: number;
  skippedFiles: number;
  error?: string;
}

// In-memory registry to hold active background tasks
const activeJobs = new Map<string, IndexJob>();

const indexSchema = z.object({
  workspacePath: z.string().min(1),
});

// POST /index - Trigger background indexing task
router.post('/index', async (req: Request, res: Response) => {
  try {
    const check = indexSchema.safeParse(req.body);
    if (!check.success) {
      return res
        .status(400)
        .json({ hariksonError: 'workspacePath field is required' });
    }

    const { workspacePath } = check.data;
    const tenantId =
      (req.headers['x-tenant-id'] as string) ||
      '00000000-0000-0000-0000-000000000000';

    const jobId = crypto.randomUUID();
    const job: IndexJob = {
      id: jobId,
      workspacePath,
      status: 'pending',
      progress: 0,
      totalFiles: 0,
      processedFiles: 0,
      chunksCreated: 0,
      skippedFiles: 0,
    };

    activeJobs.set(jobId, job);

    // Run indexing asynchronously (fire-and-forget in the background)
    RepositoryIndexer.indexWorkspace(tenantId, workspacePath, (progress) => {
      const currentJob = activeJobs.get(jobId);
      if (!currentJob) return;

      currentJob.totalFiles = progress.totalFiles;
      currentJob.processedFiles = progress.processedFiles;
      currentJob.chunksCreated = progress.chunksCreated;
      currentJob.skippedFiles = progress.skippedFiles;

      if (progress.phase === 'scanning') {
        currentJob.status = 'running';
        currentJob.progress = 5; // Scan started
      } else if (progress.phase === 'indexing') {
        currentJob.status = 'running';
        const percent =
          progress.totalFiles > 0
            ? Math.round((progress.processedFiles / progress.totalFiles) * 85)
            : 0;
        currentJob.progress = 5 + percent; // Allocate 5%-90% for processing files
      } else if (progress.phase === 'cleanup') {
        currentJob.status = 'running';
        currentJob.progress = 95;
      } else if (progress.phase === 'done') {
        currentJob.status = 'completed';
        currentJob.progress = 100;
      }
    }).catch((err) => {
      const currentJob = activeJobs.get(jobId);
      if (currentJob) {
        currentJob.status = 'failed';
        currentJob.error = err.message || 'Failed workspace indexing run';
      }
    });

    return res.status(202).json({
      hariksonJobId: jobId,
      hariksonStatus: 'pending',
      hariksonMessage: 'Workspace vector indexing initiated in background.',
    });
  } catch (error: any) {
    return res.status(500).json({ hariksonError: error.message });
  }
});

// GET /index/status/:jobId - Poll active job status
router.get('/index/status/:jobId', async (req: Request, res: Response) => {
  const { jobId } = req.params;
  const job = activeJobs.get(jobId);

  if (!job) {
    return res.status(404).json({
      hariksonSuccess: false,
      hariksonMessage: 'Index job not found in workspace registry.',
    });
  }

  return res.status(200).json({
    hariksonJobId: job.id,
    hariksonStatus: job.status,
    hariksonProgress: `${job.progress}%`,
    hariksonTotalFiles: job.totalFiles,
    hariksonProcessedFiles: job.processedFiles,
    hariksonChunksCreated: job.chunksCreated,
    hariksonSkippedFiles: job.skippedFiles,
    hariksonError: job.error,
  });
});

export default router;
