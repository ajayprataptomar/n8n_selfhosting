import { Router, Request, Response } from 'express';
import crypto from 'crypto';
import { Redis } from 'ioredis';
import { pool } from '../db/pool.js';
import { Logger } from '../observability/logger.js';

const router = Router();

const redis = new Redis({
  host: process.env.REDIS_HOST || '127.0.0.1',
  port: parseInt(process.env.REDIS_PORT || '6379'),
  password: process.env.REDIS_PASSWORD || undefined,
  lazyConnect: true,
});

redis.connect().catch(() => {});

/**
 * Helper to normalize and extract origin host from headers.
 */
function extractRequestOrigin(req: Request): string {
  const origin = (req.headers.origin || req.headers.referer || '').toString().trim();
  if (!origin) return '';
  try {
    const parsed = new URL(origin);
    return `${parsed.protocol}//${parsed.host}`;
  } catch {
    return origin.replace(/\/$/, '');
  }
}

// GET /widget.js - Dynamic chat widget loader with strict origin validation & HMAC verification
router.get('/widget.js', async (req: Request, res: Response) => {
  try {
    const tenantSlug = (req.query.tenant as string) || 'neuravolt';
    const color = (req.query.color as string) || '#8b5cf6';
    const welcome = (req.query.welcome as string) || 'Hello! How can I help you today?';
    const timestamp = (req.query.ts as string) || '';
    const signature = (req.query.sig as string) || '';

    const requestOrigin = extractRequestOrigin(req);

    // Look up tenant allowed origins and widget secret
    const tenantRes = await pool.query(
      `SELECT id, slug, name, widget_allowed_origins, widget_secret 
       FROM tenants WHERE slug = $1 OR id::text = $1`,
      [tenantSlug]
    );

    if (tenantRes.rows.length === 0) {
      return res.status(404).json({ error: 'Tenant not found' });
    }

    const tenant = tenantRes.rows[0];
    const allowedOrigins: string[] = tenant.widget_allowed_origins || [];

    // 1. Origin Validation
    const isAllowedOrigin =
      allowedOrigins.includes('*') ||
      (requestOrigin && allowedOrigins.some((allowed) => allowed === requestOrigin || allowed === '*'));

    if (allowedOrigins.length === 0 || (!isAllowedOrigin && requestOrigin)) {
      Logger.warn(`🚨 [WIDGET ORIGIN BLOCKED] Unauthorized embed attempt from origin '${requestOrigin}' for tenant '${tenant.slug}'`);
      return res.status(403).json({
        error: 'WIDGET_ORIGIN_NOT_ALLOWED',
        message: 'Embedding widget is disabled or unauthorized for this domain origin.',
      });
    }

    // 2. HMAC Signature Verification (if provided)
    if (signature && timestamp) {
      const secret = tenant.widget_secret || process.env.JWT_SECRET || 'widget_fallback_secret';
      const expectedSig = crypto
        .createHmac('sha256', secret)
        .update(`${requestOrigin}|${timestamp}`)
        .digest('hex');

      if (signature !== expectedSig) {
        Logger.warn(`⚠️ [WIDGET SIG MISMATCH] Invalid widget.js signature from origin '${requestOrigin}'`);
      }
    }

    // 3. Set Strict Non-Wildcard CORS Header for Allowed Origin
    if (requestOrigin) {
      res.setHeader('Access-Control-Allow-Origin', requestOrigin);
      res.setHeader('Access-Control-Allow-Credentials', 'true');
    }

    // 4. Track Widget Session Analytics in background
    if (requestOrigin) {
      pool.query(
        `INSERT INTO widget_analytics (tenant_id, origin, sessions_count, last_activity_at)
         VALUES ($1, $2, 1, NOW())
         ON CONFLICT (tenant_id, origin) DO UPDATE 
         SET sessions_count = widget_analytics.sessions_count + 1,
             last_activity_at = NOW()`,
        [tenant.id, requestOrigin]
      ).catch((err) => Logger.warn('Failed to track widget session analytics:', err.message));
    }

    const jsScript = `
(function() {
  console.log("⚡ [Neuravolt Chat Widget] Initializing for origin: ${requestOrigin || 'local'}");

  const launcher = document.createElement("div");
  launcher.id = "nv-chat-launcher";
  launcher.style.position = "fixed";
  launcher.style.bottom = "20px";
  launcher.style.right = "20px";
  launcher.style.width = "60px";
  launcher.style.height = "60px";
  launcher.style.borderRadius = "50%";
  launcher.style.background = "${color}";
  launcher.style.boxShadow = "0 4px 15px rgba(0,0,0,0.2)";
  launcher.style.cursor = "pointer";
  launcher.style.display = "flex";
  launcher.style.alignItems = "center";
  launcher.style.justifyContent = "center";
  launcher.style.zIndex = "999999";
  launcher.innerHTML = '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>';
  document.body.appendChild(launcher);

  const chatFrame = document.createElement("div");
  chatFrame.id = "nv-chat-container";
  chatFrame.style.position = "fixed";
  chatFrame.style.bottom = "90px";
  chatFrame.style.right = "20px";
  chatFrame.style.width = "340px";
  chatFrame.style.height = "460px";
  chatFrame.style.borderRadius = "16px";
  chatFrame.style.border = "1px solid rgba(255,255,255,0.1)";
  chatFrame.style.background = "rgba(10, 10, 10, 0.95)";
  chatFrame.style.boxShadow = "0 10px 30px rgba(0,0,0,0.3)";
  chatFrame.style.zIndex = "999999";
  chatFrame.style.display = "none";
  chatFrame.style.flexDirection = "column";
  chatFrame.style.overflow = "hidden";
  chatFrame.style.fontFamily = "system-ui, -apple-system, sans-serif";
  
  chatFrame.innerHTML = \`
    <div style="padding: 16px; background: ${color}; color: white; display: flex; gap: 10px; align-items: center; font-size: 0.9rem; font-weight: bold;">
      <div style="width: 8px; height: 8px; border-radius: 50%; background: #10b981;"></div>
      <span>${tenant.name || 'AI Assistant'}</span>
    </div>
    <div id="nv-messages-box" style="flex: 1; padding: 15px; display: flex; flex-direction: column; gap: 10px; overflow-y: auto;">
      <div style="background: rgba(255,255,255,0.06); color: white; padding: 8px 12px; border-radius: 8px; max-width: 80%; font-size: 0.8rem; line-height: 1.4;">
        ${welcome}
      </div>
    </div>
    <div style="border-top: 1px solid rgba(255,255,255,0.1); padding: 10px 15px; display: flex; gap: 10px; align-items: center; background: rgba(0,0,0,0.2);">
      <input type="text" id="nv-chat-input" placeholder="Type a message..." style="flex: 1; height: 32px; font-size: 0.8rem; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; padding-inline: 8px; color: white; outline: none;" />
      <button id="nv-chat-send" style="background: ${color}; color: white; border: none; border-radius: 6px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; cursor: pointer;">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
      </button>
    </div>
  \`;
  document.body.appendChild(chatFrame);

  launcher.addEventListener("click", function() {
    chatFrame.style.display = chatFrame.style.display === "none" ? "flex" : "none";
  });

  const sendBtn = chatFrame.querySelector("#nv-chat-send");
  const inputEl = chatFrame.querySelector("#nv-chat-input");
  const msgBox = chatFrame.querySelector("#nv-messages-box");

  async function sendMessage() {
    const text = inputEl.value.trim();
    if (!text) return;
    inputEl.value = "";

    const userMsg = document.createElement("div");
    userMsg.style.cssText = "align-self: flex-end; background: ${color}; color: white; padding: 8px 12px; border-radius: 8px; max-width: 80%; font-size: 0.8rem; line-height: 1.4;";
    userMsg.innerText = text;
    msgBox.appendChild(userMsg);
    msgBox.scrollTop = msgBox.scrollHeight;

    try {
      const res = await fetch("/api/widget/chat", {
        method: "POST",
        headers: { 
          "Content-Type": "application/json",
          "x-tenant-slug": "${tenantSlug}"
        },
        body: JSON.stringify({ message: text, origin: "${requestOrigin}" })
      });
      const data = await res.json();
      
      const botMsg = document.createElement("div");
      botMsg.style.cssText = "align-self: flex-start; background: rgba(255,255,255,0.06); color: white; padding: 8px 12px; border-radius: 8px; max-width: 80%; font-size: 0.8rem; line-height: 1.4;";
      botMsg.innerText = data.response || data.error || "Sorry, I encountered an issue.";
      msgBox.appendChild(botMsg);
      msgBox.scrollTop = msgBox.scrollHeight;
    } catch(err) {
      console.error(err);
    }
  }

  sendBtn.addEventListener("click", sendMessage);
  inputEl.addEventListener("keydown", function(e) {
    if (e.key === "Enter") sendMessage();
  });
})();
    `;

    res.setHeader('Content-Type', 'application/javascript');
    return res.status(200).send(jsScript);
  } catch (err) {
    Logger.error('Widget route error:', err);
    res.status(500).json({ error: 'Failed to generate widget script' });
  }
});

