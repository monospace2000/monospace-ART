# Discount Hint Styling Guide

## Overview

The Sales Dashboard now includes comprehensive styling controls for volume discount hints that appear on product pages. All styling is managed through **WooCommerce → Settings → Sales Dashboard → Badges & Messages**.

## Features

### ✅ What You Can Control

**Colors:**
- Main text color
- Secondary text color
- Background color
- Border color

**Typography:**
- Main text size
- Secondary text size
- Font weight (normal, medium, semi-bold, bold)
- Text alignment (left, center, right)

**Layout:**
- Padding (space inside the box)
- Margin (space around the box)
- Border width
- Border radius (corner roundness)

**Content:**
- Message templates for each discount type
- Secondary text (e.g., "applied at checkout")
- Enable/disable hints globally

### 🎨 Live Preview

The settings page includes a **live preview** that updates as you change settings. See exactly how your hints will look before saving!

## Configuration Guide

### Basic Setup

1. Navigate to **WooCommerce → Settings → Sales Dashboard → Badges & Messages**
2. Scroll to **Volume Discount Hints**
3. Check **"Show Discount Hints"** to enable
4. Configure styling options below

### Color Schemes

#### Classic Green (Default)
```
Main Text Color: #33aa33
Secondary Text Color: #666666
Background Color: #f0f9f0
Border Color: #33aa33
```

#### Urgent Red
```
Main Text Color: #cc0000
Secondary Text Color: #666666
Background Color: #fff0f0
Border Color: #cc0000
```

#### Professional Blue
```
Main Text Color: #0066cc
Secondary Text Color: #666666
Background Color: #f0f6ff
Border Color: #0066cc
```

#### Luxury Gold
```
Main Text Color: #b8860b
Secondary Text Color: #8b7355
Background Color: #fffef0
Border Color: #b8860b
```

#### Minimal Grayscale
```
Main Text Color: #333333
Secondary Text Color: #666666
Background Color: #f5f5f5
Border Color: #cccccc
```

### Typography Styles

#### Bold & Prominent
```
Main Text Size: 16px
Font Weight: bold
Secondary Text Size: 12px
```

#### Subtle & Elegant
```
Main Text Size: 13px
Font Weight: 600 (semi-bold)
Secondary Text Size: 11px
```

#### Modern & Large
```
Main Text Size: 18px
Font Weight: bold
Secondary Text Size: 13px
```

### Layout Options

#### Compact
```
Padding: 6px 10px
Margin: 4px 0
Border Width: 1px
Border Radius: 3px
```

#### Spacious
```
Padding: 15px 20px
Margin: 10px 0
Border Width: 2px
Border Radius: 8px
```

#### Minimal (No Background)
```
Background Color: (empty/transparent)
Border Width: 0
Padding: 8px 0
Margin: 6px 0
```

#### Card Style
```
Padding: 12px 16px
Margin: 8px 0
Border Width: 1px
Border Radius: 6px
Background: #ffffff
Border Color: #e0e0e0
```

## Message Templates

### Template Variables

Each discount type has its own template with available variables:

#### Fixed Bundle
**Template:** `Special: Get {qty} for only ${total}!`

**Variables:**
- `{qty}` - Quantity in bundle (e.g., 3)
- `{total}` - Total price (e.g., 99)

**Examples:**
- `Bundle Deal: {qty} items for ${total}`
- `Save Big: {qty} for ${total}!`
- `Limited: Get {qty} @ ${total}`

#### Buy X Get Y Free
**Template:** `Buy {buy}, get {get} free!`

**Variables:**
- `{buy}` - Quantity to purchase
- `{get}` - Quantity received free

**Examples:**
- `Special Offer: Buy {buy} Get {get} Free!`
- `Purchase {buy}, receive {get} at no charge`
- `{buy}+{get} Deal Available`

#### Percent Discount
**Template:** `Save {percent}% on qualifying purchases!`

**Variables:**
- `{percent}` - Discount percentage

**Examples:**
- `{percent}% OFF Today Only!`
- `Take {percent}% off your order`
- `Special: {percent}% savings available`

### Secondary Text

The secondary text appears below the main message in a smaller font. Common options:

- `(Discount applied at checkout.)`
- `(Auto-applied in cart)`
- `(See cart for details)`
- `(Limited time offer)`
- `(While supplies last)`
- `(No code needed)`

## Integration with Painting Buy Button

The shortcode automatically:

1. ✅ Checks if product qualifies for discount
2. ✅ Retrieves styling from Sales Dashboard
3. ✅ Applies template with actual values
4. ✅ Respects enable/disable setting
5. ✅ Uses inline styles for consistent display

### Example Output

With default settings, a miniature (4x4) with a "3 for $99" bundle rule displays:

