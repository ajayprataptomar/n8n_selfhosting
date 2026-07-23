# Harikson Multi-Tenant AI Platform

Harikson is a high-performance, multi-tenant AI developer platform designed for cost-efficient, single-instance Virtual Machine (16GB RAM) deployments. The platform isolates tenant data using PostgreSQL Row-Level Security (RLS), throttles usage using plan-based Redis rate limits, and serves customized local LLMs via Ollama. It integrates with an IDE extension (VS Code) for inline ghost text autocompletions, developer sidebar chats, and interactive selection code reviews.

---

## 🏛️ System Architecture

```mermaid
graph TD
    User([Developer User]) -->|VS Code Extension| API[Tenant API: Port 3000]
    Browser([Web Browser]) -->|User Portal| API
    Admin([Superadmin User]) -->|Admin Panel| AdminAPI[Admin API: Port 4000]

    subgraph Shared VM Infrastructure
        API -->|Pool Connection & RLS| DB[(PostgreSQL: Port 5432)]
        API -->|Plan Rate Limits| Redis[(Redis: Port 6379)]
        API -->|Sequential Prompt Inference| Ollama[Ollama LLM Engine: Port 11434]

        AdminAPI -->|Tenant Provisioning & Usage Stats| DB
    end
```

---

## 📁 Repository Directory Structure

The codebase is organized into modular services:

- **`harikson/tenant-api/src/migrations/`**: Centralized SQL schema migrations (001 to 023+). Enables `uuid-ossp`, RLS policies, indexes, multi-tenancy, and automated checksum tracking.
- **`tenant-api/`**: The backend service handling chat generation, model catalogs, and auth scoped dynamically by tenant subdomain.
- **`admin-api/`**: Separate admin service handling tenant creation (with database provisioning transactions), usage analytics, and VM capacity tracking.
- **`user-portal/`**: Next.js user-facing web app. Includes the sandbox chat page (`user-portal/pages/chat.js`).
- **`admin-panel/`**: Next.js admin dashboard page (`admin-panel/pages/dashboard.js`) rendering VM alerts, capacity graphs, and tenant controllers.
- **`ide-extension/`**: VS Code extension files providing autocomplete triggers, sidebar consoles, diff panels, and status bar toggles.
- **`model-builder/`**: Local template Modelfiles (`harikson-plus` and `harikson-max`) defining prompts and parameters.
- **`scripts/`**: Shell scripts automating system checks, Docker updates, LLM pulls, test diagnostics, and VM deployments.

## 📊 Database ER Diagram

```mermaid
erDiagram
    tenants ||--o{ users : "has"
    tenants ||--o{ subscriptions : "has"
    tenants ||--o{ invoices : "has"
    tenants ||--o{ conversations : "has"
    tenants ||--o{ messages : "has"
    users ||--o{ conversations : "owns"
    users ||--o{ password_reset_tokens : "has"
    users ||--o{ activity_logs : "has"
    users ||--o{ user_sessions : "has"
    users ||--o{ api_keys : "has"
    conversations ||--o{ messages : "contains"
    plans ||--o{ subscriptions : "defines"
    subscriptions ||--o{ invoices : "bills"

    tenants {
        uuid id PK
        varchar name
        varchar slug
        varchar plan
        varchar status
        timestamptz created_at
        timestamptz updated_at
    }
    users {
        uuid id PK
        uuid tenant_id FK
        varchar email
        varchar password_hash
        varchar role
        timestamptz created_at
        timestamptz updated_at
    }
    plans {
        varchar id PK
        varchar name
        varchar tier
        numeric price
        varchar billing
        varchar currency
        boolean is_active
        boolean is_recommended
        integer token_limit
        integer tenant_limit
        integer agent_limit
        text_array model_access
        jsonb features
        text description
        timestamptz created_at
    }
    subscriptions {
        uuid id PK
        uuid tenant_id FK
        varchar provider
        varchar provider_subscription_id
        varchar plan_id FK
        varchar status
        timestamptz current_period_start
        timestamptz current_period_end
        decimal amount
        varchar currency
        jsonb metadata
        timestamptz created_at
        timestamptz updated_at
    }
    invoices {
        uuid id PK
        uuid tenant_id FK
        uuid subscription_id FK
        varchar provider
        varchar provider_invoice_id
        decimal amount
        varchar currency
        varchar status
        timestamptz paid_at
        text invoice_url
        text pdf_url
        timestamptz created_at
        timestamptz updated_at
    }
```

---

## 🔒 Multi-Tenant Row-Level Security (RLS)

Instead of running separate database containers per client, data privacy is enforced at the database layer using PostgreSQL RLS policies.

1.  **Context-Binding Function**:
    Every query checkout sets the PostgreSQL session parameter `app.current_tenant`:
    ```sql
    CREATE OR REPLACE FUNCTION set_tenant_context(tenant_id UUID)
    RETURNS VOID AS $$
    BEGIN
        PERFORM set_config('app.current_tenant', tenant_id::text, false);
    END;
    $$ LANGUAGE plpgsql;
    ```
