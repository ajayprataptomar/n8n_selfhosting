#!/bin/bash
BACKUP_DIR="/backups"
S3_BUCKET="harikson-backups"
DATE=$(date +%Y%m%d_%H%M%S)

# Create local backup directory if it does not exist
mkdir -p "$BACKUP_DIR"

echo "💾 Starting backup sequence: $DATE"

# Backup PostgreSQL
echo "🗄️ Backing up PostgreSQL database..."
if docker exec harikson-postgres pg_dump -U neuravolt neuravolt > "$BACKUP_DIR/harikson_db_$DATE.sql"; then
  echo "✅ Database backup complete."
else
  echo "⚠️ Database backup failed."
fi

# Backup Redis
echo "💾 Triggering Redis background save..."
if docker exec harikson-redis redis-cli BGSAVE; then
  echo "⏳ Waiting for Redis save completion..."
  sleep 5
  # Copy dump.rdb
  if cp /var/lib/docker/volumes/harikson_redis/_data/dump.rdb "$BACKUP_DIR/harikson_redis_$DATE.rdb" 2>/dev/null || cp /var/lib/docker/volumes/harikson_harikson-redis-data/_data/dump.rdb "$BACKUP_DIR/harikson_redis_$DATE.rdb" 2>/dev/null; then
    echo "✅ Redis backup complete."
  else
    echo "⚠️ Failed to copy Redis dump.rdb."
  fi
else
  echo "⚠️ Redis BGSAVE trigger failed."
fi

# Package models
echo "📦 Packaging GGUF models..."
if tar -czf "$BACKUP_DIR/harikson_models_$DATE.tar.gz" /shared/models 2>/dev/null; then
  echo "✅ Model packaging complete."
else
  echo "⚠️ Model packaging failed."
fi

# Upload to Amazon S3
echo "☁️ Uploading to Amazon S3..."
if command -v aws &> /dev/null; then
  if aws s3 sync "$BACKUP_DIR" "s3://$S3_BUCKET/backups/$DATE/"; then
    echo "✅ S3 Sync complete."
  else
    echo "⚠️ S3 Sync failed."
  fi
else
  echo "⚠️ AWS CLI not installed. Skipping S3 upload."
fi

# Cleanup old backups (older than 7 days)
echo "🧹 Cleaning up old backups..."
find "$BACKUP_DIR" -type f -mtime +7 -delete

echo "🎉 Backup sequence finished."
