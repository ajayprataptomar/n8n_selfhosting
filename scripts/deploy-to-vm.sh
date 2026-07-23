#!/bin/bash
# scripts/deploy-to-vm.sh
# Deploys Harikson from GitHub to your VM

set -e

# Configuration
VM_USER="ubuntu"
VM_HOST="154.201.127.68"
VM_KEY="${SSH_KEY_PATH:-$HOME/Downloads/app.pem}"
VM_PATH="/mnt/docker-data"
BRANCH="main"

# Colors
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo "=========================================="
echo "  HARIKSON DEPLOYMENT TO VM"
echo "=========================================="
echo "VM: $VM_HOST"
echo "Path: $VM_PATH"
echo "=========================================="

# Step 1: Push to GitHub (if local changes)
echo ""
echo -e "${YELLOW}Step 1: Pushing to GitHub...${NC}"
if [ -d ".git" ]; then
    git add -A
    git commit -m "Deploy: $(date '+%Y-%m-%d %H:%M:%S')" || true
    git push origin $BRANCH
    echo -e "${GREEN}✓ Pushed to GitHub${NC}"
else
    echo -e "${RED}✗ Not a git repository. Initialize first:${NC}"
    exit 1
fi

# Step 2: Sync files to VM and deploy
echo ""
echo -e "${YELLOW}Step 2: Syncing codebase to VM ($VM_HOST)...${NC}"

ssh -o ServerAliveInterval=30 -i "$VM_KEY" "$VM_USER@$VM_HOST" "sudo mkdir -p /mnt/docker-data && sudo chown -R ubuntu:ubuntu /mnt/docker-data"

rsync -avz -e "ssh -i $VM_KEY -o ServerAliveInterval=30" \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='.next' \
    --exclude='postgres-data' \
    --exclude='redis-data' \
    ./ "$VM_USER@$VM_HOST:/mnt/docker-data/"

echo -e "${GREEN}✓ Codebase synced to VM${NC}"

echo ""
echo -e "${YELLOW}Step 3: Building and Re-launching services on VM...${NC}"

ssh -o ServerAliveInterval=30 -o ServerAliveCountMax=20 -i "$VM_KEY" "$VM_USER@$VM_HOST" << 'REMOTE_SCRIPT'
    set -e
    
    cd /mnt/docker-data
    
    echo "Stopping existing containers and freeing up disk space on VM..."
    docker compose down --remove-orphans || true
    docker stop $(docker ps -aq) || true
    docker rm $(docker ps -aq) || true
    docker system prune -af --volumes || true
    docker builder prune -af || true
    sudo rm -rf /tmp/* || true
    
    echo "Building services..."
    docker compose build || true
    
    echo "Starting services..."
    docker compose up -d
REMOTE_SCRIPT

echo ""
echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  DEPLOYMENT SUCCESSFUL!${NC}"
echo -e "${GREEN}==========================================${NC}"
