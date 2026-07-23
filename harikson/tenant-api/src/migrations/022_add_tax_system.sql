-- Migration 022: Create Tax Rates Table, Add Invoices & Plans Tax Columns, Seed Standard Tax Rules

-- 1. Create tax_rates table
CREATE TABLE IF NOT EXISTS tax_rates (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    country_code VARCHAR(10) NOT NULL,
    region_code VARCHAR(10),
    tax_name VARCHAR(100) NOT NULL,
    rate_percent NUMERIC(5, 2) NOT NULL,
    type VARCHAR(20) NOT NULL DEFAULT 'gst',
    hsn_code VARCHAR(20) DEFAULT '998315',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_tax_rates_country_region ON tax_rates(country_code, region_code);

-- 2. Add tax columns to invoices table
ALTER TABLE invoices
ADD COLUMN IF NOT EXISTS subtotal NUMERIC(10, 2),
ADD COLUMN IF NOT EXISTS tax_amount NUMERIC(10, 2),
ADD COLUMN IF NOT EXISTS tax_rate_id UUID REFERENCES tax_rates(id),
ADD COLUMN IF NOT EXISTS total NUMERIC(10, 2),
ADD COLUMN IF NOT EXISTS hsn_code VARCHAR(20) DEFAULT '998315';

-- 3. Add tax region scoping to plans table
ALTER TABLE plans
ADD COLUMN IF NOT EXISTS applicable_tax_regions JSONB DEFAULT '[]'::jsonb;

-- 4. Seed Standard Global Tax Rates
INSERT INTO tax_rates (country_code, region_code, tax_name, rate_percent, type, hsn_code)
VALUES 
    ('IN', NULL, 'GST', 18.00, 'gst', '998315'),
    ('DE', NULL, 'VAT', 19.00, 'vat', '998315'),
    ('FR', NULL, 'VAT', 20.00, 'vat', '998315'),
    ('GB', NULL, 'VAT', 20.00, 'vat', '998315'),
    ('US', 'CA', 'Sales Tax', 7.25, 'sales', '998315'),
    ('US', 'NY', 'Sales Tax', 4.00, 'sales', '998315'),
    ('US', 'TX', 'Sales Tax', 6.25, 'sales', '998315')
ON CONFLICT DO NOTHING;
