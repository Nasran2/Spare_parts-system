# Vehicle POS - Auto Parts Management System

A comprehensive Point of Sale (POS) system designed specifically for vehicle parts selling businesses. Built with Laravel and featuring a mobile-responsive vehicle-themed UI.

## Features

### 🚗 Core Functionality

- **Dashboard**: Real-time KPIs, sales charts, and business insights
- **User Management**: Role-based access control with customizable permissions
- **Product Management**: Complete inventory management with categories, brands, and units
- **Purchase Management**: Track purchases, suppliers, and payment records
- **Sales & POS**: Full-featured point of sale with quotations and invoicing
- **Customer & Supplier Management**: Maintain comprehensive records
- **Expense Tracking**: Monitor business expenses across categories
- **Reporting**: Detailed reports for sales, purchases, profit/loss, stock, and trends
- **Activity Logging**: Complete audit trail of system activities
- **Notifications**: SMS and WhatsApp integration for alerts

### 🎨 UI Features

- **Vehicle-Themed Design**: Auto parts inspired interface with gear animations
- **Mobile Responsive**: Fully optimized for all devices
- **Modern UI**: TailwindCSS with gradient effects and smooth animations
- **Intuitive Navigation**: Collapsible sidebar with organized menus

## Technologies Used

- **Backend**: PHP 8.2+ with Laravel 11
- **Frontend**: HTML5, TailwindCSS, JavaScript
- **Database**: MySQL 8.0+
- **Charts**: Chart.js
- **Icons**: Font Awesome 6

## Installation

### Prerequisites

- PHP >= 8.2
- Composer
- MySQL >= 8.0
- Node.js & NPM (optional, for asset compilation)

### Setup Steps

1. **Clone or navigate to the project directory**
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/vehical/VehiclePOS
   ```

2. **Install PHP dependencies**
   ```bash
   composer install
   ```

3. **Configure environment**
   ```bash
   cp .env.example .env
   ```

4. **Update database configuration in `.env`**
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=vehicle_pos
   DB_USERNAME=root
   DB_PASSWORD=
   ```

5. **Generate application key**
   ```bash
   php artisan key:generate
   ```

6. **Create database**
   - Create a new MySQL database named `vehicle_pos`
   - Or use your preferred database name (update .env accordingly)

7. **Run migrations**
   ```bash
   php artisan migrate
   ```

8. **Seed the database**
   ```bash
   php artisan db:seed
   ```

9. **Create storage symlink**
   ```bash
   php artisan storage:link
   ```

10. **Start the development server**
    ```bash
    php artisan serve
    ```

11. **Access the application**
    - URL: `http://localhost:8000`
    - Default Login:
      - Email: `admin@vehiclepos.com`
      - Password: `password`

## Default Data

After seeding, the system includes:

### Users & Roles
- **Admin Role**: Full system access
- **Manager Role**: Limited management access
- **Cashier Role**: POS and sales only
- **Admin User**: admin@vehiclepos.com / password

### Units
- Piece (pc)
- Set of 2 Pieces (set2)
- Set of 4 Pieces (set4)
- Dozen - 12 Pieces (dz)

### Settings
- Business name, email, phone, address
- Currency settings (USD, $)
- Invoice printer size (80mm)
- Tax rate configuration

## System Structure

### Database Tables

- `users` - System users
- `roles` - User roles with permissions
- `products` - Product inventory
- `categories` - Product categories
- `brands` - Product brands
- `units` - Measurement units
- `suppliers` - Supplier information
- `customers` - Customer records
- `purchases` & `purchase_items` - Purchase records
- `sales` & `sale_items` - Sales transactions
- `expenses` & `expense_categories` - Expense tracking
- `settings` - System configuration
- `activity_logs` - Audit trail

### Key Routes

- `/login` - User authentication
- `/dashboard` - Main dashboard
- `/products` - Product management
- `/pos` - Point of sale interface
- `/sales` - Sales records
- `/purchases` - Purchase records
- `/reports/*` - Various reports
- `/settings` - System settings

## Features by Module

### Dashboard
- Total sales, purchases, expenses, net profit
- Invoice due tracking
- Date range filters (Today, Yesterday, Last Week, Last Month, Custom)
- Sales chart (last 30 days)
- Top selling products
- Recent sales
- Low stock alerts
- Quick action buttons

### Product Management
- Add/Edit/Delete products
- SKU and barcode support
- Category and brand organization
- Multiple unit types (piece, sets, dozen)
- Cost and selling price tracking
- Stock quantity alerts
- Product images
- Price update functionality
- Label printing

### POS (Point of Sale)
- Real-time product search
- Shopping cart functionality
- Customer selection
- Payment processing
- Receipt generation
- SMS/WhatsApp receipt delivery
- Quotation creation

### Sales Management
- Sales list and details
- Quotation management
- Sales returns
- Payment status tracking
- Customer payment records

### Purchase Management
- Purchase orders
- Purchase returns
- Supplier payment tracking
- Stock updates

### Reports
- Sales report
- Purchase report
- Profit & Loss statement
- Stock report
- Stock adjustment report
- Trending products
- Expense report
- Activity log

### Settings
- Business information
- Invoice configuration
- Printer size selection
- Currency settings
- Tax configuration
- Notification templates

## Security Features

- Role-based access control (RBAC)
- Permission-based authorization
- Activity logging for audit trail
- Secure password hashing
- CSRF protection
- Session management

## Mobile Responsive

The entire system is fully responsive and optimized for:
- Desktop (1920px+)
- Laptop (1366px - 1920px)
- Tablet (768px - 1366px)
- Mobile (320px - 768px)

## Support & Documentation

For additional support or questions:
- Check the inline comments in the code
- Review the migration files for database structure
- Examine the model relationships
- Check the routes for available endpoints

## License

This is a proprietary POS system for vehicle parts businesses.

## Version

Current Version: 1.0.0

---

**Built with ❤️ for Vehicle Parts Businesses**
