import { pool } from '../db/pool.js';
import logger from '../utils/logger.js';

export interface TaxCalculationResult {
  subtotal: number;
  taxAmount: number;
  total: number;
  ratePercent: number;
  taxName: string;
  taxType: string;
  taxRateId: string | null;
  hsnCode: string;
  breakdownText: string;
}

/**
 * Calculates tax for a given subtotal based on customer country and region.
 * Default fallback is 0% tax if no specific tax rule is configured.
 */
export async function calculateTax(
  subtotal: number,
  countryCode: string = 'IN',
  regionCode?: string
): Promise<TaxCalculationResult> {
  const normalizedCountry = (countryCode || 'IN').trim().toUpperCase();
  const normalizedRegion = regionCode ? regionCode.trim().toUpperCase() : null;

  try {
    // 1. Query for region-specific rate first, then country-level rate
    let query = `
      SELECT id, country_code, region_code, tax_name, rate_percent, type, hsn_code
      FROM tax_rates
      WHERE UPPER(country_code) = $1 AND is_active = TRUE
      ORDER BY region_code IS NOT NULL DESC, created_at DESC
    `;
    let params: any[] = [normalizedCountry];

    if (normalizedRegion) {
      query = `
        SELECT id, country_code, region_code, tax_name, rate_percent, type, hsn_code
        FROM tax_rates
        WHERE UPPER(country_code) = $1 
          AND (UPPER(region_code) = $2 OR region_code IS NULL)
          AND is_active = TRUE
        ORDER BY region_code IS NOT NULL DESC, created_at DESC
        LIMIT 1
      `;
      params = [normalizedCountry, normalizedRegion];
    }

    const result = await pool.query(query, params);

    let ratePercent = 0;
    let taxName = 'Tax';
    let taxType = 'standard';
    let taxRateId: string | null = null;
    let hsnCode = '998315'; // Default HSN Code for SaaS services

    if (result.rows.length > 0) {
      const row = result.rows[0];
      ratePercent = Number(row.rate_percent) || 0;
      taxName = row.tax_name || 'Tax';
      taxType = row.type || 'gst';
      taxRateId = row.id;
      hsnCode = row.hsn_code || '998315';
    } else if (normalizedCountry === 'IN') {
      // Default fallback for India: 18% GST (HSN 998315)
      ratePercent = 18.00;
      taxName = 'GST';
      taxType = 'gst';
      hsnCode = '998315';
    }

    const taxAmount = Number(((subtotal * ratePercent) / 100).toFixed(2));
    const total = Number((subtotal + taxAmount).toFixed(2));
    const breakdownText = `${subtotal.toFixed(2)} + ${taxAmount.toFixed(2)} (${taxName} ${ratePercent}%) = ${total.toFixed(2)}`;

    return {
      subtotal,
      taxAmount,
      total,
      ratePercent,
      taxName,
      taxType,
      taxRateId,
      hsnCode,
      breakdownText,
    };
  } catch (err: any) {
    logger.error('Error calculating tax:', err);
    // Safe fallback: 0% tax
    return {
      subtotal,
      taxAmount: 0,
      total: subtotal,
      ratePercent: 0,
      taxName: 'Tax',
      taxType: 'none',
      taxRateId: null,
      hsnCode: '998315',
      breakdownText: `${subtotal.toFixed(2)} subtotal`,
    };
  }
}
