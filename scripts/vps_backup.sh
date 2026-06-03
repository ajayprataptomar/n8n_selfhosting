#!/bin/bash
# Neuravolt VPS Migration Pack Export Utility
# This script bundles the DB dump, configurations, secrets, and all client n8n volumes into a single archive.

set -e

EXPORT_DIR="./neuravolt_migration"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
OUTPUT_FILE="./neuravolt_pack_${TIMESTAMP}.tar.gz"

echo "🤖 Initializing Neuravolt Migration Pack Export..."
rm -rf "$EXPORT_DIR"
mkdir -p "$EXPORT_DIR"
mkdir -p "$EXPORT_DIR/configs"
mkdir -p "$EXPORT_DIR/volumes"

# 1. Back up database
if [ "$(docker ps -q -f name=nv-postgres)" ]; then
  echo "🐘 Dumping PostgreSQL database..."
  DB_PASS=$(cat ./secrets/db_password)
  docker exec -e PGPASSWORD="$DB_PASS" nv-postgres pg_dump -U neuravolt neuravolt > "$EXPORT_DIR/database.sql"
  echo "🐘 DB dump successful."
else
  echo "❌ Error: nv-postgres container is not running. Cannot backup database."
  exit 1
fi

# 2. Copy configs and secrets
echo "🔑 Bundling environment variables and secrets..."
if [ -f ".env" ]; then
  cp .env "$EXPORT_DIR/configs/.env"
fi
if [ -d "secrets" ]; then
  cp -r secrets "$EXPORT_DIR/configs/secrets"
fi
if [ -d "traefik/letsencrypt" ]; then
  mkdir -p "$EXPORT_DIR/configs/traefik"
  cp -r traefik/letsencrypt "$EXPORT_DIR/configs/traefik/letsencrypt"
fi

# 3. Export all user n8n data volumes
echo "🐳 Exporting client n8n docker volumes..."
VOLUMES=$(docker volume ls -q -f name=nv-instance-)

for vol in $VOLUMES; do
  echo "📦 Exporting volume: $vol"
  # Mount the volume and compress it inside a temporary container
  docker run --rm \
    -v "$vol:/data:ro" \
    -v "$(pwd)/$EXPORT_DIR/volumes:/backup" \
    alpine tar -czf "/backup/${vol}.tar.gz" -C /data .
done

# 4. Tar the migration package
echo "📚 Packing everything into unified archive..."
tar -czf "$OUTPUT_FILE" "$EXPORT_DIR"
rm -rf "$EXPORT_DIR"

echo "--------------------------------------------------------"
echo "✅ Neuravolt Migration Pack created successfully!"
echo "📍 Location: $OUTPUT_FILE"
echo "👉 Move this file to your new VPS and run vps_restore.sh"
echo "--------------------------------------------------------"
