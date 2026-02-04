# 🚗 Vehicle POS - Quick Reference Card

## 🔑 Default Login
```
Email: admin@vehiclepos.com
Password: password
```

## 🚀 Quick Start Commands

```bash
# 1. Setup (one-time)
chmod +x setup.sh && ./setup.sh

# 2. Start Server
php artisan serve

# 3. Access Application
# Open: http://localhost:8000
```

## 📦 Manual Setup

```bash
# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Setup database (update .env first)
php artisan migrate
php artisan db:seed
php artisan storage:link

# Start server
php artisan serve
```

## 🗂️ Database Configuration

Edit `.env` file:
```env
DB_CONNECTION=mysql
DB_DATABASE=vehicle_pos
DB_USERNAME=root
DB_PASSWORD=your_password
```

## 🎨 Main Features

### Dashboard
- **URL**: `/dashboard`
- Real-time sales, purchases, expenses
- Low stock alerts
- Sales charts
- Quick actions

### Products
- **URL**: `/products`
- Add/Edit/Delete products
- Categories, Brands, Units
- Stock management
- Price updates

### POS
- **URL**: `/pos`
- Point of Sale interface
- Quick product search
- Cart management
- Checkout & receipts

### Sales
- **URL**: `/sales`
- Sales list
- Quotations
- Returns
- Payment tracking

### Purchases
- **URL**: `/purchases`
- Purchase orders
- Supplier management
- Payment records

### Reports
- **URL**: `/reports/*`
- Sales report
- Purchase report
- Profit & Loss
- Stock report
- Trending products

## 👥 User Roles

### Admin
- Full system access
- User management
- Settings control

### Manager
- Products, Sales, Purchases
- Reports access
- Limited settings

### Cashier
- POS access only
- Sales operations
- Customer management

## 🔧 Troubleshooting

### Can't Login?
```bash
php artisan migrate:fresh --seed
# Login: admin@vehiclepos.com / password
```

### Database Error?
```bash
# Check MySQL is running
# Verify .env database settings
# Recreate database
```

### Blank Page?
```bash
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Routes Not Working?
```bash
php artisan route:clear
```

## 📊 Default Data After Seeding

### Roles Created
1. **Admin** - Full access
2. **Manager** - Limited management
3. **Cashier** - POS only

### Units Created
1. **Piece (pc)** - Single item
2. **Set of 2 (set2)** - 2 pieces
3. **Set of 4 (set4)** - 4 pieces
4. **Dozen (dz)** - 12 pieces

### Settings Configured
- Business name, email, phone
- Currency (USD, $)
- Invoice printer size (80mm)
- Tax rate

## 🛠️ Useful Artisan Commands

```bash
# Database
php artisan migrate              # Run migrations
php artisan migrate:fresh        # Fresh migration
php artisan db:seed              # Seed database
php artisan migrate:fresh --seed # Fresh + seed

# Cache
php artisan cache:clear          # Clear cache
php artisan config:clear         # Clear config
php artisan view:clear           # Clear views
php artisan route:clear          # Clear routes
php artisan optimize:clear       # Clear all

# Application
php artisan serve                # Start server
php artisan about                # System info
php artisan storage:link         # Create storage link

# Interactive Shell
php artisan tinker               # Laravel shell
```

## 📁 Important File Locations

```
Config:          .env
Routes:          routes/web.php
Views:           resources/views/
Controllers:     app/Http/Controllers/
Models:          app/Models/
Migrations:      database/migrations/
Seeders:         database/seeders/
```

## 🎯 First Time Setup Checklist

- [ ] Run `setup.sh` or manual setup
- [ ] Create database `vehicle_pos`
- [ ] Run migrations
- [ ] Seed initial data
- [ ] Login with default credentials
- [ ] Change admin password
- [ ] Update business settings
- [ ] Configure invoice settings
- [ ] Add categories
- [ ] Add brands
- [ ] Add suppliers
- [ ] Add first product
- [ ] Test POS system

## 🔐 Security Notes

⚠️ **Before Production:**
1. Change admin password
2. Set `APP_DEBUG=false` in .env
3. Set `APP_ENV=production` in .env
4. Run `php artisan config:cache`
5. Set proper file permissions
6. Enable SSL certificate
7. Configure backups

## 📱 Mobile Access

The system is fully responsive:
- Access from any device
- Mobile-optimized POS
- Touch-friendly interface

## 🎨 Theme Colors

- **Primary**: Blue (#3b82f6)
- **Success**: Green (#10b981)
- **Warning**: Orange (#f59e0b)
- **Danger**: Red (#ef4444)
- **Info**: Indigo (#6366f1)

## 📞 Support Files

- `INSTALLATION.md` - Complete setup guide
- `README_VEHICLEPOS.md` - Full documentation
- `PROJECT_SUMMARY.md` - What's included
- This file - Quick reference

## ⚡ Quick Tips

1. **Use POS for fast sales** - `/pos`
2. **Check dashboard daily** - Monitor KPIs
3. **Set low stock alerts** - Prevent stockouts
4. **Regular backups** - Protect your data
5. **Update prices** - Use bulk update feature
6. **Review reports** - Weekly profit/loss check

## 🔄 Common Workflows

### Add New Product
1. Products → Add Product
2. Fill details (Name, SKU, Price)
3. Select Category, Brand, Unit
4. Set stock quantity
5. Save

### Make a Sale
1. Go to POS
2. Search & add products
3. Select customer (optional)
4. Process payment
5. Print/Send receipt

### Create Purchase Order
1. Purchases → Add Purchase
2. Select supplier
3. Add products
4. Enter quantities & prices
5. Save & update stock

### Generate Report
1. Reports → Select type
2. Set date range
3. View/Export data

---

**Need help? Check the full documentation in `INSTALLATION.md`**

*Vehicle POS v1.0*
