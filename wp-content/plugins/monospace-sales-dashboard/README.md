# monospace Sales Dashboard

Comprehensive sales management plugin for WooCommerce that unifies pricing rules, sale controls, and volume discounts.

## Installation

1. Upload the `monospace-sales-dashboard` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **WooCommerce → Settings → Sales Dashboard** to configure

## Features

### 🎯 Global Controls
- Master on/off switch for all sales functionality
- Two modes:
  - **Global**: Sales enabled everywhere except exclusions
  - **Taxonomy**: Sales only on selected categories/tags
- Conflict resolution priority settings
- One-time scheduling with start/end dates
- **NEW: Recurring schedules** - Daily, weekends, weekdays, specific days, monthly patterns

### 💰 Price Adjustments
- Global percentage increase/decrease (e.g., raise everything 40% or discount 20%)
- Apply to specific categories or tags
- Exclude commission products or specific categories
- Rounding options (up/down/nearest)
- Charm pricing (.99 endings)

### 🏷️ Sale Rules
- Control which products can have sales based on:
  - Categories (include/exclude)
  - Tags (include/exclude)
- Per-product overrides (force on/off)
- Show/hide crossed-out regular price

### 📦 Volume Discounts
Multiple discount types supported:
- **Fixed Bundle**: "3 for $99"
- **Buy X Get Y**: "Buy 2, get 1 free"
- **Second Half**: "Second item half price"
- **Percent Discount**: "20% off"
- **Fixed Discount**: "$5 off each"

**NEW: Visual Rule Builder** - Drag-and-drop interface to create rules without writing JSON!

Apply to:
- Product categories
- Miniatures (pa_size = 4x4)
- Custom attributes (e.g., `attr:pa_format=limited`)

### ⏰ Scheduling
**One-Time Schedule:**
- Set start/end dates for automatic sale activation
- Format: `YYYY-MM-DD HH:MM`
- Leave empty for immediate start or no end

**NEW: Recurring Schedules:**
- **Daily** - Every day during specified hours
- **Every Weekend** - Saturday and Sunday only
- **Weekdays Only** - Monday through Friday
- **Weekly** - Specific day each week (e.g., every Monday)
- **Monthly Date** - Specific date each month (e.g., 1st of month)
- **Monthly Weekday** - Specific occurrence (e.g., first Monday, last Friday)

### 🏅 Badges & Messages
- Customizable sale badge text
- Cart hints ("Add 1 more to save $X!")
- Volume discount labels in cart

### ✏️ Bulk Product Editor
**NEW: Apply overrides to multiple products at once**
- Filter products by:
  - Category
  - Tag
  - Search
  - Manual ID list
  - All products
- Set sale overrides in bulk
- Exclude products from rules
- Apply custom badges to multiple products

### 💾 Import/Export
**NEW: Backup and restore your configuration**
- Export settings as JSON file
- Choose which sections to export
- Include/exclude product overrides
- Import with merge or replace options
- Perfect for migrating between sites or backing up before changes

## Configuration

### Visual Rule Builder (NEW!)

Instead of writing JSON, use the visual interface:

1. Go to **Volume Discounts** tab
2. Click **"+ Add Rule Group"**
3. Enter a rule key:
   - `miniature` for products with pa_size = 4x4
   - `category-slug` for a product category
   - `attr:pa_taxonomy=term` for attribute matching
4. Click **"+ Add Rule"** to add discount types
5. Configure each rule with the dropdowns and fields
6. Save - JSON is generated automatically!

### Recurring Schedule Examples

**Weekend Flash Sales:**
- Pattern: Every Weekend
- Start Time: 00:00
- End Time: 23:59

**First Monday of Month:**
- Pattern: Specific Weekday Each Month
- Week of Month: First
- Day of Week: Monday
- Start/End times as needed

**Every Friday:**
- Pattern: Specific Day Each Week
- Day of Week: Friday
- Start Time: 00:00
- End Time: 23:59

### Bulk Product Editor Workflow (NEW!)

1. Go to **Bulk Editor** tab
2. **Step 1**: Select products
   - Choose filter method (category, tag, search, etc.)
   - Click "Load Products"
   - Review and check/uncheck products
3. **Step 2**: Choose override settings
   - Set sale overrides
   - Configure exclusions
   - Add custom badges
4. Click "Apply to Selected Products"

### Import/Export Workflow (NEW!)

**Export:**
1. Go to **Import/Export** tab
2. Check which sections to export
3. Optionally include product overrides
4. Click "Download Configuration File"
5. Save the .json file

**Import:**
1. Go to **Import/Export** tab
2. Upload a .json configuration file
3. Choose merge (keeps existing) or replace (overwrites all)
4. Click "Import Configuration"

### Rule Keys
- `miniature` - Products with pa_size = 4x4
- `category-slug` - Any product category slug
- `attr:pa_taxonomy=term` - Products with specific attribute

### Rule Types

1. **fixed_bundle**
   ```json
   {"type": "fixed_bundle", "qty": 3, "total": 99}
   ```

2. **buy_x_get_y**
   ```json
   {"type": "buy_x_get_y", "buy": 2, "get": 1}
   ```

3. **second_half**
   ```json
   {"type": "second_half", "factor": 0.5}
   ```

4. **percent_discount**
   ```json
   {"type": "percent_discount", "percent": 20}
   ```

5. **fixed_discount**
   ```json
   {"type": "fixed_discount", "amount": 5}
   ```

## Per-Product Overrides

Edit any product and scroll to the "Sales Dashboard Overrides" section:

- **Sale Override**: Force on/off regardless of global rules
- **Show Original Price**: Override display settings
- **Exclude from price adjustments**: Skip global % changes
- **Exclude from volume discounts**: Don't apply quantity pricing
- **Custom Badge Text**: Product-specific badge

## Priority Order

When multiple discounts could apply (default "best for customer"):

1. Product overrides take precedence
2. Volume discounts
3. Sale prices
4. Global price adjustments

Change this in **Global Controls → Conflict Resolution Priority**

## Admin Features

### Product List Column
The products list shows a "Sale Overrides" column with indicators:
- ● Sale = Forced on
- ○ Sale = Forced off
- ⊘ Price = Excluded from price adjustments
- ⊘ Volume = Excluded from volume discounts
- ★ = Custom badge set

## Compatibility

- **Requires**: WooCommerce 5.0+
- **Tested up to**: WooCommerce 8.5
- **PHP**: 7.4+
- **WordPress**: 5.8+

## Support

For issues or feature requests, contact Hens Breet at monospace.art

## Changelog

### 1.0.0
- Initial release
- Global price adjustments
- Taxonomy-based sale control
- Volume discount engine
- Product-level overrides
- Scheduling support
- Badge customization
