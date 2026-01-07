# 🔐 Vehicle POS - Login Credentials

## Default User Accounts

After running the database seeder, you can login with these accounts:

---

## 👑 Administrator Account

**Full system access with ALL permissions**

- **Email:** `admin@vehiclepos.com`
- **Password:** `admin123`

### Admin Permissions Include:
✅ Dashboard access
✅ User management (Create, Edit, Delete)
✅ Role management (Create, Edit, Delete)
✅ Product management (Full CRUD + Price updates)
✅ Category, Brand, Unit management
✅ Supplier management
✅ Customer management
✅ Purchase management
✅ Sales management
✅ POS access
✅ Quotation management
✅ Expense management
✅ All reports access
✅ System settings
✅ Activity log viewing
✅ Notification configuration

---

## 👔 Manager Account

**Limited management access**

- **Email:** `manager@vehiclepos.com`
- **Password:** `manager123`

### Manager Permissions Include:
✅ Dashboard access
✅ Product management (View, Create, Edit, Update Price)
✅ Category & Brand management (View, Create, Edit)
✅ Supplier & Customer management (View, Create, Edit)
✅ Purchase management (View, Create, Edit)
✅ Sales management (View, Create, Edit)
✅ POS access
✅ Expense viewing and creation
✅ Sales, Purchase, and Stock reports

❌ Cannot delete records
❌ Cannot manage users or roles
❌ Cannot access system settings
❌ Cannot view activity logs

---

## 💰 Cashier Account

**POS and sales access only**

- **Email:** `cashier@vehiclepos.com`
- **Password:** `cashier123`

### Cashier Permissions Include:
✅ Dashboard access
✅ POS access
✅ Sales (View, Create)
✅ Customer (View, Create)
✅ Product viewing

❌ Cannot edit products
❌ Cannot access purchases
❌ Cannot manage expenses
❌ Cannot access most reports
❌ Cannot access settings

---

## 🔒 Security Recommendations

### ⚠️ IMPORTANT - Before Production:

1. **Change all default passwords immediately**
2. **Delete or disable demo accounts (Manager & Cashier) if not needed**
3. **Use strong passwords** (minimum 12 characters, mix of uppercase, lowercase, numbers, symbols)
4. **Enable two-factor authentication** (if implemented)
5. **Review and customize role permissions** based on your business needs

### Password Requirements:
- Minimum 8 characters (12+ recommended)
- Mix of uppercase and lowercase letters
- Include numbers
- Include special characters
- Avoid common words or patterns

---

## 🎯 Quick Login Test

After setup, test each account:

1. **Test Admin Login:**
   - Login with admin@vehiclepos.com / admin123
   - Verify full menu access
   - Check Settings page
   - Verify User Management

2. **Test Manager Login:**
   - Login with manager@vehiclepos.com / manager123
   - Verify limited menu access
   - Confirm no Settings/Users menu

3. **Test Cashier Login:**
   - Login with cashier@vehiclepos.com / cashier123
   - Verify POS access only
   - Confirm minimal menu options

---

## 📝 Creating New Users

To create additional users:

1. Login as **Admin**
2. Go to **User Management** → **Create User**
3. Fill in user details
4. Assign appropriate role (Admin/Manager/Cashier)
5. Set initial password
6. Activate user

---

## 🔄 Password Reset

### Manual Password Reset (Admin):
1. Login as Admin
2. Go to User Management
3. Edit the user
4. Set new password
5. Save changes

### Via Database (Emergency):
```bash
php artisan tinker
>>> $user = App\Models\User::where('email', 'admin@vehiclepos.com')->first();
>>> $user->password = bcrypt('new_password');
>>> $user->save();
```

---

## 🛡️ Permission System

The system uses role-based permissions. Each role has specific permissions defined:

### Permission Format:
- `resource.action`
- Example: `products.create`, `sales.view`, `reports.sales`

### Available Actions:
- `view` - Can view/list resources
- `create` - Can create new resources
- `edit` - Can edit existing resources
- `delete` - Can delete resources
- `access` - Can access feature (e.g., POS)

### Adding Custom Permissions:

Edit the Role in database or through Role Management:
```php
'permissions' => [
    'custom.permission',
    'another.permission',
]
```

---

## 📊 User Summary

| Role | Email | Password | Access Level | Primary Use |
|------|-------|----------|--------------|-------------|
| **Admin** | admin@vehiclepos.com | admin123 | Full Access | System administration |
| **Manager** | manager@vehiclepos.com | manager123 | Management | Daily operations |
| **Cashier** | cashier@vehiclepos.com | cashier123 | POS Only | Sales counter |

---

## 🆘 Troubleshooting

### Can't login?
1. Check email and password are correct
2. Verify user is active: Check `is_active` in database
3. Clear browser cache and cookies
4. Try different browser

### Forgot password?
1. Use manual password reset method above
2. Or re-run seeder: `php artisan db:seed --class=DatabaseSeeder`

### Getting "Unauthorized" errors?
1. Check user's role permissions
2. Verify role is active
3. Re-login to refresh permissions

---

## 📌 Remember

- **Default admin password is `admin123`** - Change it immediately!
- All passwords are encrypted in the database
- User sessions expire after 120 minutes (configurable in .env)
- Activity is logged for all major actions

---

**Last Updated:** November 9, 2025  
**Version:** 1.0

---

For more information, see:
- `INSTALLATION.md` - Setup guide
- `README_VEHICLEPOS.md` - Full documentation
- `QUICK_REFERENCE.md` - Quick commands
