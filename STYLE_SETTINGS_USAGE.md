# Style Settings Usage Guide

This guide explains how to use the Style Settings throughout the project.

## Overview

Style Settings are stored in the `wp_hotel_chain_system_settings` table in JSON format. The `StyleSettings` utility class provides easy access to these settings, and the `StyleSettingsService` automatically applies them to the frontend and admin.

## Quick Start

### 1. Using StyleSettings Utility Class

The `StyleSettings` class provides static methods to retrieve style settings:

```php
use HotelChain\Support\StyleSettings;

// Get all style settings
$all_settings = StyleSettings::get_all();

// Get a specific setting
$primary_font = StyleSettings::get( 'primary_font' );
$logo_url = StyleSettings::get( 'logo_url' );

// Get specific values (helper methods)
$primary_font = StyleSettings::get_primary_font();
$secondary_font = StyleSettings::get_secondary_font();
$logo_url = StyleSettings::get_logo_url();
$favicon_url = StyleSettings::get_favicon_url();

// Get font sizes
$base_size = StyleSettings::get_font_size( 'base' );    // 16px
$h1_size = StyleSettings::get_font_size( 'h1' );       // 32px

// Get button colors
$primary_color = StyleSettings::get_button_color( 'primary' );   // #1f88ff
$success_color = StyleSettings::get_button_color( 'success' );   // #10b981
```

### 2. Automatic Application

The `StyleSettingsService` automatically:
- Enqueues Google Fonts (primary and secondary)
- Injects CSS variables for fonts, font sizes, and button colors
- Adds favicon to `<head>`
- Applies styles to body and headings

**CSS Variables Available:**
```css
:root {
  --font-primary: 'Inter', sans-serif;
  --font-secondary: 'Playfair Display', serif;
  --font-size-base: 16px;
  --font-size-small: 14px;
  --font-size-large: 18px;
  --font-size-h1: 32px;
  --font-size-h2: 28px;
  --font-size-h3: 24px;
  --button-primary-color: #1f88ff;
  --button-secondary-color: #6b7280;
  --button-success-color: #10b981;
  --button-info-color: #3b82f6;
  --button-warning-color: #f59e0b;
  --button-danger-color: #ef4444;
}
```

## Usage Examples

### In PHP Templates

#### Display Logo
```php
<?php
use HotelChain\Support\StyleSettings;

$logo_url = StyleSettings::get_logo_url();
if ( ! empty( $logo_url ) ) :
    ?>
    <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" />
<?php endif; ?>
```

#### Use Custom Font Sizes
```php
<?php
use HotelChain\Support\StyleSettings;

$h1_size = StyleSettings::get_font_size( 'h1' );
?>
<h1 style="font-size: <?php echo esc_attr( $h1_size ); ?>px;">
    Custom Heading
</h1>
```

#### Use Button Colors
```php
<?php
use HotelChain\Support\StyleSettings;

$primary_color = StyleSettings::get_button_color( 'primary' );
?>
<button style="background-color: <?php echo esc_attr( $primary_color ); ?>;">
    Click Me
</button>
```

### In CSS

Use the CSS variables that are automatically injected:

```css
/* Use primary font */
.custom-text {
    font-family: var(--font-primary);
}

/* Use secondary font for headings */
.custom-heading {
    font-family: var(--font-secondary);
    font-size: var(--font-size-h2);
}

/* Use button colors */
.my-button {
    background-color: var(--button-primary-color);
    color: white;
}

.success-button {
    background-color: var(--button-success-color);
}
```

### In JavaScript

Access settings via PHP localization or inline data:

```php
// In your PHP file
wp_localize_script(
    'my-script',
    'styleSettings',
    array(
        'primaryFont' => StyleSettings::get_primary_font(),
        'buttonPrimaryColor' => StyleSettings::get_button_color( 'primary' ),
    )
);
```

```javascript
// In your JavaScript
const primaryColor = styleSettings.buttonPrimaryColor;
document.querySelector('.my-button').style.backgroundColor = primaryColor;
```

### In WordPress Admin

Style settings work automatically in admin pages too. The CSS variables are available in both frontend and admin.

## Available Settings

### Fonts
- `primary_font` - Primary font name (e.g., "Inter")
- `primary_font_url` - Google Fonts URL for primary font
- `secondary_font` - Secondary font name (e.g., "Playfair Display")
- `secondary_font_url` - Google Fonts URL for secondary font

### Branding
- `logo_id` - WordPress attachment ID for logo
- `logo_url` - Logo image URL
- `favicon_id` - WordPress attachment ID for favicon
- `favicon_url` - Favicon image URL

### Font Sizes
- `font_size_base` - Base font size (default: 16px)
- `font_size_small` - Small font size (default: 14px)
- `font_size_large` - Large font size (default: 18px)
- `font_size_h1` - H1 font size (default: 32px)
- `font_size_h2` - H2 font size (default: 28px)
- `font_size_h3` - H3 font size (default: 24px)

### Button Colors
- `button_primary_color` - Primary button color (default: #1f88ff)
- `button_secondary_color` - Secondary button color (default: #6b7280)
- `button_success_color` - Success button color (default: #10b981)
- `button_info_color` - Info button color (default: #3b82f6)
- `button_warning_color` - Warning button color (default: #f59e0b)
- `button_danger_color` - Danger button color (default: #ef4444)

## Cache Management

Settings are cached for performance. To clear the cache:

```php
use HotelChain\Support\StyleSettings;

StyleSettings::clear_cache();
```

The cache is automatically cleared when settings are saved or reset via the admin panel.

## Best Practices

1. **Always use the utility class** - Don't query the database directly
2. **Use CSS variables** - They're automatically available and performant
3. **Provide fallbacks** - The utility class provides defaults, but always have fallbacks in your code
4. **Escape output** - Always use `esc_attr()`, `esc_url()`, etc. when outputting settings
5. **Cache clearing** - Clear cache after programmatic updates to settings

## Example: Complete Template Usage

```php
<?php
use HotelChain\Support\StyleSettings;

$logo_url = StyleSettings::get_logo_url();
$primary_font = StyleSettings::get_primary_font();
$h1_size = StyleSettings::get_font_size( 'h1' );
$button_color = StyleSettings::get_button_color( 'primary' );
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Page</title>
</head>
<body style="font-family: var(--font-primary);">
    <?php if ( $logo_url ) : ?>
        <img src="<?php echo esc_url( $logo_url ); ?>" alt="Logo" />
    <?php endif; ?>
    
    <h1 style="font-size: <?php echo esc_attr( $h1_size ); ?>px;">
        Welcome
    </h1>
    
    <button 
        class="btn-primary" 
        style="background-color: var(--button-primary-color);"
    >
        Click Me
    </button>
</body>
</html>
```

