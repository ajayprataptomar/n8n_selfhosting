import { Router } from 'express';
import multer from 'multer';
import { executeTenantQuery } from '../db/pool.js';
import { RagService } from '../services/rag.service.js';
import { decryptDocumentContent } from '../services/documentEncryptionService.js';
import logger from '../utils/logger.js';

const router = Router();
const upload = multer({
  limits: { fileSize: 25 * 1024 * 1024 }, // 25MB max file size limit
});

// POST /api/documents/upload
router.post('/upload', upload.single('file'), async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  if (!req.file) return res.status(400).json({ error: 'No file uploaded' });

  const { originalname, buffer, mimetype } = req.file;
  const userId = req.user?.userId || '00000000-0000-0000-0000-000000000000';
  const fileExtension = originalname.split('.').pop() || mimetype.split('/')[1] || 'txt';

  try {
    const chunkCount = await RagService.indexFile(
      req.tenant.id,
      userId,
      originalname,
      buffer,
      fileExtension
    );

    res.status(201).json({
      success: true,
      message: `Document ${originalname} processed and indexed successfully.`,
      chunkCount,
    });
  } catch (err: any) {
    logger.error('Document upload error:', err);
    res.status(500).json({ error: err.message || 'Failed to process and index document' });
  }
});

// GET /api/documents
router.get('/', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });

  try {
    const docsRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `SELECT id, filename, file_type, file_size_bytes, status, is_active, created_at
         FROM knowledge_documents
         WHERE tenant_id = $1 AND is_active = true
         ORDER BY created_at DESC`,
        [req.tenant.id]
      )
    );

    res.json({ documents: docsRes.rows });
  } catch (err: any) {
    logger.error('Fetch documents error:', err);
    res.status(500).json({ error: 'Failed to fetch knowledge documents' });
  }
});

// DELETE /api/documents/:id
router.delete('/:id', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;

  try {
    await executeTenantQuery(req.tenant.id, async (client) => {
      await client.query(
        'UPDATE knowledge_documents SET is_active = false, updated_at = NOW() WHERE id = $1 AND tenant_id = $2',
        [id, req.tenant.id]
      );
      await client.query(
        'DELETE FROM document_embeddings WHERE knowledge_document_id = $1 AND tenant_id = $2',
        [id, req.tenant.id]
      );
    });

    res.json({ success: true, message: 'Document deleted successfully' });
  } catch (err: any) {
    logger.error('Delete document error:', err);
    res.status(500).json({ error: 'Failed to delete document' });
  }
});

// GET /api/documents/:id/download
router.get('/:id/download', async (req: any, res) => {
  if (!req.tenant) return res.status(401).json({ error: 'Tenant context required' });
  const { id } = req.params;

  try {
    const docRes = await executeTenantQuery(req.tenant.id, (client) =>
      client.query(
        `SELECT filename, file_type, content, content_iv, content_tag, key_id 
         FROM knowledge_documents 
         WHERE id = $1 AND tenant_id = $2 AND is_active = true`,
        [id, req.tenant.id]
      )
    );

    if (docRes.rows.length === 0) {
      return res.status(404).json({ error: 'Document not found' });
    }

    const doc = docRes.rows[0];
    let plainText = doc.content;

    if (doc.content_iv && doc.content_tag) {
      plainText = decryptDocumentContent(doc.id, doc.content, doc.content_iv, doc.content_tag, doc.key_id);
    }

    res.setHeader('Content-Type', 'text/plain; charset=utf-8');
    res.setHeader('Content-Disposition', `attachment; filename="${doc.filename}"`);
    res.send(plainText);
  } catch (err: any) {
    logger.error('Download document error:', err);
    res.status(500).json({ error: 'Failed to download document' });
  }
});

export default router;
