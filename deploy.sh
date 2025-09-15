#!/bin/bash

# SyncFlow Deployment Script untuk Debian 11
# Usage: ./deploy.sh

set -e

echo "🚀 SyncFlow Deployment Script"
echo "================================"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if Docker is installed
check_docker() {
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Installing Docker..."
        curl -fsSL https://get.docker.com -o get-docker.sh
        sh get-docker.sh
        usermod -aG docker $USER
        print_status "Docker installed successfully"
    else
        print_status "Docker is already installed"
    fi
}

# Check if Docker Compose is installed
check_docker_compose() {
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Installing..."
        curl -L "https://github.com/docker/compose/releases/latest/download/docker-compose-$(uname -s)-$(uname -m)" -o /usr/local/bin/docker-compose
        chmod +x /usr/local/bin/docker-compose
        print_status "Docker Compose installed successfully"
    else
        print_status "Docker Compose is already installed"
    fi
}

# Create required directories
create_directories() {
    print_status "Creating required directories..."
    mkdir -p docker/mysql-init
    mkdir -p storage/logs
    chmod -R 755 storage/
}

# Setup environment file
setup_environment() {
    if [ ! -f .env ]; then
        print_status "Creating production environment file..."
        cp .env.example .env
        
        # Update .env for production
        sed -i 's/APP_ENV=local/APP_ENV=production/' .env
        sed -i 's/APP_DEBUG=true/APP_DEBUG=false/' .env
        sed -i 's/DB_CONNECTION=sqlite/DB_CONNECTION=mysql/' .env
        sed -i 's/DB_HOST=127.0.0.1/DB_HOST=syncflow-db/' .env
        sed -i 's/DB_DATABASE=laravel/DB_DATABASE=syncflow/' .env
        sed -i 's/DB_USERNAME=root/DB_USERNAME=syncflow_user/' .env
        sed -i 's/DB_PASSWORD=/DB_PASSWORD=SyncFlow2024#Secure/' .env
        
        print_status "Environment file created and configured"
    else
        print_warning ".env file already exists, skipping creation"
    fi
}

# Stop existing containers
stop_containers() {
    print_status "Stopping existing containers..."
    docker-compose down --remove-orphans || true
}

# Build and start containers
start_containers() {
    print_status "Building and starting containers..."
    docker-compose up -d --build
    
    print_status "Waiting for services to be ready..."
    sleep 30
    
    # Check container status
    docker-compose ps
}

# Show deployment info
show_info() {
    print_status "Deployment completed successfully! 🎉"
    echo
    echo -e "${BLUE}=== SyncFlow API URLs ===${NC}"
    echo -e "🌐 API Endpoint: ${GREEN}http://$(hostname -I | awk '{print $1}'):2020${NC}"
    echo -e "🌐 API Endpoint: ${GREEN}http://103.236.140.19:2020${NC}"
    echo -e "🗄️  phpMyAdmin: ${GREEN}http://$(hostname -I | awk '{print $1}'):8081${NC}"
    echo -e "🗄️  phpMyAdmin: ${GREEN}http://103.236.140.19:8081${NC}"
    echo
    echo -e "${BLUE}=== Database Connection Info ===${NC}"
    echo -e "🔗 Host: localhost"
    echo -e "🔗 Port: 33061"
    echo -e "👤 Username: syncflow_user"
    echo -e "🔑 Password: SyncFlow2024#Secure"
    echo -e "🗃️  Database: syncflow"
    echo
    echo -e "${BLUE}=== Management Commands ===${NC}"
    echo -e "📜 View logs: ${YELLOW}docker-compose logs -f${NC}"
    echo -e "🔄 Restart: ${YELLOW}docker-compose restart${NC}"
    echo -e "🛑 Stop: ${YELLOW}docker-compose down${NC}"
    echo -e "🚀 Start: ${YELLOW}docker-compose up -d${NC}"
    echo
    echo -e "${GREEN}Happy coding! 🎯${NC}"
}

# Main deployment function
main() {
    print_status "Starting SyncFlow deployment on Debian 11..."
    
    # Check system requirements
    check_docker
    check_docker_compose
    
    # Setup project
    create_directories
    setup_environment
    
    # Deploy
    stop_containers
    start_containers
    
    # Show information
    show_info
}

# Run main function
main "$@"
