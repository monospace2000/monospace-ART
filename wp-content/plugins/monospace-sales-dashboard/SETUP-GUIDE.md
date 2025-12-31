# Quick Setup Guide

## File Structure

Make sure your plugin folder is organized exactly like this:

```
wp-content/plugins/monospace-sales-dashboard/
├── monospace-sales-dashboard.php
├── README.md
├── includes/
│   ├── class-settings.php
│   ├── class-sale-control.php
│   ├── class-price-adjustments.php
│   ├── class-volume-discounts.php
│   └── class-product-overrides.php
└── assets/
    ├── admin.css
    └── admin.js
```

## Step-by-Step Setup

### 1. Upload & Activate

1. Upload the entire `monospace-sales-dashboard` folder to `/wp-content/plugins/`
2. Go to WordPress Admin → Plugins
3. Find "monospace Sales Dashboard" and click **Activate**

### 2. Access Settings

Go to: **WooCommerce → Settings → Sales Dashboard**

You'll see 6 tabs:
- Global Controls
- Price Adjustments
- Sale Rules
- Volume Discounts
- Scheduling
- Badges & Messages

### 3. Basic Configuration

#### Start with Global Controls:

1. ✅ Check **"Enable Sales System"**
2. Choose **Sale Mode**:
   - **Global** = Sales everywhere (you exclude specific categories)
   - **Taxonomy** = Sales only on categories/tags you select
3. Set **"Show Original Price"** = Yes (to show crossed-out prices)
4. Choose **Conflict Resolution Priority** (recommend "Best for customer")

**Save Changes**

#### Configure Sale Rules:

If you chose **Taxonomy mode**:
1. Select categories where sales are allowed
2. Select tags where sales are allowed

If you chose **Global mode**:
1. Select categories to EXCLUDE from sales
2. Select tags to EXCLUDE from sales

**Save Changes**

### 4. Test Basic Sales

1. Go to any product
2. Set a **Sale Price** in the product editor
3. Check if it's in an allowed category (taxonomy mode) or not excluded (global mode)
4. View the product on frontend - you should see the sale price

### 5. Add Volume Discounts (Optional)

1. Go to **Volume Discounts** tab
2. ✅ Check **"Enable Volume Discounts"**
3. Use the JSON editor to configure rules. Example:

```json
{
  "miniature": [
    {
      "type": "fixed_bundle",
      "qty": 2,
      "total": 69
    },
    {
      "type": "fixed_bundle",
      "qty": 3,
      "total": 99
    }
  ]
}
```

This creates "2 for $69" and "3 for $99" deals on miniatures.

**Save Changes**

### 6. Test Volume Discounts

1. Add 2 miniature products to cart
2. Cart should show the bundled price
3. Add a 3rd miniature
4. Price should adjust to 3-for-$99 rate

### 7. Set Global Price Adjustments (Optional)

If you want to raise/lower all prices:

1. Go to **Price Adjustments** tab
2. ✅ Check **"Enable Price Adjustments"**
3. Enter percentage (positive = increase, negative = decrease)
   - `25` = 25% increase
   - `-20` = 20% decrease
4. Select which categories to apply to (or leave empty for all)
5. Select categories to exclude (like commission work)
6. Choose rounding and charm pricing options

**Save Changes**

### 8. Schedule Sales (Optional)

1. Go to **Scheduling** tab
2. ✅ Check **"Enable Scheduling"**
3. Enter **Start Date**: `2025-01-15 00:00` (or leave empty for now)
4. Enter **End Date**: `2025-01-31 23:59` (or leave empty for no end)

**Save Changes**

### 9. Customize Badges (Optional)

1. Go to **Badges & Messages** tab
2. ✅ Check **"Enable Sale Badges"**
3. Customize badge text: `SAVE {percent}%`
4. Set cart hints for volume discounts

**Save Changes**

## Per-Product Overrides

For any individual product:

1. Edit the product
2. Scroll to **"Sales Dashboard Overrides"** section
3. Choose overrides:
   - Force sale ON or OFF
   - Show/hide original price
   - Exclude from price adjustments
   - Exclude from volume discounts
   - Set custom badge text

## Common Scenarios

### Scenario 1: Weekend Flash Sale

1. **Global Controls**: Enable sales, choose Taxonomy mode
2. **Sale Rules**: Select categories for the sale
3. **Scheduling**:
   - Start: `2025-01-10 00:00`
   - End: `2025-01-12 23:59`
4. Set sale prices on products in those categories
5. It will auto-start and auto-end!

### Scenario 2: Permanent Volume Discount on Miniatures

1. **Global Controls**: Enable system
2. **Volume Discounts**: Enable, add rules for "miniature"
3. **Sale Rules**: Not needed (volume works independently)
4. No scheduling needed

### Scenario 3: Raise Prices 40% Except Commission Work

1. **Global Controls**: Enable system
2. **Price Adjustments**: Enable, set `40` percent
3. **Price Adjustments**: Add "commission" to excluded categories
4. **Sale Rules**: Can leave at default
5. All prices increase 40% except commissions

### Scenario 4: Combined Strategy

You can run ALL of these at once:
- ✅ Volume discounts on miniatures
- ✅ 25% price increase on prints
- ✅ Flash sale on specific category
- ✅ Product overrides where needed

The "best for customer" priority ensures they always get the best deal!

## Troubleshooting

### Sales not showing?

1. Check **Global Controls** → "Enable Sales System" is checked
2. Check **Sale Rules** → product is in allowed category (taxonomy mode)
3. Check **Scheduling** → current date is within schedule
4. Check product has a sale price set

### Price adjustments not working?

1. Check **Price Adjustments** → "Enable Price Adjustments" is checked
2. Check product isn't in excluded categories
3. Check product doesn't have "_msd_exclude_price_adj" checked

### Volume discounts not applying?

1. Check **Volume Discounts** → "Enable Volume Discounts" is checked
2. Verify JSON syntax is valid (no errors in editor)
3. Check product matches the rule key (category slug, miniature, or attribute)
4. Test with exact quantities specified in rules

### Conflicts between rules?

Check **Global Controls** → "Conflict Resolution Priority"
- "Best for customer" = highest discount wins
- "Product override" = respects hierarchy
- "Volume always wins" = volume discounts take precedence

## Next Steps

Once basic setup works:

1. Add more volume discount rules for different products
2. Schedule seasonal sales
3. Customize badge messages
4. Use per-product overrides for exceptions
5. Monitor which discounts perform best

## Need Help?

Check the main README.md for detailed documentation on:
- JSON rule format
- All discount types
- Attribute matching
- Advanced configurations
