# Integration Guide: Painting Buy Button + Sales Dashboard

## Overview

This guide shows how the `[painting_buy_button]` shortcode now integrates seamlessly with the Sales Dashboard for dynamic, centrally-managed discount hints.

## What Changed

### Before (Hardcoded)
```php
// Old: Hardcoded text and logic
$show_special = false;
// ... complex size checking code ...
if ($show_special) {
    echo 'Special: Get 3 miniatures for only $69!';
}
```

**Problems:**
- Text hardcoded in PHP
- Styling hardcoded in HTML
- Had to edit code to change offers
- No central management
- Single offer only

### After (Dynamic)
```php
// New: Dynamic from Sales Dashboard
$discount_hint = monospace_get_volume_discount_hint($product, $product_id, $status);
```

**Benefits:**
- ✅ Text managed in admin dashboard
- ✅ Styling controlled via settings
- ✅ No code changes needed
- ✅ Central management of all offers
- ✅ Multiple discount types supported
- ✅ Works with scheduling

## How It Works

### 1. Product Page Display

When `[painting_buy_button id="123"]` shortcode runs:

```
1. Get product data
2. Check if available for purchase
3. Call monospace_get_volume_discount_hint()
   ├─ Check if hints enabled in dashboard
   ├─ Get volume rules from dashboard
   ├─ Match product against rule keys
   ├─ Find best offer to display
   └─ Format with template and styles
4. Display hint with product
```

### 2. Rule Matching Logic

Products match volume discount rules based on:

**Miniature Detection:**
```
Rule Key: "miniature"
Matches: Products with pa_size = 4x4
```

**Category Detection:**
```
Rule Key: "prints"
Matches: Products in "prints" category
```

**Attribute Detection:**
```
Rule Key: "attr:pa_format=limited"
Matches: Products with pa_format = limited
```

### 3. Template System

Templates from dashboard are populated with actual values:

**Fixed Bundle:**
```
Template: "Special: Get {qty} for only ${total}!"
Rule: {"type": "fixed_bundle", "qty": 3, "total": 99}
Output: "Special: Get 3 for only $99!"
```

**Buy X Get Y:**
```
Template: "Buy {buy}, get {get} free!"
Rule: {"type": "buy_x_get_y", "buy": 2, "get": 1}
Output: "Buy 2, get 1 free!"
```

## Setup Instructions

### Step 1: Configure Volume Discounts

1. Go to **WooCommerce → Settings → Sales Dashboard → Volume Discounts**
2. Enable Volume Discounts
3. Add rule for miniatures:
   - Rule key: `miniature`
   - Rule type: Fixed Bundle
   - Qty: 3
   - Total: 99
4. Save changes

### Step 2: Style the Hints

1. Go to **Badges & Messages** tab
2. Scroll to **Volume Discount Hints**
3. Check **"Show Discount Hints"**
4. Customize colors, sizes, spacing
5. Use live preview to see changes
6. Adjust templates if desired
7. Save changes

### Step 3: Test on Product Pages

1. Visit any product page with `[painting_buy_button]` shortcode
2. If product is a 4x4 miniature and available, hint displays
3. Verify styling matches your settings
4. Check mobile responsiveness

## Function Reference

### Main Functions

#### `monospace_get_volume_discount_hint($product, $product_id, $status)`
Returns formatted HTML for discount hint or empty string.

**Parameters:**
- `$product` - WooCommerce product object
- `$product_id` - Product ID
- `$status` - Availability status (sold, private, gallery, etc.)

**Returns:**
- HTML string with hint markup
- Empty string if no hint should show

#### `monospace_product_matches_volume_rule($product_id, $rule_key)`
Checks if product qualifies for a specific discount rule.

**Parameters:**
- `$product_id` - Product ID
- `$rule_key` - Rule identifier (miniature, category-slug, attr:taxonomy=term)

**Returns:**
- `true` if product matches
- `false` if no match

#### `monospace_format_volume_hint($rules, $rule_key)`
Formats the discount hint HTML with templates and styling.

**Parameters:**
- `$rules` - Array of discount rules for this product
- `$rule_key` - Rule identifier

**Returns:**
- Formatted HTML string

#### `monospace_get_hint_styles()`
Retrieves styling settings from Sales Dashboard.

**Returns:**
- Array with keys: `container`, `main`, `secondary`
- Each contains CSS style string

### Helper Functions

#### `monospace_product_has_attribute($product_id, $taxonomy, $term_slug)`
Checks if product has specific attribute term.

#### `monospace_output_hint_styles()`
Outputs CSS styles in site header (hooked to `wp_head`).

## Conditional Display

Hints only show when ALL conditions are met:

1. ✅ Hints enabled in dashboard (`msd_hints_enable = 'yes'`)
2. ✅ Volume discounts enabled (`msd_volume_enable = 'yes'`)
3. ✅ Product is available (not sold/private/gallery)
4. ✅ Product is in stock
5. ✅ Product matches a volume discount rule
6. ✅ Volume rule has valid discount configuration

## Styling Architecture

### Inline Styles
Individual hint elements use inline styles from dashboard settings:

```html
<div class="special-discount" style="...">
    <span class="msd-hint-main" style="...">Message</span>
    <span class="msd-hint-secondary" style="...">Details</span>
</div>
```

