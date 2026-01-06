# New Features - Version 1.0

## 🎨 Visual Rule Builder

**Location:** Volume Discounts tab

### What It Does
Replaces manual JSON editing with an intuitive drag-and-drop interface for creating volume discount rules.

### How To Use
1. Navigate to **WooCommerce → Settings → Sales Dashboard → Volume Discounts**
2. Click **"+ Add Rule Group"**
3. Enter a rule key (e.g., `miniature`, `prints`, `attr:pa_size=4x4`)
4. Click **"+ Add Rule"** for each discount you want
5. Select discount type from dropdown
6. Fill in the fields (quantities, amounts, percentages)
7. Click **"+ Add Rule"** to add more discounts to the same group
8. Click **"+ Add Rule Group"** to create rules for different products
9. Save changes - JSON is generated automatically!

### Rule Types Available
- **Fixed Bundle** - Set quantity and total price (e.g., 3 for $99)
- **Buy X Get Y Free** - Specify buy and get quantities
- **Second Half Price** - Automatically applies 50% to every second item
- **Percent Discount** - Set percentage off
- **Fixed Discount** - Set dollar amount off each item

### Benefits
- No JSON syntax errors
- Visual organization of rules
- Easy to add, edit, or remove rules
- Instant validation
- More intuitive than code

---

## 🔄 Recurring Schedules

**Location:** Scheduling tab

### What It Does
Automatically enables/disables sales based on recurring patterns, not just one-time dates.

### Patterns Available

#### 1. Daily
Sales active every day during specified hours.

**Use Case:** Daily flash sales from 9 AM - 5 PM

#### 2. Every Weekend
Sales active Saturday and Sunday only.

**Use Case:** Weekend-only promotions

#### 3. Weekdays Only
Sales active Monday through Friday.

**Use Case:** Business day specials

#### 4. Specific Day Each Week
Choose any day of the week (Monday, Tuesday, etc.)

**Use Case:** "Taco Tuesday" style promotions

#### 5. Specific Date Each Month
Choose a date (1-31) that repeats monthly.

**Use Case:** First of the month sales, payday promotions

#### 6. Specific Weekday Each Month
Choose first/second/third/fourth/last occurrence of a weekday.

**Use Cases:**
- First Monday of each month
- Last Friday of each month
- Third Thursday (perfect for "Third Thursday" events)

### Configuration
1. Go to **Scheduling → Recurring Schedule**
2. Check **"Enable Recurring Schedule"**
3. Select **Pattern**
4. Configure pattern-specific options (day, week, date)
5. Set **Start Time** and **End Time** (24-hour format)
6. Save

### Example: Weekend Flash Sale
```
Pattern: Every Weekend
Start Time: 00:00
End Time: 23:59
```
Sales automatically enable Friday night at midnight, disable Sunday night at midnight. Every week. Forever.

### Example: First Monday Member Day
```
Pattern: Specific Weekday Each Month
Week of Month: First
Day of Week: Monday
Start Time: 00:00
End Time: 23:59
```

### Combined with One-Time Schedules
You can use BOTH:
- One-time schedule: Holiday sale Dec 20-26
- Recurring schedule: Weekend sales every Sat-Sun

If either is active, sales are enabled!

---

## ✏️ Bulk Product Editor

**Location:** Bulk Editor tab

### What It Does
Apply sale overrides to dozens or hundreds of products at once instead of editing each individually.

### Filter Methods

#### By Category
Select a category, load all products in that category.

**Use Case:** Apply overrides to all products in "Prints" category

#### By Tag
Select a tag, load all tagged products.

**Use Case:** Mark all "featured" products

#### Search Products
Type keywords to find specific products.

**Use Case:** Find all products with "canvas" in the name

#### Manual Selection
Enter product IDs (comma or line-separated).

**Use Case:** Specific list of products from external source

#### All Products
Load every product in your store (use with caution on large stores).

**Use Case:** Store-wide policy changes

### Bulk Actions Available

1. **Sale Override**
   - Force ON - Always on sale
   - Force OFF - Never on sale
   - Reset - Use global rules

2. **Show Original Price**
   - Show crossed-out price
   - Hide crossed-out price
   - Reset - Use global setting

3. **Exclude from Price Adjustments**
   - Check to skip global % increases/decreases

4. **Exclude from Volume Discounts**
   - Check to skip quantity-based pricing

5. **Custom Badge**
   - Apply same badge text to all selected products

### Workflow
1. Choose filter method
2. Load products (review the list)
3. Uncheck any you don't want to affect
4. Choose override settings
5. Click "Apply to Selected Products"
6. Confirmation shows how many were updated

### Use Cases

**Scenario 1: Commission Work**
- Filter: Category = "Commissions"
- Action: Exclude from price adjustments ✓
- Action: Sale Override = Force OFF

**Scenario 2: Holiday Limited Edition**
- Filter: Tag = "holiday-2025"
- Action: Custom Badge = "LIMITED TIME"
- Action: Sale Override = Force ON

**Scenario 3: Clearance Section**
- Filter: Category = "Clearance"
- Action: Sale Override = Force ON
- Action: Show Original Price = Show crossed-out

---

## 💾 Import/Export Configuration

**Location:** Import/Export tab

### What It Does
Backup all your settings as a JSON file. Restore them later or transfer to another site.

