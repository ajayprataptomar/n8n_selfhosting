#!/bin/bash
# Neuravolt VPS Migration Pack Restore Utility
# Restores configurations, secrets, databases, and client n8n volumes from a migration pack.

set -e

if [ -z "$1" ]; then
  echo "Usage: $0 <path_to_neuravolt_pack_tar_gz>"
  exit 1
fi

PACK_FILE="$1"
TEMP_DIR="./neuravolt_restore_temp"

if [ ! -f "$PACK_FILE" ]; then
  echo "❌ Error: Pack file '$PACK_FILE' does not exist."
  exit 1
fi

echo "🤖 Extracting Neuravolt Migration Pack..."
rm -rf "$TEMP_DIR"
mkdir -p "$TEMP_DIR"
tar -xzf "$PACK_FILE" -C "$TEMP_DIR"

# Find actual extracted root directory (which might contain timestamp)
EXTRACTED_ROOT=$(find "$TEMP_DIR" -maxdepth 1 -name "neuravolt_migration" | head -n 1)

if [ -z "$EXTRACTED_ROOT" ]; then
  echo "❌ Error: Invalid pack structure. Could not find 'neuravolt_migration' folder."
  exit 1
fi

# 1. Restore configurations and secrets
echo "🔑 Restoring environment variables and secrets..."
if [ -f "$EXTRACTED_ROOT/configs/.env" ]; then
  cp "$EXTRACTED_ROOT/configs/.env" ./.env
  echo "👉 .env restored."
fi
if [ -d "$EXTRACTED_ROOT/configs/secrets" ]; then
  cp -r "$EXTRACTED_ROOT/configs/secrets" ./secrets
  echo "👉 secrets/ folder restored."
fi
if [ -d "$EXTRACTED_ROOT/configs/traefik/letsencrypt" ]; then
  mkdir -p traefik/letsencrypt
  cp -r "$EXTRACTED_ROOT/configs/traefik/letsencrypt" ./traefik/
  echo "👉 traefik/letsencrypt restored."
fi

# 2. Start PostgreSQL container
echo "🐘 Launching PostgreSQL database container..."
docker compose up -d postgres
echo "🐘 Waiting for PostgreSQL database to be healthy..."
sleep 5

# 3. Restore database schema and entries
if [ -f "$EXTRACTED_ROOT/database.sql" ]; then
  echo "🐘 Importing database entries..."
  DB_PASS=$(cat ./secrets/db_password)
  docker exec -i -e PGPASSWORD="$DB_PASS" nv-postgres psql -U neuravolt neuravolt < "$EXTRACTED_ROOT/database.sql"
  echo "🐘 Database restore complete."
else
  echo "⚠️ Warning: No database.sql file found in migration pack. Skipping DB restore."
fi

# 4. Restore client volumes
echo "🐳 Recreating and restoring client n8n volumes..."
VOLUME_FILES=$(find "$EXTRACTED_ROOT/volumes" -name "nv-instance-*.tar.gz" 2>/dev/null || true)

for vol_file in $VOLUME_FILES; do
  # Extract volume name from file name (e.g. nv-instance-sharma-data.tar.gz -> nv-instance-sharma-data)
  vol_name=$(basename "$vol_file" .tar.gz)
  
  echo "📦 Restoring volume: $vol_name"
  # Create the docker volume first
  docker volume create "$vol_name" >/dev/null
  
  # Run a temporary container to extract the archive into the new volume
  docker run --rm \
    -v "$vol_name:/data" \
    -v "$(dirname "$vol_file"):/backup" \
    alpine tar -xzf "/backup/$(basename "$vol_file")" -C /data
done

# 5. Start the complete application stack
echo "🚀 Starting all Neuravolt services..."
docker compose up -d

# Clean up temp folder
rm -rf "$TEMP_DIR"

echo "--------------------------------------------------------"
echo "✅ Restoration completed successfully!"
echo "👉 Verify running containers: docker ps"
echo "--------------------------------------------------------"
