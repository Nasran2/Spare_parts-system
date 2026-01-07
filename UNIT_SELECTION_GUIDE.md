# POS Unit Selection Feature

## Overview
The POS system now supports selecting different unit types when adding products to the cart. This allows selling the same product in different units (e.g., pieces, sets, dozens) based on the product's configuration.

## How It Works

### 1. Product Configuration
Each product has a `visible_units` field (array) that stores the IDs of units available for that product. This is configured when creating/editing products.

Example:
```php
Product {
    id: 1,
    name: "Brake Pad",
    unit_id: 1, // Base unit (e.g., Piece)
    visible_units: [1, 3, 5], // Piece, Set, Dozen
    selling_price: 500.00
}
```

### 2. Unit Selection Modal
When clicking a product in POS:
1. A modal appears showing only the units from `visible_units`
2. User selects desired unit type
3. User enters quantity
4. Product is added to cart with unit information

### 3. Cart Handling
Products with different units are treated as separate cart items:
- Cart Key Format: `{product_id}_unit_{unit_id}`
- Example: Product #5 with Unit #3 = cart key `5_unit_3`
- Same product with different units = separate line items

### 4. Display
Cart items show the unit in the product name:
- "Brake Pad (Piece)" - Qty: 2
- "Brake Pad (Set)" - Qty: 1

## Files Modified

### Backend
- **app/Http/Controllers/POSController.php**
  - `addToCart()` - Handles unit parameter and creates unique cart keys
  - `updateQty()` - Updated to use cart_key instead of product_id
  - `removeItem()` - Updated to use cart_key instead of product_id

### Frontend
- **resources/views/pos/index.blade.php**
  - Added unit selection modal HTML
  - Updated product cards to include unit data attributes
  - Modified cart rendering to use cart_key
  - Added modal JavaScript for unit selection
  - Exposed `renderCart()` to window object for modal access

## Usage

### For End Users
1. Click any product in POS
2. Select the desired unit type (if configured)
3. Enter quantity
4. Click "Add to Cart"

### For Products Without Visible Units
If a product has no `visible_units` configured, clicking it adds directly to cart without showing the modal (backwards compatible).

## Technical Details

### Cart Item Structure
```javascript
{
    id: 5,                    // Product ID
    cart_key: "5_unit_3",     // Unique cart identifier
    name: "Brake Pad (Set)",  // Product name with unit
    price: 500.00,            // Unit price
    qty: 2,                   // Quantity
    unit: {                   // Unit details
        id: 3,
        name: "Set"
    }
}
```

### API Endpoints
- **POST /pos/cart/add**
  - Parameters: `product_id`, `quantity`, `unit` (optional)
  - Returns: Updated cart object
  
- **POST /pos/cart/update**
  - Parameters: `cart_key`, `qty`
  - Returns: Updated cart object
  
- **POST /pos/cart/remove**
  - Parameters: `cart_key`
  - Returns: Updated cart object

## Future Enhancements
Potential improvements:
1. Different prices per unit type
2. Stock tracking per unit
3. Unit conversion for stock management
4. Bulk unit operations
5. Default unit selection based on customer preferences

## Notes
- The base unit (`unit_id`) is still used for stock management
- `visible_units` only affects POS display options
- Stock is decremented by quantity regardless of unit (no automatic conversion yet)
- Unit information is stored in sale items for reporting