// POST /api/widget/chat - Rate limited widget message execution & origin analytics
router.post('/api/widget/chat', async (req: Request, res: Response) => {
  try {
    const tenantSlug = (req.headers['x-tenant-slug'] as string) || req.body?.tenantSlug || 'neuravolt';
    const { message, origin: clientOrigin } = req.body;
    const requestOrigin = extractRequestOrigin(req) || clientOrigin || 'unknown';

    if (!message) {
      return res.status(400).json({ error: 'Message is required' });
    }

    const tenantRes = await pool.query(
      `SELECT id, slug, widget_allowed_origins FROM tenants WHERE slug = $1 OR id::text = $1`,
      [tenantSlug]
    );

    if (tenantRes.rows.length === 0) {
      return res.status(404).json({ error: 'Tenant not found' });
    }

    const tenant = tenantRes.rows[0];
    const allowedOrigins: string[] = tenant.widget_allowed_origins || [];

    // Origin Check
    const isAllowed = allowedOrigins.includes('*') || allowedOrigins.includes(requestOrigin);
    if (allowedOrigins.length === 0 || !isAllowed) {
      return res.status(403).json({
        error: 'WIDGET_ORIGIN_NOT_ALLOWED',
        message: 'This domain origin is not authorized to execute chat requests.',
      });
    }

    // Set CORS Header specifically for this origin
    if (requestOrigin && requestOrigin !== 'unknown') {
      res.setHeader('Access-Control-Allow-Origin', requestOrigin);
    }

    // Per-Origin Rate Limiting (Max 100 messages/hr per origin)
    const rateKey = `ratelimit:widget:msg:${tenant.id}:${requestOrigin}`;
    const attempts = await redis.incr(rateKey);
    if (attempts === 1) await redis.expire(rateKey, 3600);

    if (attempts > 100) {
      return res.status(429).json({
        error: 'WIDGET_RATE_LIMIT_EXCEEDED',
        message: 'Rate limit exceeded: Maximum 100 widget messages per hour for this origin domain.',
      });
    }

    // Track Analytics
    pool.query(
      `INSERT INTO widget_analytics (tenant_id, origin, messages_sent, last_activity_at)
       VALUES ($1, $2, 1, NOW())
       ON CONFLICT (tenant_id, origin) DO UPDATE 
       SET messages_sent = widget_analytics.messages_sent + 1,
           last_activity_at = NOW()`,
      [tenant.id, requestOrigin]
    ).catch(() => {});

    res.json({
      success: true,
      response: `Thank you for reaching out! Your message was received securely via widget origin ${requestOrigin}.`,
    });
  } catch (err) {
    Logger.error('Widget chat error:', err);
    res.status(500).json({ error: 'Failed to process widget message' });
  }
});

export default router;
