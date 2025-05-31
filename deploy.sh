#!/bin/bash

# Budget Control System - Deployment Script
# This script helps prepare the application for deployment

echo "🚀 Budget Control System - Deployment Preparation"
echo "================================================"

# Check if composer is installed
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed. Please install Composer first."
    exit 1
fi

# Install dependencies
echo "📦 Installing dependencies..."
composer install --no-dev --optimize-autoloader

if [ $? -eq 0 ]; then
    echo "✅ Dependencies installed successfully"
else
    echo "❌ Failed to install dependencies"
    exit 1
fi

# Create uploads directory
echo "📁 Creating uploads directory..."
mkdir -p public/uploads
chmod 755 public/uploads
echo "✅ Uploads directory created"

# Check for environment configuration
if [ -f ".env.deployment" ]; then
    echo "📋 Environment configuration found"
    echo "ℹ️  Please configure your environment variables based on .env.deployment"
else
    echo "⚠️  No environment configuration found"
fi

# Display deployment options
echo ""
echo "🌐 Deployment Options:"
echo "1. Render: Use render.yaml configuration"
echo "2. Railway: Use railway.toml configuration"
echo "3. Heroku: Use Procfile configuration"
echo "4. Traditional Hosting: Upload files manually"
echo ""
echo "📖 For detailed instructions, see DEPLOYMENT.md"
echo ""
echo "⚠️  Important Security Notes:"
echo "   - Delete install.php after installation"
echo "   - Change default admin password"
echo "   - Set proper file permissions"
echo "   - Configure SSL/HTTPS"
echo ""
echo "✅ Deployment preparation completed!"
echo "🔗 Next: Follow platform-specific instructions in DEPLOYMENT.md"