# n8n Hosting (VPS Deployment Notes)

Target VPS:
- Host: `45.194.2.244`
- User: `ubuntu`
- SSH key: `~/Downloads/developer.pem`
- SSH: `ssh -i ~/Downloads/developer.pem ubuntu@45.194.2.244`

This document assumes you want **real containers** on a VPS with **one subdomain per customer instance** (recommended).

---

## 0) Decide Your Domain Layout

Recommended:
- Admin UI: `admin.YOUR_DOMAIN`
- User UI: `app.YOUR_DOMAIN`
- API: `api.YOUR_DOMAIN`
- Customer instances: `*.YOUR_DOMAIN` (wildcard)

DNS records (typical):
- `A  api` -> VPS IP
- `A  app` -> VPS IP
- `A  admin` -> VPS IP
- `A  *` -> VPS IP  (wildcard for customer instances)

Note: wildcard is what enables `customer1.YOUR_DOMAIN`, `customer2.YOUR_DOMAIN`, etc.

---

## 1) VPS Setup (Ubuntu)

### 1.1 SSH in
```bash
ssh -i ~/Downloads/developer.pem ubuntu@45.194.2.244
```

### 1.2 Install Docker + Compose
On the VPS:
```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl gnupg

sudo install -m 0755 -d /etc/apt/keyrings
curl -fsSL https://download.docker.com/linux/ubuntu/gpg | sudo gpg --dearmor -o /etc/apt/keyrings/docker.gpg
sudo chmod a+r /etc/apt/keyrings/docker.gpg

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo \"$VERSION_CODENAME\") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin

sudo usermod -aG docker ubuntu
newgrp docker
docker version
docker compose version
```

### 1.3 Open firewall ports
If using `ufw`:
```bash
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

---

## 2) Copy Project To VPS (Keep It Self-Contained)

Pick a folder on VPS (example):
```bash
mkdir -p ~/neuravolt
```

From your local machine (where this repo exists), use `rsync`:
```bash
rsync -avz -e "ssh -i ~/Downloads/developer.pem" \
  --exclude node_modules \
  --exclude postgres-data \
  --exclude redis-data \
  --exclude .next \
  --exclude dist \
  /Users/ajayprataptomar/Downloads/files/ \
  ubuntu@45.194.2.244:~/neuravolt/
```

On the VPS:
```bash
cd ~/neuravolt
ls
```

---

## 3) Production Environment Files

Your repo already has:
- `docker-compose.yml`
- `secrets/`
- `.env` / `.env.example`
- `traefik/` + `monitoring/`

### 3.1 Update `.env` (VPS values)
On the VPS, edit `~/neuravolt/.env` and `~/neuravolt/backend/.env` so they are not localhost-only.

Important values to set for a real VPS:
- `NEXT_PUBLIC_API_URL=https://api.YOUR_DOMAIN`
- `NEXTAUTH_URL=https://app.YOUR_DOMAIN`
- `NEXTAUTH_ADMIN_URL=https://admin.YOUR_DOMAIN`
- `DATABASE_URL=...` (use the compose Postgres service hostname, not `localhost`, if the backend runs in Docker)
- `REDIS_URL=...` (use compose service hostname)

### 3.2 Set real secrets
Update files in `~/neuravolt/secrets/`:
- `secrets/jwt_secret`
- `secrets/db_password`
- `secrets/stripe_webhook_secret`
- `secrets/resend_api_key`
- `secrets/n8n_encryption_key`

Permissions:
```bash
chmod 600 ~/neuravolt/secrets/*
```

---

## 4) Traefik + Docker Networking (Required for Subdomains)

Your backend provisions instance containers with:
- Docker network name: `internal`
- Traefik labels that route by `Host(customer.YOUR_DOMAIN)`

For this to work on VPS:
1. Traefik must be running.
2. Traefik must be attached to the same Docker network as the instance containers (`internal`).

Create the network once (safe if it already exists):
```bash
docker network create internal || true
```

Bring up the compose stack:
```bash
cd ~/neuravolt
docker compose up -d
docker compose ps
```

---

## 5) “Real Containers” Provisioning (n8n)

The system must create **real** per-customer containers and persist their data.

Baseline requirements:
- Use a real image (example: `n8nio/n8n:latest` or a pinned version)
- Provide persistent storage per instance (Docker volume per customer)
- Attach to `internal` network
- Add Traefik labels for the customer subdomain

Operational notes:
- Pin image versions in production (avoid `latest`).
- Add limits (CPU/memory) in the container HostConfig.
- Store container ID + domain in Postgres so admin UI is real data, not demo.

---

## 6) First-Time Run Checklist

1. DNS A records are set (including wildcard `*`).
2. VPS ports 80/443 open.
3. `docker compose up -d` is running clean.
4. Backend reachable: `https://api.YOUR_DOMAIN/`
5. Admin reachable: `https://admin.YOUR_DOMAIN/`
6. App reachable: `https://app.YOUR_DOMAIN/`
7. Approving a user provisions an instance container and routes it on `customer.YOUR_DOMAIN`.

---

## 7) Common Problems

### 7.1 “No such image …”
Docker cannot pull image or image missing.
- Make sure VPS has outbound internet.
- `docker pull <image>`

### 7.2 “network internal not found”
Create the network:
```bash
docker network create internal || true
```

### 7.3 HTTPS not working
Usually:
- DNS not pointing correctly
- Traefik resolver not configured
- Port 80/443 blocked
- Let’s Encrypt rate limiting

---

## 8) Handy Commands

```bash
docker compose logs -f --tail=200
docker ps
docker network ls
docker network inspect internal
docker logs -f <container>
```

---

## 9) What We Still Need To Do In Code (When You’re Ready)

To make this production-grade (not placeholder nginx):
- Update backend provisioning to create `n8n` containers per user, with volumes and Traefik labels.
- Add admin actions for stop/start/restart/delete that match real container lifecycle.
- Ensure domain used in provisioning matches your real domain (not hardcoded `neuravolt.cloud`).