### Global Styles
Container styles output in `<head>`:

```css
.special-discount {
    background-color: #f0f9f0;
    border: 1px solid #33aa33;
    /* ... more from settings */
}
```

### Override Priority
1. Inline styles (highest)
2. Global styles from `wp_head`
3. Theme CSS
4. Custom CSS (with `!important` if needed)

## Common Customizations

### Show Different Hints by Category

```json
{
  "miniature": [
    {"type": "fixed_bundle", "qty": 3, "total": 99}
  ],
  "prints": [
    {"type": "percent_discount", "percent": 15}
  ],
  "originals": [
    {"type": "buy_x_get_y", "buy": 2, "get": 1}
  ]
}
```

Each category gets its own hint automatically!

### Seasonal Template Changes

**Summer Template:**
```
Bundle: "Summer Special: {qty} for ${total}!"
BXGY: "Hot Deal: Buy {buy} Get {get} Free!"
```

**Holiday Template:**
```
Bundle: "Holiday Bundle: {qty} items @ ${total}"
BXGY: "Gift Deal: {buy}+{get} Package!"
```

Just change in dashboard - no code edits!

### Match Brand Colors

Set all hint colors to match your brand:
```
Main Text: (your primary color)
Background: (your light tinted background)
Border: (your primary color)
```

Consistent branding across site!

## Troubleshooting

### Hint shows on wrong products
**Check:** Rule key matches product attributes/categories
**Fix:** Verify rule key in Volume Discounts tab

### Old hardcoded text still shows
**Check:** Theme or plugin caching
**Fix:** Clear all caches, hard refresh browser

### Styling doesn't match dashboard
**Check:** Settings saved? Cache cleared?
**Fix:** Save settings, clear cache, check browser console for errors

### Hint not showing at all
**Debug checklist:**
1. Volume Discounts enabled? ✓
2. Show Hints enabled? ✓
3. Product available? ✓
4. Product matches rule? ✓
5. Valid discount rule? ✓

## Performance Notes

### Efficiency
- Rule matching uses taxonomy queries (efficient)
- Styling cached via `get_option` (fast)
- No external API calls
- Minimal database queries

### Caching Considerations
If using object cache:
- Settings changes may not appear immediately
- Clear cache after dashboard changes
- Transients not used (intentional for real-time updates)

## Future Enhancements

Potential additions to consider:

1. **Position Control** - Choose where hint appears in shortcode output
2. **Device Targeting** - Different hints for mobile vs desktop
3. **Animation** - Subtle fade-in or pulse effects
4. **Icons** - Add emoji or icon before text
5. **Click Tracking** - Analytics on hint effectiveness
6. **Smart Hints** - "Add 1 more for discount" when close to threshold

## Integration with Other Features

### Works With Recurring Schedules
```
Setup:
- Volume Discount: 3 for $99 on miniatures
- Recurring Schedule: Every weekend

Result:
- Hint shows Friday-Sunday automatically
- Hides Monday-Thursday
- No manual intervention needed
```

### Works With Price Adjustments
```
Setup:
- Price Adjustment: +25% on all prints
- Volume Discount: 15% off prints

Result:
- Base price increased 25%
- Volume hint shows "Save 15%"
- Both rules apply correctly in cart
```

### Works With Sale Rules
```
Setup:
- Sale Rule: Taxonomy mode, miniatures allowed
- Volume Discount: 3 for $99 on miniatures

Result:
- Sales only active on miniatures
- Volume hint displays on miniatures
- Coordinated discount messaging
```

## Code Examples

### Custom Template in Functions.php

If you need to override hint generation:

```php
add_filter('msd_format_hint', function($html, $offer, $rule_key) {
    if ($rule_key === 'miniature') {
        return '<div class="custom-hint">Mini Sale!</div>';
    }
    return $html;
}, 10, 3);
```

### Add Custom CSS Classes

```php
add_filter('msd_hint_classes', function($classes) {
    $classes[] = 'my-custom-class';
    return $classes;
});
```

### Disable Hints Programmatically

```php
add_filter('msd_show_hint', function($show, $product_id) {
    // Don't show on specific products
    if (in_array($product_id, [123, 456])) {
        return false;
    }
    return $show;
}, 10, 2);
```

## Best Practices

1. **Test before going live** - Preview on staging site
2. **Mobile-first** - Design for smallest screens
3. **Clear messaging** - Ensure hint is easy to understand
4. **Brand consistency** - Match site colors/fonts
5. **Monitor performance** - Track conversion rates
6. **Update seasonally** - Refresh templates for holidays
7. **Document changes** - Export config before major updates

## Support Resources

- Main documentation: `README.md`
- Styling details: `STYLING-GUIDE.md`
- Setup walkthrough: `SETUP-GUIDE.md`
- New features: `NEW-FEATURES.md`

## Summary

The integration provides:
- ✅ Zero hardcoding - all managed in dashboard
- ✅ Live styling preview
- ✅ Template system for messages
- ✅ Multiple discount types
- ✅ Automatic product matching
- ✅ Scheduling compatibility
- ✅ Mobile responsive
- ✅ Performance optimized
- ✅ Easy to maintain

Change your discount strategy anytime from the dashboard - no developer needed!
