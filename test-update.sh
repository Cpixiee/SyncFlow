#!/bin/bash

# Test script untuk update code ke server
# Ganti SERVER_IP dan SERVER_USER sesuai server kamu

SERVER_IP="103.236.140.19"
SERVER_USER="root"
SERVER_PATH="/root/SyncFlow"

echo "🧪 Testing Quick Update to Server"
echo "================================="
echo "Server: $SERVER_IP"
echo "Path: $SERVER_PATH"
echo ""

# Test connection
echo "🔍 Testing server connection..."
if ssh -o ConnectTimeout=10 $SERVER_USER@$SERVER_IP "echo '✅ Server connection OK'"; then
    echo "✅ Server accessible"
else
    echo "❌ Cannot connect to server"
    echo "💡 Make sure SSH key is setup:"
    echo "   ssh-copy-id $SERVER_USER@$SERVER_IP"
    exit 1
fi

# Test Docker container
echo "🔍 Checking Docker container..."
if ssh $SERVER_USER@$SERVER_IP "docker ps | grep syncflow-api"; then
    echo "✅ Container is running"
else
    echo "❌ Container not running"
    echo "💡 Start container first:"
    echo "   ssh $SERVER_USER@$SERVER_IP 'cd $SERVER_PATH && docker-compose up -d'"
    exit 1
fi

echo ""
echo "🎉 Server is ready for quick updates!"
echo "💡 Now you can run: ./quick-update.sh"
