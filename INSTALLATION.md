# Vehicle POS - Installation & Setup Guide

## 🚗 Welcome to Vehicle POS System

This guide will help you set up and run the Vehicle POS (Point of Sale) system for auto parts businesses.

---

## 📋 System Requirements

### Minimum Requirements
- **PHP**: 8.2 or higher
- **MySQL**: 8.0 or higher
- **Composer**: Latest version
- **Web Server**: Apache/Nginx (XAMPP includes Apache)
- **Storage**: 500 MB free space
- **RAM**: 2 GB minimum

### Recommended Requirements
- **PHP**: 8.3
- **MySQL**: 8.0+
- **RAM**: 4 GB+
- **Storage**: 1 GB+

---

## 🛠️ Installation Methods

### Method 1: Automatic Setup (Recommended)

1. **Navigate to project directory**
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/vehical/VehiclePOS
   ```

2. **Make setup script executable**
   ```bash
   chmod +x setup.sh
   ```

3. **Run the setup script**
   ```bash
   ./setup.sh
   ```

4. **Follow the prompts**
   - The script will ask for database credentials
   - Choose whether to run migrations
   - Choose whether to seed initial data

### Method 2: Manual Setup

#### Step 1: Install Dependencies

```bash
cd /Applications/XAMPP/xamppfiles/htdocs/vehical/VehiclePOS
composer install
```

#### Step 2: Environment Configuration

1. Copy the example environment file:
   ```bash
   cp .env.example .env
   ```

2. Generate application key:
   ```bash
   php artisan key:generate
   ```

3. Edit `.env` file and configure database:
   ```env
   APP_NAME="Vehicle POS"
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vehicle_pos
   DB_USERNAME=root
   DB_PASSWORD=your_password_here
   ```

#### Step 3: Create Database

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "New" in the left sidebar
3. Database name: `vehicle_pos`
4. Collation: `utf8mb4_unicode_ci`
5. Click "Create"

Alternatively, use MySQL command line:
```bash
mysql -u root -p
CREATE DATABASE vehicle_pos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
EXIT;
```

#### Step 4: Run Migrations

```bash
php artisan migrate
```

#### Step 5: Seed Database

```bash
php artisan db:seed
```

This creates:
- Admin user and roles
- Default units (Piece, Set of 2, Set of 4, Dozen)
- System settings

#### Step 6: Create Storage Link

```bash
php artisan storage:link
```

#### Step 7: Clear Caches

```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

---

## 🚀 Running the Application

### Development Server

```bash
php artisan serve
```

Access at: `http://localhost:8000`

### Using XAMPP

1. Ensure Apache and MySQL are running in XAMPP
2. Place project in: `/Applications/XAMPP/xamppfiles/htdocs/vehical/VehiclePOS`
3. Access at: `http://localhost/vehical/VehiclePOS/public`

### Production Server

For production deployment:

1. Point web server document root to `public` directory
2. Set proper file permissions:
   ```bash
   chmod -R 755 storage bootstrap/cache
   ```
