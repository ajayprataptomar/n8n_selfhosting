import { Router, Request, Response } from 'express';
import multer from 'multer';
import { z } from 'zod';
import { RagService } from '../services/rag.service.js';

const router = Router();
const ALLOWED_EXTENSIONS = new Set(['pdf', 'txt', 'md', 'json', 'docx', 'csv', 'log']);
const ALLOWED_MIME_TYPES = new Set([
  'application/pdf',
  'text/plain',
  'text/markdown',
  'application/json',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
  'text/csv',
]);

const upload = multer({
  storage: multer.memoryStorage(),
  limits: {
    fileSize: 10 * 1024 * 1024, // 10MB limit
    files: 1,
    fields: 10,
  },
  fileFilter: (_req, file, cb) => {
    // CVE-2025-48997: Reject empty field names
    if (!file.fieldname || file.fieldname.trim() === '') {
      return cb(new Error('Invalid or empty field name'));
    }

    const ext = (file.originalname.split('.').pop() || '').toLowerCase();
    if (ALLOWED_EXTENSIONS.has(ext) || ALLOWED_MIME_TYPES.has(file.mimetype)) {
      cb(null, true);
    } else {
      cb(new Error(`Forbidden file type: .${ext} is not allowed. Whitelisted types: pdf, txt, md, json, docx`));
    }
  },
});

const crawlSchema = z.object({
  url: z.string().url(),
});

/**
 * Sanitize filename to prevent path traversal attacks (CVE-2026-3520 mitigation)
 */
function sanitizeFilename(filename: string): string {
  if (!filename) return 'unnamed_file';
  // Strip path separators, relative path markers, and null bytes
  return filename
    .replace(/[\0\r\n]/g, '')
    .replace(/(\.\.[\/\\])+/g, '')
    .replace(/[^a-zA-Z0-9_\-\.]/g, '_')
    .trim();
}

// POST /documents/upload - Upload file and parse/index for RAG search
router.post(
  '/upload',
  (req: Request, res: Response, next) => {
    upload.single('file')(req, res, (err: any) => {
      if (err) {
        if (err.code === 'LIMIT_FILE_SIZE') {
          return res.status(400).json({ error: 'File size exceeds maximum limit of 10MB' });
        }
        return res.status(400).json({ error: err.message || 'File upload validation failed' });
      }
      next();
    });
  },
  async (req: Request, res: Response) => {
    try {
      if (!req.file) {
        return res.status(400).json({ error: 'No file uploaded' });
      }

      const sanitizedName = sanitizeFilename(req.file.originalname);
      const { buffer } = req.file;
      const extension = (sanitizedName.split('.').pop() || '').toLowerCase();
      const tenantId = (req as any).tenantId || (req as any).tenant?.id || (req.headers['x-tenant-id'] as string);
      const userId = (req as any).userId || (req.headers['x-user-id'] as string);

      if (!tenantId || !userId) {
        return res.status(401).json({ error: 'Unauthorized: Missing tenant or user context' });
      }

      const chunks = await RagService.indexFile(
        tenantId,
        userId,
        sanitizedName,
        buffer,
        extension
      );

      return res.status(200).json({
        message: `File ${sanitizedName} parsed and indexed successfully.`,
        chunksCreated: chunks,
        status: 'INDEXED',
      });
    } catch (error: any) {
      return res.status(500).json({ error: error.message });
    }
  }
);

// POST /documents/crawl - Crawl web page and index contents
router.post('/crawl', async (req: Request, res: Response) => {
  try {
    const check = crawlSchema.safeParse(req.body);
    if (!check.success) {
      return res.status(400).json({ error: 'Invalid URL format' });
    }

    const { url } = check.data;
    const tenantId = (req as any).tenantId || (req as any).tenant?.id || (req.headers['x-tenant-id'] as string);
    const userId = (req as any).userId || (req.headers['x-user-id'] as string);

    if (!tenantId || !userId) {
      return res.status(401).json({ error: 'Unauthorized: Missing tenant or user context' });
    }

    const chunks = await RagService.indexUrl(tenantId, userId, url);

    return res.status(200).json({
      message: `Website content from ${url} crawled and indexed.`,
      chunksCreated: chunks,
      status: 'INDEXED',
    });
  } catch (error: any) {
    return res.status(500).json({ error: error.message });
  }
});

export default router;
