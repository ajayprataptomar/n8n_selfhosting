#!/bin/bash
# Neuravolt Cloud database migration runner

set -e

echo "🐘 Bootstrapping PostgreSQL migrations via Prisma..."

cd backend

# Execute Prisma Client updates
npx prisma generate

# Execute DB push or schema migration
if [ -n "$DATABASE_URL" ]; then
  npx prisma db push --accept-data-loss
  echo "✅ Database schema pushed to target database instance successfully."
else
  echo "⚠️ DATABASE_URL is not set. Skipping actual database sync."
fi