### Export Options

**Sections You Can Export:**
- ✓ Global Controls (modes, priorities)
- ✓ Price Adjustments (percentages, rounding)
- ✓ Sale Rules (categories, tags)
- ✓ Volume Discounts (all rules)
- ✓ Scheduling (one-time and recurring)
- ✓ Badges & Messages (text customization)
- ✓ Product Overrides (optional - can create large files)

**File Format:** JSON (human-readable, version-controlled)

### Export Workflow
1. Go to **Import/Export** tab
2. Check which sections to include
3. Optionally include product overrides
4. Click "Download Configuration File"
5. File downloads: `msd-config-2025-01-15-143022.json`
6. Store safely or commit to version control

### Import Options

**Merge Mode:**
- Combines imported settings with existing
- Safer option - won't delete what you have
- Good for importing just a few sections

**Replace Mode:**
- Completely overwrites current settings
- Use when restoring a full backup
- Warning shown before proceeding

### Import Workflow
1. Go to **Import/Export** tab
2. Click "Choose File" and select .json
3. Choose Merge or Replace
4. Click "Import Configuration"
5. Settings are restored immediately

### Use Cases

**Scenario 1: Backup Before Changes**
1. Export all settings
2. Make experimental changes
3. If something breaks, import the backup

**Scenario 2: Seasonal Templates**
1. Configure summer sale rules
2. Export as `summer-config.json`
3. Configure fall sale rules
4. Export as `fall-config.json`
5. Each season, import the relevant template

**Scenario 3: Staging to Production**
1. Test new rules on staging site
2. Export configuration
3. Import to production site
4. Rules instantly applied

**Scenario 4: Multi-Site Network**
1. Configure one site perfectly
2. Export settings
3. Import to all other sites in network
4. Maintain consistency across properties

### File Structure
The JSON file includes:
```json
{
  "version": "1.0.0",
  "timestamp": "2025-01-15 14:30:22",
  "site_url": "https://monospace.art",
  "global": { ... },
  "price_adjustments": { ... },
  "sale_rules": { ... },
  "volume_discounts": { ... },
  "scheduling": { ... },
  "badges": { ... },
  "products": { ... }
}
```

Human-readable and version-control friendly!

---

## 🔧 Technical Improvements

### Enhanced Scheduling System
- Combines one-time AND recurring schedules
- Supports complex patterns like "last Friday"
- Proper timezone handling with `current_time()`
- Efficient caching to avoid repeated calculations

### Optimized Performance
- Static caching in rule matching
- Reduced database queries
- Batch product operations in bulk editor
- AJAX loading for large product lists

### Better Admin UX
- Field visibility toggling based on selections
- Live validation (JSON, dates, times)
- Visual feedback (spinners, success/error colors)
- Helpful descriptions and examples

### HPOS Compatibility
- Declared compatible with WooCommerce High-Performance Order Storage
- No compatibility warnings in WooCommerce admin

---

## 📝 Updated Settings Layout

The settings now have **8 tabs** instead of 6:

1. **Global Controls** - Master switches, modes, priorities
2. **Price Adjustments** - Global percentage changes
3. **Sale Rules** - Taxonomy-based control
4. **Volume Discounts** - Visual rule builder + JSON fallback
5. **Scheduling** - One-time + recurring schedules
6. **Badges & Messages** - Display customization
7. **Bulk Editor** - ⭐ NEW - Multi-product operations
8. **Import/Export** - ⭐ NEW - Backup/restore

---

## 🚀 Quick Start Guide

### For Visual Rule Building
1. Enable Volume Discounts
2. Click "+ Add Rule Group"
3. Set rule key (e.g., `miniature`)
4. Click "+ Add Rule"
5. Choose "Fixed Bundle", set 3 for $99
6. Save!

### For Recurring Weekend Sales
1. Go to Scheduling tab
2. Enable Recurring Schedule
3. Pattern: Every Weekend
4. Times: 00:00 to 23:59
5. Save - now sales activate every weekend automatically!

### For Bulk Override Application
1. Go to Bulk Editor
2. Filter by Category
3. Load products
4. Choose override (e.g., Force ON)
5. Apply!

### For Configuration Backup
1. Go to Import/Export
2. Check all boxes
3. Download Configuration File
4. Store somewhere safe!

---

## 💡 Pro Tips

1. **Export before major changes** - Always have a rollback option
2. **Use recurring schedules** instead of manually changing settings every week
3. **Bulk editor saves hours** - Don't edit 100 products individually
4. **Visual builder prevents errors** - No more JSON syntax mistakes
5. **Combine features** - Recurring schedule + bulk overrides + volume discounts = powerful automation

---

## 🐛 Troubleshooting

### Visual Rule Builder not saving?
- Check that each rule group has a unique key
- Make sure numeric fields have valid numbers
- Look for browser console errors

### Recurring schedule not working?
- Verify Enable Recurring Schedule is checked
- Check start/end times are in 24-hour format (HH:MM)
- For monthly patterns, ensure day/week selections are saved

### Bulk editor not loading products?
- Check category/tag selections are valid
- For manual IDs, use comma or line separation
- Large stores may take time to load

### Import failing?
- Verify file is valid JSON
- Check file was exported from same or compatible version
- Try merge mode instead of replace
