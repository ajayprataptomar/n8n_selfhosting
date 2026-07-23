import dns from 'dns/promises';
import { pool, invalidateTenantCache } from '../db/pool.js';
import logger from '../utils/logger.js';

const TARGET_CNAME = process.env.CUSTOM_DOMAIN_TARGET_CNAME || 'neuravolt.cloud';

/**
 * Validate DNS CNAME record for custom domain and save to tenant.
 */
export async function setupCustomDomain(tenantId: string, domain: string): Promise<{ verified: boolean; message: string }> {
  const cleanDomain = domain.toLowerCase().trim().replace(/^https?:\/\//, '');

  try {
    // 1. Query DNS CNAME records for domain
    let cnames: string[] = [];
    try {
      cnames = await dns.resolveCname(cleanDomain);
    } catch (dnsErr: any) {
      logger.warn(`CNAME lookup failed for ${cleanDomain}:`, dnsErr.message);
    }

    const isVerified = cnames.some((c) => c.toLowerCase().includes(TARGET_CNAME));

    // 2. Save domain in DB
    await pool.query(
      `UPDATE tenants 
       SET custom_domain = $1, 
           custom_domain_verified = $2, 
           custom_domain_ssl_status = $3, 
           updated_at = NOW() 
       WHERE id = $4`,
      [cleanDomain, isVerified, isVerified ? 'active' : 'pending_dns', tenantId]
    );

    await invalidateTenantCache(tenantId);

    if (isVerified) {
      logger.info(`🌐 Custom domain ${cleanDomain} verified successfully for tenant ${tenantId}`);
      return { verified: true, message: `Custom domain ${cleanDomain} verified successfully.` };
    } else {
      return {
        verified: false,
        message: `DNS verification pending. Please create a CNAME record for ${cleanDomain} pointing to ${TARGET_CNAME}.`,
      };
    }
  } catch (err: any) {
    logger.error(`Setup custom domain failed for ${cleanDomain}:`, err);
    throw err;
  }
}
