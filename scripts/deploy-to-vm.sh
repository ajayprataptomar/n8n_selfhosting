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

# Step 2: SSH into VM and deploy
echo ""
echo -e "${YELLOW}Step 2: Deploying to VM...${NC}"

ssh -o ServerAliveInterval=30 -o ServerAliveCountMax=20 -i "$VM_KEY" "$VM_USER@$VM_HOST" << 'REMOTE_SCRIPT'
    set -e
    
    echo "Connected to VM"
    
    sudo mkdir -p /mnt/docker-data/harikson && sudo chown ubuntu:ubuntu /mnt/docker-data/harikson
    cd /mnt/docker-data
    
    if [ ! -d "harikson" ]; then
        echo "Cloning fresh repository..."
        git clone https://github.com/ashishtomarnet123-oss/harikson.git
        cd harikson
        chmod +x scripts/*.sh
        ./scripts/deploy.sh
    else
        echo "Repository exists. Freeing up disk space on VM..."
        docker system prune -af --volumes || true
        docker builder prune -af || true
        sudo rm -rf /tmp/* || true
        
        cd harikson
        git fetch origin
        git reset --hard origin/main
        
        # Build modified containers
        docker compose build --no-cache api app admin
        
        # Re-launch services
        docker compose up -d
    fi
REMOTE_SCRIPT

echo ""
echo -e "${GREEN}==========================================${NC}"
echo -e "${GREEN}  DEPLOYMENT SUCCESSFUL!${NC}"
echo -e "${GREEN}==========================================${NC}"