3. Update `.env`:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   ```
4. Run optimizations:
   ```bash
   php artisan config:cache
   php artisan route:cache
   php artisan view:cache
   ```

---

## 🔐 Default Login Credentials

After seeding the database:

**Admin Account:**
- Email: `admin@vehiclepos.com`
- Password: `password`

**⚠️ IMPORTANT:** Change this password immediately after first login!

---

## 🎯 Post-Installation Setup

### 1. Change Admin Password

1. Login with default credentials
2. Go to Settings → Users
3. Edit admin user
4. Update password

### 2. Configure Business Settings

1. Go to Settings → Business Settings
2. Update:
   - Business Name
   - Email
   - Phone
   - Address
   - Logo (optional)

### 3. Configure Invoice Settings

1. Go to Settings → Invoice Settings
2. Set:
   - Printer size (80mm, 60mm, A4)
   - Invoice prefix
   - Tax rate

### 4. Add Initial Data

1. **Categories**: Add product categories (Engine Parts, Brake Parts, etc.)
2. **Brands**: Add vehicle brands (Toyota, Honda, Ford, etc.)
3. **Suppliers**: Add your suppliers
4. **Products**: Add your inventory

---

## 📱 Features Overview

### Dashboard
- Real-time sales statistics
- Purchase tracking
- Expense monitoring
- Profit/loss calculations
- Low stock alerts
- Sales charts

### Product Management
- Add/Edit/Delete products
- SKU and barcode
- Multiple units (piece, sets, dozen)
- Stock tracking
- Price management
- Product images

### Sales & POS
- Point of sale interface
- Product search
- Cart management
- Customer selection
- Payment processing
- Receipt printing
- SMS/WhatsApp notifications

### Purchase Management
- Purchase orders
- Supplier tracking
- Stock updates
- Payment records

### Reports
- Sales reports
- Purchase reports
- Profit & Loss
- Stock reports
- Expense reports
- Trending products
- Activity logs

### User Management
- Role-based access
- Custom permissions
- User activity tracking

---

## 🔧 Troubleshooting

### Database Connection Error

**Problem:** Can't connect to database

**Solution:**
1. Check MySQL is running in XAMPP
2. Verify database credentials in `.env`
3. Ensure database exists
4. Check MySQL port (default: 3306)

### Storage Permission Error

**Problem:** Can't write to storage directory

**Solution:**
```bash
chmod -R 755 storage
chmod -R 755 bootstrap/cache
```

### Migration Error

**Problem:** Migration fails

**Solution:**
1. Check database connection
2. Drop and recreate database:
   ```bash
   php artisan migrate:fresh --seed
   ```

### Blank Page After Login

**Problem:** White/blank page

**Solution:**
1. Clear all caches:
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan view:clear
   ```
2. Check file permissions
3. Check PHP error logs

### 404 Error on Routes

**Problem:** Routes not working

**Solution:**
1. Clear route cache:
   ```bash
   php artisan route:clear
   ```
2. If using Apache, ensure `.htaccess` exists in `public` directory
3. Enable `mod_rewrite` in Apache

---

## 📊 Database Structure

### Main Tables

- **users**: System users
- **roles**: User roles with permissions
- **products**: Product inventory
- **categories**: Product categories
- **brands**: Product brands
- **units**: Measurement units
- **suppliers**: Supplier information
- **customers**: Customer records
- **purchases**: Purchase records
- **sales**: Sales transactions
- **expenses**: Expense tracking
- **settings**: System configuration
- **activity_logs**: Audit trail

---

## 🔄 Updates & Maintenance

### Backup Database

```bash
mysqldump -u root -p vehicle_pos > backup_$(date +%Y%m%d).sql
```

### Restore Database

```bash
mysql -u root -p vehicle_pos < backup_20250109.sql
```

### Clear All Caches

```bash
php artisan optimize:clear
```

### Check System Health

```bash
php artisan about
```

---

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review Laravel documentation: https://laravel.com/docs
3. Check migration files for database structure
4. Review model files for relationships

---

## 🎨 UI Features

- **Vehicle-themed design** with auto parts aesthetics
- **Fully responsive** - works on desktop, tablet, and mobile
- **Modern animations** - gear rotations, floating elements
- **Intuitive navigation** - collapsible sidebar with icons
- **Color-coded stats** - visual KPI cards with gradients
- **Interactive charts** - sales visualization with Chart.js

---

## 📝 Quick Reference

### Artisan Commands

```bash
# Run migrations
php artisan migrate

# Seed database
php artisan db:seed

# Clear caches
php artisan cache:clear

# Start server
php artisan serve

# Create user
php artisan tinker
>>> User::create(['name' => 'New User', 'email' => 'user@example.com', 'password' => bcrypt('password')]);
```

### File Locations

- **Views**: `resources/views/`
- **Controllers**: `app/Http/Controllers/`
- **Models**: `app/Models/`
- **Migrations**: `database/migrations/`
- **Routes**: `routes/web.php`
- **Config**: `.env`

---

## ✅ System Check

Before going live, verify:

- [ ] Database is properly configured
- [ ] All migrations have run
- [ ] Initial data is seeded
- [ ] Admin password is changed
- [ ] Business settings are updated
- [ ] Invoice settings are configured
- [ ] Storage permissions are correct
- [ ] Backups are configured
- [ ] SSL certificate is installed (for production)

---

**Congratulations! Your Vehicle POS system is ready to use! 🎉**
