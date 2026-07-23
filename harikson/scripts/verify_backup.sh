#!/bin/bash
# ==============================================================================
# Harikson AI OS — Automated Backup Verification, Restore Testing & Retention
# ==============================================================================
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/backups}"
LATEST_BACKUP=$(ls -t "${BACKUP_DIR}"/harikson_db_*.sql 2>/dev/null | head -n 1 || true)
TEMP_CONTAINER="temp-pg-restore-test-$$"
DB_USER="${POSTGRES_USER:-neuravolt}"
DB_NAME="${POSTGRES_DB:-neuravolt}"

echo "============================================================"
echo "💾 Harikson Automated Backup Verification Sequence"
echo "============================================================"

if [ -z "$LATEST_BACKUP" ] || [ ! -f "$LATEST_BACKUP" ]; then
  echo "🚨 [ALERT] No backup file found in ${BACKUP_DIR}!"
  exit 1
fi

echo "📁 Target Backup File: ${LATEST_BACKUP}"

# 1. FILE INTEGRITY & SIZE CHECK
FILE_SIZE=$(stat -f%z "$LATEST_BACKUP" 2>/dev/null || stat -c%s "$LATEST_BACKUP" 2>/dev/null || echo 0)
if [ "$FILE_SIZE" -le 0 ]; then
  echo "🚨 [ALERT] Backup file size is 0 bytes! Partial or failed backup."
  exit 1
fi
echo "✅ File size verified: ${FILE_SIZE} bytes."

# Verify PostgreSQL Dump Header
if ! head -n 30 "$LATEST_BACKUP" | grep -q "PostgreSQL database dump"; then
  echo "🚨 [ALERT] Backup file header validation failed! Not a valid PostgreSQL dump."
  exit 1
fi
echo "✅ Header check passed: Valid PostgreSQL database dump."

# Compute SHA-256 Checksum
CHECKSUM=$(shasum -a 256 "$LATEST_BACKUP" 2>/dev/null | awk '{print $1}' || sha256sum "$LATEST_BACKUP" | awk '{print $1}')
echo "✅ SHA-256 Checksum: ${CHECKSUM}"

# Check for 25-hour staleness
FILE_MTIME=$(stat -f%m "$LATEST_BACKUP" 2>/dev/null || stat -c%Y "$LATEST_BACKUP" 2>/dev/null)
NOW=$(date +%s)
AGE_HOURS=$(( (NOW - FILE_MTIME) / 3600 ))
if [ "$AGE_HOURS" -gt 25 ]; then
  echo "🚨 [ALERT] Latest backup is ${AGE_HOURS} hours old! (Exceeds 25-hour daily SLA)."
else
  echo "✅ Backup freshness verified: ${AGE_HOURS} hours old."
fi

# 2. AUTOMATED RESTORE TEST IN TEMPORARY CONTAINER
echo "------------------------------------------------------------"
echo "🧪 Spinning up isolated temporary container: ${TEMP_CONTAINER}..."
START_TIME=$(date +%s%3N)

docker run -d \
  --name "$TEMP_CONTAINER" \
  -e POSTGRES_USER="$DB_USER" \
  -e POSTGRES_PASSWORD="restore_verification_pass" \
  -e POSTGRES_DB="$DB_NAME" \
  postgres:16-alpine > /dev/null

# Wait for PostgreSQL ready state
for i in {1..30}; do
  if docker exec "$TEMP_CONTAINER" pg_isready -U "$DB_USER" >/dev/null 2>&1; then
    break
  fi
  sleep 1
done

echo "📥 Restoring backup dump into test database..."
if docker exec -i "$TEMP_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" < "$LATEST_BACKUP" > /dev/null 2>&1; then
  END_TIME=$(date +%s%3N)
  DURATION_MS=$(( END_TIME - START_TIME ))
  echo "✅ Restore completed successfully in ${DURATION_MS} ms."

  # Health Check: Table & RLS Count
  TABLE_COUNT=$(docker exec "$TEMP_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='public';" | tr -d ' ')
  RLS_COUNT=$(docker exec "$TEMP_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM pg_policies;" | tr -d ' ')
  USER_COUNT=$(docker exec "$TEMP_CONTAINER" psql -U "$DB_USER" -d "$DB_NAME" -t -c "SELECT COUNT(*) FROM users;" 2>/dev/null | tr -d ' ' || echo 0)

  echo "📊 Restored Verification Metrics:"
  echo "   - Tables Count: ${TABLE_COUNT}"
  echo "   - RLS Policies Verified: ${RLS_COUNT}"
  echo "   - Users Count: ${USER_COUNT}"
else
  echo "❌ [RESTORE FAILURE] Failed to restore database dump into test container."
  docker rm -f "$TEMP_CONTAINER" > /dev/null 2>&1 || true
  exit 1
fi

# Tear down temporary container
echo "🧹 Tearing down test container..."
docker rm -f "$TEMP_CONTAINER" > /dev/null 2>&1 || true

# 3. BACKUP RETENTION MANAGEMENT
echo "------------------------------------------------------------"
echo "🗄️ Executing Multi-Tier Retention Cleanup Policy..."
mkdir -p "${BACKUP_DIR}/daily" "${BACKUP_DIR}/weekly" "${BACKUP_DIR}/monthly" "${BACKUP_DIR}/yearly"

# Daily (Keep 7 days)
find "${BACKUP_DIR}/daily" -type f -mtime +7 -delete 2>/dev/null || true
# Weekly (Keep 28 days / 4 weeks)
find "${BACKUP_DIR}/weekly" -type f -mtime +28 -delete 2>/dev/null || true
# Monthly (Keep 365 days / 12 months)
find "${BACKUP_DIR}/monthly" -type f -mtime +365 -delete 2>/dev/null || true
# Yearly (Keep 2555 days / 7 years)
find "${BACKUP_DIR}/yearly" -type f -mtime +2555 -delete 2>/dev/null || true

echo "============================================================"
echo "🎉 BACKUP VERIFICATION & RESTORE TEST COMPLETED SUCCESSFULLY"
echo "============================================================"
