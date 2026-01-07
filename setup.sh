#!/bin/bash

# Vehicle POS Setup Script
echo "🚗 Vehicle POS - Auto Parts Management System Setup"
echo "=================================================="
echo ""

# Check if .env exists
if [ ! -f .env ]; then
    echo "📝 Creating .env file..."
    cp .env.example .env
    echo "✓ .env file created"
else
    echo "✓ .env file already exists"
fi

# Install composer dependencies
echo ""
echo "📦 Installing PHP dependencies..."
composer install --no-interaction

# Generate application key
echo ""
echo "🔑 Generating application key..."
php artisan key:generate

# Ask for database credentials
echo ""
echo "🗄️  Database Configuration"
read -p "Enter database name [vehicle_pos]: " DB_NAME
DB_NAME=${DB_NAME:-vehicle_pos}

read -p "Enter database username [root]: " DB_USER
DB_USER=${DB_USER:-root}

read -sp "Enter database password: " DB_PASS
echo ""

# Update .env file
echo ""
echo "💾 Updating database configuration..."
if [[ "$OSTYPE" == "darwin"* ]]; then
    # macOS
    sed -i '' "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i '' "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i '' "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
else
    # Linux
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=$DB_NAME/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=$DB_USER/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=$DB_PASS/" .env
fi
echo "✓ Database configuration updated"

# Run migrations
echo ""
read -p "Do you want to run migrations now? (y/n): " RUN_MIGRATIONS
if [[ $RUN_MIGRATIONS =~ ^[Yy]$ ]]; then
    echo "🔄 Running database migrations..."
    php artisan migrate
    echo "✓ Migrations completed"
    
    echo ""
    read -p "Do you want to seed the database with initial data? (y/n): " RUN_SEED
    if [[ $RUN_SEED =~ ^[Yy]$ ]]; then
        echo "🌱 Seeding database..."
        php artisan db:seed
        echo "✓ Database seeded successfully"
    fi
fi

# Create storage link
echo ""
echo "🔗 Creating storage symlink..."
php artisan storage:link
echo "✓ Storage link created"

# Clear caches
echo ""
echo "🧹 Clearing caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear
echo "✓ Caches cleared"

# Success message
echo ""
echo "=================================================="
echo "✅ Setup completed successfully!"
echo "=================================================="
echo ""
echo "📌 Default Login Credentials:"
echo "   Email: admin@vehiclepos.com"
echo "   Password: password"
echo ""
echo "🚀 To start the development server, run:"
echo "   php artisan serve"
echo ""
echo "🌐 Then open your browser and visit:"
echo "   http://localhost:8000"
echo ""
echo "=================================================="
