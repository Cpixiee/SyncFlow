#!/bin/bash

# SyncFlow API - Automated Deployment Script
# Script untuk update code ke server tanpa rebuild Docker

set -e

echo "🚀 SyncFlow API - Automated Deployment"
echo "======================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
SERVER_IP="103.236.140.19"
SERVER_USER="root"
SERVER_PATH="/root/SyncFlow"
CONTAINER_NAME="syncflow-api"

echo -e "${BLUE}📋 Deployment Configuration:${NC}"
echo "Server: $SERVER_IP"
echo "Path: $SERVER_PATH"
echo "Container: $CONTAINER_NAME"
echo ""

# Function to run command on server
run_on_server() {
    ssh $SERVER_USER@$SERVER_IP "$1"
}

# Function to check if container is running
check_container() {
    if run_on_server "docker ps | grep -q $CONTAINER_NAME"; then
        echo -e "${GREEN}✅ Container $CONTAINER_NAME is running${NC}"
        return 0
    else
        echo -e "${RED}❌ Container $CONTAINER_NAME is not running${NC}"
        return 1
    fi
}

# Step 1: Check if server is accessible
echo -e "${YELLOW}🔍 Checking server connection...${NC}"
if ! ssh -o ConnectTimeout=10 $SERVER_USER@$SERVER_IP "echo 'Server connection OK'"; then
    echo -e "${RED}❌ Cannot connect to server $SERVER_IP${NC}"
    exit 1
fi
echo -e "${GREEN}✅ Server connection OK${NC}"

# Step 2: Check if container is running
echo -e "${YELLOW}🔍 Checking Docker container...${NC}"
if ! check_container; then
    echo -e "${YELLOW}⚠️  Container not running. Starting container...${NC}"
    run_on_server "cd $SERVER_PATH && docker-compose up -d"
    sleep 10
    if ! check_container; then
        echo -e "${RED}❌ Failed to start container${NC}"
        exit 1
    fi
fi

# Step 3: Pull latest code from Git
echo -e "${YELLOW}📥 Pulling latest code from Git...${NC}"
run_on_server "cd $SERVER_PATH && git pull origin main"

# Step 4: Install/Update dependencies (only if composer.lock changed)
echo -e "${YELLOW}📦 Checking dependencies...${NC}"
if run_on_server "cd $SERVER_PATH && [ -f composer.lock ] && [ composer.lock -nt vendor/ ]"; then
    echo -e "${YELLOW}📦 Installing/Updating Composer dependencies...${NC}"
    run_on_server "cd $SERVER_PATH && docker exec $CONTAINER_NAME composer install --no-dev --optimize-autoloader"
else
    echo -e "${GREEN}✅ Dependencies are up to date${NC}"
fi

# Step 5: Run Laravel commands
echo -e "${YELLOW}⚙️  Running Laravel commands...${NC}"

# Clear caches
run_on_server "docker exec $CONTAINER_NAME php artisan config:clear"
run_on_server "docker exec $CONTAINER_NAME php artisan cache:clear"
run_on_server "docker exec $CONTAINER_NAME php artisan route:clear"

# Run migrations (if any)
echo -e "${YELLOW}🗃️  Running database migrations...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan migrate --force"

# Run seeders (if needed)
echo -e "${YELLOW}🌱 Running database seeders...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan db:seed --force"

# Optimize for production
echo -e "${YELLOW}🚀 Optimizing for production...${NC}"
run_on_server "docker exec $CONTAINER_NAME php artisan config:cache"
run_on_server "docker exec $CONTAINER_NAME php artisan route:cache"

# Set proper permissions
run_on_server "docker exec $CONTAINER_NAME chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache"

# Step 6: Restart container (optional - only if needed)
echo -e "${YELLOW}🔄 Restarting container...${NC}"
run_on_server "docker restart $CONTAINER_NAME"

# Step 7: Health check
echo -e "${YELLOW}🏥 Performing health check...${NC}"
sleep 5

if curl -f -s "http://$SERVER_IP:2020/api/v1/login" > /dev/null; then
    echo -e "${GREEN}✅ API is responding correctly${NC}"
else
    echo -e "${YELLOW}⚠️  API health check failed, but deployment completed${NC}"
fi

# Step 8: Show deployment summary
echo ""
echo -e "${GREEN}🎉 Deployment completed successfully!${NC}"
echo -e "${BLUE}📊 Deployment Summary:${NC}"
echo "• Server: $SERVER_IP"
echo "• API URL: http://$SERVER_IP:2020/api/v1"
echo "• phpMyAdmin: http://$SERVER_IP:8081"
echo "• Container: $CONTAINER_NAME"
echo ""
echo -e "${YELLOW}💡 Next steps:${NC}"
echo "• Test API endpoints with Postman"
echo "• Check logs: docker logs $CONTAINER_NAME"
echo "• Monitor container: docker ps"
echo ""
echo -e "${GREEN}✨ Your team can now test the updated API!${NC}"