2.  **RLS Isolation Policies**:
    RLS is enabled across all tables, filtering results to match the current session ID:
    ```sql
    CREATE POLICY tenant_isolation_policy ON messages
        FOR ALL
        USING (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid)
        WITH CHECK (tenant_id = NULLIF(current_setting('app.current_tenant', true), '')::uuid);
    ```
3.  **API Connection Lifecycle (Anti-Leak)**:
    To prevent connection pool session variable leaks, the Tenant API queries the database using a strict checkout lifecycle wrapper:
    - Acquire a client connection from the database pool.
    - Set the tenant session context: `SELECT set_tenant_context($1)`.
    - Execute the application query (data filtered by RLS).
    - **Reset the tenant context to `NULL`**: `SELECT set_tenant_context(NULL)`.
    - Release the client back to the pool.
    - _Note: Database connections are released during long-running Ollama LLM requests to prevent connection starvation._

---

## ⚡ Plan-Based Rate Limiting

The API throttles chat requests on a sliding 60-second window stored in Redis. The limit adapts dynamically to the tenant's subscription plan:

- **Solo / Starter**: 10 requests per minute.
- **Team / Pro**: 60 requests per minute.
- **Business**: 300 requests per minute.
- **Enterprise**: Unlimited (0).

---

## 🛠️ VS Code Extension Features

The `ide-extension` provides AI assistance directly in the editor:

1.  **Debounced Ghost Text Autocomplete**:
    Provides inline suggestions as you type. Employs a **300ms debounce** checking the cancellation token (`token.isCancellationRequested`) to discard queries if the user continues typing.
2.  **Webview Sidebar Chat**:
    A panel built with VS Code theme variables that sends user prompts to the tenant API and renders Markdown code answers with **"Copy"** clipboard buttons.
3.  **Command: Code Review Diff Editor**:
    Select code, run `Harikson: Review Selection`, and review suggested optimizations in a side-by-side **Diff Editor** pane.
4.  **Status Bar Controller**:
    Displays active model names and connection status (solid green for active, outline red for disconnected). Click the status bar to prompt a dropdown menu to swap model weights.

---

## 🚀 Setup & VM Deployment Guide

We provide four command-line tools under `scripts/` to automate deployment and management:

### 1. Model Downloader (`scripts/download-models.sh`)

Downloads base model weights and compiles branded models in Ollama:

```bash
./scripts/download-models.sh
```

- Pulls `qwen2.5:7b` (Plus) and `qwen2.5:14b` (Max).
- Registers custom system prompt behaviors and options.

### 2. Model Swap Manager (`scripts/model-manager.sh`)

Ollama loads models dynamically. On a 16GB RAM VM, running both 7B and 14B models simultaneously causes out-of-memory errors. The model manager ensures only one model is loaded:

```bash
# Load or switch active models (unloads the previous model first)
./scripts/model-manager.sh switch harikson-max

# Show active memory usage and loaded models
./scripts/model-manager.sh status

# Unload all models to free memory
./scripts/model-manager.sh unload
```

### 4. Comprehensive Diagnostics Suite (`scripts/test.sh`)

Verifies your platform health across 6 diagnostic layers:

```bash
./scripts/test.sh
```

- **Infra**: Verifies Docker daemon status and checks that all 9 containers are running.
- **Ollama**: Confirms port connections, checks model directories, and tests completions.
- **PostgreSQL**: Confirms logins and queries `pg_policies` to verify RLS configuration.
- **Tenant API**: Tests `/health`, `/api/models`, and sends a chat request to verify response text.
- **Frontend**: Confirms Next.js ports respond.
- **Stress Test**: Sends **10 concurrent requests** and measures response time.

### 5. Git Push & SSH VM Deployer (`scripts/deploy-to-vm.sh`)

Deploys local code changes directly to your remote VM:

```bash
./scripts/deploy-to-vm.sh
```

- Establishes an SSH connection to the Ace Cloud VM (`154.201.127.68`), pulls the fresh code, generates random environment secrets, runs migrations, triggers model downloads, and restarts containers.

---

## 🔒 Security & Encryption Configuration

> [!CAUTION]
> **MANDATORY ENVIRONMENT VARIABLE REQUIREMENT**
> The Harikson API service will **FAIL TO START** if `TENANT_MASTER_KEY` is unconfigured or fewer than 32 characters. There are NO hardcoded fallback encryption keys.
> 
> Generate a 256-bit secure key using OpenSSL before booting:
> ```bash
> export TENANT_MASTER_KEY=$(openssl rand -base64 32)
> ```

---

## 🗃️ Database Schema & Migration Guide

> [!IMPORTANT]
> Centralized SQL migrations ([harikson/tenant-api/src/migrations/](file:///Users/ashishpratapsinghtomar/Downloads/files/harikson/tenant-api/src/migrations)) are the **single source of truth** for the database schema. Migrations are tracked with SHA-256 checksums in `migrations_meta` and executed automatically via `npm run migrate`.

### Generating SQL Migrations

All database migrations should be generated and managed using Prisma Migrate:

```bash
# Inside the backend/ directory
npx prisma migrate dev --name <migration_name>
```

### Applying Migrations in Production

During VM deployments and startup, migrations are automatically applied via:

```bash
npx prisma migrate deploy
```
