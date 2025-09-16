#!/bin/bash

# Super Simple Update Script
# Update code ke server dalam 1 command

SERVER_IP="103.236.140.19"
SERVER_USER="root"
SERVER_PATH="/root/SyncFlow"

echo "⚡ Quick Update SyncFlow API"
echo "============================"

# Sync code ke server
echo "📤 Syncing code..."
rsync -avz --delete \
    --exclude 'node_modules' \
    --exclude 'vendor' \
    --exclude '.git' \
    --exclude 'storage/logs' \
    --exclude '.env' \
    ./ $SERVER_USER@$SERVER_IP:$SERVER_PATH/

# Run Laravel commands di container
echo "⚙️  Updating Laravel..."
ssh $SERVER_USER@$SERVER_IP "docker exec syncflow-api php artisan config:clear"
ssh $SERVER_USER@$SERVER_IP "docker exec syncflow-api php artisan cache:clear"
ssh $SERVER_USER@$SERVER_IP "docker exec syncflow-api php artisan migrate --force"

echo "✅ Update completed!"
echo "🌐 API: http://$SERVER_IP:2020/api/v1"
