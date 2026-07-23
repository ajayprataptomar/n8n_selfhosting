# Harikson Engineering Code Review & Security Checklist

## 1. Database & Logging Security
- [ ] **No raw SQL values in logs**: Never log interpolated parameters, raw SQL parameter values, or unredacted query strings at `INFO` level.
- [ ] **Query Tracking**: Database query execution MUST be wrapped via `traceQuery` utility to include a unique 8-character `QID` (`[QID:abc12345]`), target table name, operation (`SELECT`, `INSERT`, `UPDATE`, `DELETE`), and duration in milliseconds (`durationMs`).
- [ ] **PII Redaction**: In `DEBUG` level log outputs, all sensitive data (emails, phone numbers, API keys, Bearer tokens, passwords) MUST be redacted using the `redactPII` helper (`/\\w.-]+@[\\w.-]+\\.\\w+/g` → `[REDACTED_EMAIL]`).

## 2. Authentication & Session Management
- [ ] **Refresh Token Rotation**: Refresh tokens must use 64-byte crypto-random hashes, belong to a `refresh_token_family` UUID, and instantly revoke all family tokens if reuse of a revoked token is detected.
- [ ] **Cookie Security**: All authentication cookies (`hk_access_token`, `hk_refresh_token`) MUST have `HttpOnly`, `SameSite=Strict`, `Path=/`, and `Secure` flags set.

## 3. Data Protection & Isolation
- [ ] **RLS Context**: Every tenant database query MUST execute within `executeTenantQuery` specifying `set_config('app.current_tenant', ...)` and `assert_tenant_context()`.
- [ ] **Read-Your-Writes Consistency**: Any mutation (`POST`, `PUT`, `DELETE`, `PATCH`) MUST force primary database connection and set a 2-second Redis stickiness key `primary_stickiness:${slug}:${userId}`.
