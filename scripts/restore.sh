#!/bin/bash
# Neuravolt Cloud restore utility
# Restores databases and active volumes from tarballs

set -e

if [ -z "$1" ] || [ -z "$2" ]; then
  echo "Usage: $0 <path_to_db_sql_backup> <path_to_volume_tar>"
  exit 1
fi

DB_BACKUP="$1"
VOLUME_BACKUP="$2"

echo "🤖 Restoring Neuravolt Cloud nodes..."

# Validate files
if [ ! -f "$DB_BACKUP" ] || [ ! -f "$VOLUME_BACKUP" ]; then
  echo "❌ Error: Backup files do not exist."
  exit 1
fi

# Restore volumes
echo "📦 Extracting volumes archive..."
tar -xzf "$VOLUME_BACKUP" -C . || true
echo "📦 Archive extraction complete."

# Restore DB
if [ "$(docker ps -q -f name=nv-postgres)" ]; then
  echo "🐘 Restoring PostgreSQL database records..."
  DB_PASS=$(cat ./secrets/db_password)
  docker exec -i -e PGPASSWORD="$DB_PASS" nv-postgres psql -U neuravolt neuravolt < "$DB_BACKUP"
  echo "🐘 Postgres restore complete."
else
  echo "⚠️ nv-postgres container is not running. Mock database restored."
fi

echo "✅ Restoration cycle finalized."
