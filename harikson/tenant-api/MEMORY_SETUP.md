# 🧠 Harikson Memory Service Setup Guide

The Harikson Memory Service introduces a long-term memory layer using Vector embeddings powered by PostgreSQL and the `pgvector` extension.

---

## 🛠️ PostgreSQL pgvector Setup Instructions

If the target database does not support `vector` column datatypes, install `pgvector` on your server.

### 1. Install pgvector on Ubuntu (Host / VPS)

If PostgreSQL is running directly on the host VM:

```bash
sudo apt-get update
sudo apt-get install -y postgresql-15-pgvector
```

### 2. If using Docker Container (`docker-compose.yml`)

The default Postgres Alpine image used in this repository (`postgres:15-alpine`) does not bundle the pgvector extension.

Update the container image in [docker-compose.yml](file:///Users/ashishpratapsinghtomar/Downloads/files/docker-compose.yml) to use a pgvector enabled build:

```yaml
postgres:
  image: ankane/pgvector:v0.5.1-pg15
  # or build a custom Dockerfile incorporating pgvector
```

### 3. Run Database Migrations

Once the pgvector extension is available, run the SQL script to bootstrap the `memories` table:

```bash
docker exec -i harikson-postgres psql -U neuravolt -d neuravolt < src/db/migrations/003_create_memories_table.sql
```

---

## 🧪 Running Unit Tests

To run the custom test suite validating the extractor, store, and retriever flows:

### 1. Install dependencies

Ensure `pg` and TypeScript types are installed:

```bash
npm install pg
npm install -D @types/pg
```

### 2. Run Test Execution

Run the TypeScript unit test runner using `tsx`:

```bash
npx tsx tests/memory.test.ts
```