```html
<div class="special-discount" style="background-color:#f0f9f0;border:1px solid #33aa33;padding:10px 12px;margin:6px 0;border-radius:4px;text-align:left;">
    <span class="msd-hint-main" style="color:#33aa33;font-size:14px;font-weight:bold;">
        Special: Get 3 for only $99!
    </span>
    <br>
    <span class="msd-hint-secondary" style="color:#666666;font-size:12px;">
        (Discount applied at checkout.)
    </span>
</div>
```

## Best Practices

### Readability
- Keep main text 13-16px for optimal readability
- Use sufficient contrast between text and background
- Avoid overly bright or neon colors

### Visual Hierarchy
- Main text should be bolder/larger than secondary
- Secondary text typically 2-4px smaller than main
- Use color to differentiate importance

### Branding
- Match your site's color scheme
- Use consistent typography with your theme
- Consider your brand personality (playful vs. professional)

### Mobile Optimization
- Test on mobile devices
- Avoid excessively large padding on small screens
- Ensure text remains readable at smaller sizes

### Accessibility
- Maintain WCAG AA contrast ratios (4.5:1 minimum)
- Don't rely solely on color to convey information
- Keep font sizes readable (minimum 12px)

## Common Customizations

### Match Astra Theme
If using Astra theme, you might want:
```
Main Text Color: (match your primary color)
Font Weight: 600
Background: (match your content background or slightly tinted)
Border: 1px solid (your primary color)
```

### Subtle Professional Style
```
Main Text Color: #2c3e50
Secondary Text Color: #7f8c8d
Background Color: #ecf0f1
Border Color: #bdc3c7
Border Width: 1px
Border Radius: 3px
Padding: 10px 15px
Font Weight: 600
```

### Bold Call-to-Action Style
```
Main Text Color: #ffffff
Secondary Text Color: #f0f0f0
Background Color: #e74c3c
Border Color: #c0392b
Border Width: 0
Border Radius: 6px
Padding: 12px 20px
Font Weight: bold
Text Size: 15px
```

### Minimalist Modern
```
Main Text Color: #000000
Secondary Text Color: #888888
Background Color: (empty)
Border Color: (empty)
Border Width: 0
Padding: 8px 0
Font Weight: 500
Text Align: left
```

## Troubleshooting

### Hints not showing?
1. Check **Show Discount Hints** is enabled
2. Verify product matches a volume discount rule
3. Confirm product status allows purchase (not sold/private/gallery)
4. Check Volume Discounts are enabled globally

### Styling not applying?
1. Clear cache (browser and any caching plugins)
2. Check for theme CSS conflicts
3. Verify settings are saved
4. Try adding `!important` in custom CSS if needed

### Text cutoff or overflow?
1. Increase padding
2. Check text alignment
3. Verify container width in your theme
4. Test on different screen sizes

### Colors not matching preview?
1. Ensure color values are valid hex codes
2. Save settings after changes
3. Refresh the front-end page
4. Check browser console for CSS errors

## Advanced: Custom CSS Override

If you need additional styling control beyond the dashboard settings, add custom CSS to your theme:

```css
/* Override specific elements */
.special-discount {
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.msd-hint-main {
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.msd-hint-secondary {
    font-style: italic;
}

/* Mobile responsive */
@media (max-width: 768px) {
    .special-discount {
        padding: 8px 10px !important;
        font-size: 13px !important;
    }
}
```

Place this in:
- **Appearance → Customize → Additional CSS**, or
- Your child theme's `style.css`

## CSS Classes Reference

The hint markup uses these classes:

- `.special-discount` - Container div
- `.msd-hint-main` - Main message span
- `.msd-hint-secondary` - Secondary text span

Use these for targeted styling in custom CSS.

## Tips for Success

1. **Start with defaults** - Tweak gradually rather than changing everything at once
2. **Use the live preview** - See changes before committing
3. **Test on actual products** - Preview shows generic text, test with real discount rules
4. **Mobile first** - Design for mobile, then adjust for desktop if needed
5. **Brand consistency** - Match your site's existing design language
6. **User testing** - Ask others if hints are clear and noticeable
7. **A/B test** - Try different styles and track conversion rates

## Quick Reference: Default Values

```
Show Hints: Yes
Main Color: #33aa33
Secondary Color: #666666
Background: #f0f9f0
Border Color: #33aa33
Main Size: 14px
Secondary Size: 12px
Font Weight: bold
Text Align: left
Padding: 10px 12px
Margin: 6px 0
Border Radius: 4px
Border Width: 1px

Bundle Template: Special: Get {qty} for only ${total}!
BXGY Template: Buy {buy}, get {get} free!
Percent Template: Save {percent}% on qualifying purchases!
Secondary Text: (Discount applied at checkout.)
```

These provide a professional, conversion-optimized look that works across most themes and products.
