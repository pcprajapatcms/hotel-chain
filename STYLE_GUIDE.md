# Style Management Guide

## Overview

This project uses **Tailwind CSS** as the primary styling framework, with custom CSS for WordPress admin overrides and design system tokens.

## Architecture

### 1. **Tailwind CSS Configuration**

**Location:** `tailwind.config.js`

```javascript
{
  content: ['./*.php', './app/**/*.php', './template-parts/**/*.php', './src/js/**/*.js'],
  theme: {
    extend: {
      colors: {
        brand: {
          50: '#f5fbff',
          100: '#e0f2ff',
          // ... up to 900
        }
      }
    }
  }
}
```

**Key Points:**
- Scans PHP and JS files for Tailwind classes
- Custom `brand` color palette (blue shades)
- Extends default Tailwind theme

### 2. **Source Files**

**Location:** `src/styles/`

#### `main.css` - Main Stylesheet
- **Purpose:** Base Tailwind directives and custom components
- **Structure:**
  ```css
  @tailwind base;      /* Base styles */
  @tailwind components; /* Component classes */
  @tailwind utilities;  /* Utility classes */
  ```

**Key Features:**
- Font face declarations
- CSS custom properties (variables)
- Component classes
- Base typography rules

#### `admin.css` - Admin Overrides
- **Purpose:** WordPress admin-specific overrides
- **Why:** WordPress admin has high CSS specificity, requires `!important`
- **Contains:**
  - Tailwind utility overrides with `!important`
  - WordPress admin menu styling
  - Responsive breakpoints
  - Button overrides

### 3. **Build Process**

**Location:** `package.json`

```json
{
  "scripts": {
    "dev": "tailwindcss -i ./src/styles/main.css -o ./assets/css/main.css --watch",
    "build": "tailwindcss -i ./src/styles/main.css -o ./assets/css/main.css --minify"
  }
}
```

**Process:**
1. Source: `src/styles/main.css` (with Tailwind directives)
2. Build: Compiles to `assets/css/main.css` (compiled CSS)
3. Watch mode: `npm run dev` for development
4. Production: `npm run build` for minified output

**PostCSS:** `postcss.config.js` processes Tailwind and Autoprefixer

### 4. **Asset Enqueuing**

**Location:** `app/Setup/Assets.php`

**Frontend:**
- Enqueues `assets/css/main.css`
- Enqueues `assets/js/app.js`

**Admin:**
- Enqueues `assets/css/main.css` (base Tailwind)
- Enqueues `src/styles/admin.css` (admin overrides)
- Conditionally loads editor scripts for TinyMCE

## Design System

### **Fonts & Typography**

#### Font Families

**CSS Variables (defined in `main.css`):**
```css
:root {
  --font-serif: 'TAN Aegean', serif;
  --font-sans: 'Helvetica Neue', sans-serif, system-ui, -apple-system, BlinkMacSystemFont, Arial;
}
```

**Usage:**
- **Headings (h1-h6):** Use `var(--font-serif)` - Uppercase, letter-spaced
- **Body text:** Use `var(--font-sans)` - Regular sans-serif

**In PHP Templates:**
```php
<!-- Using CSS variable -->
<div style="font-family: var(--font-serif);">Heading</div>
<div style="font-family: var(--font-sans);">Body text</div>

<!-- Using Tailwind classes -->
<h1 class="font-serif">Heading</h1>
<p class="font-sans">Body text</p>
```

**Custom Font:**
- **File:** `assets/fonts/TAN AEGEAN Regular.woff2`
- **Loaded via:** `@font-face` in `main.css`
- **Weight:** 400 (Regular only)

#### Typography Scale

**Tailwind Typography Classes:**
- `text-xs` - 0.75rem (12px)
- `text-sm` - 0.875rem (14px)
- `text-base` - 1rem (16px)
- `text-lg` - 1.125rem (18px)
- `text-xl` - 1.25rem (20px)
- `text-2xl` - 1.5rem (24px)
- `text-3xl` - 1.875rem (30px)

**Font Weights:**
- `font-normal` - 400
- `font-medium` - 500
- `font-semibold` - 600
- `font-bold` - 700

**Letter Spacing:**
- Headings automatically get `letter-spacing: 0.06em` (via base styles)
- Custom: `tracking-tight`, `tracking-wide`, etc.

### **Colors**

#### Brand Colors (Custom Palette)

**Location:** `tailwind.config.js`

```javascript
brand: {
  50: '#f5fbff',   // Lightest
  100: '#e0f2ff',
  200: '#b9e0ff',
  300: '#82c5ff',
  400: '#4aa8ff',
  500: '#1f88ff',  // Primary
  600: '#0f6fe5',
  700: '#0c58b3',  // Dark
  800: '#0f4a8f',
  900: '#123f74'   // Darkest
}
```

**Usage:**
```php
<div class="bg-brand-500 text-brand-700">Content</div>
<button class="bg-brand-600 hover:bg-brand-700">Button</button>
```

#### Design System Colors

**Defined in `main.css` component layer:**

**Primary Colors:**
- **Heading Primary:** `rgb(60, 56, 55)` - Dark brown/charcoal
- **Text Primary:** `rgb(61, 61, 68)` - Dark gray
- **Text Muted:** `rgb(122, 122, 122)` - Medium gray
- **Background Cream:** `rgb(240, 231, 215)` - Beige/cream
- **Border Light:** `rgb(196, 196, 196)` - Light gray

**Component Classes:**
```css
.hotel-video-library-heading-primary { color: rgb(60, 56, 55); }
.hotel-video-library-text-muted { color: rgb(122, 122, 122); }
.hotel-video-library-bg-cream { background-color: rgb(240, 231, 215); }
```

**Usage:**
```php
<h1 class="hotel-video-library-heading-primary">Title</h1>
<p class="hotel-video-library-text-muted">Description</p>
<div class="hotel-video-library-bg-cream">Container</div>
```

#### Standard Tailwind Colors

**Available color palettes:**
- `gray`, `slate`, `zinc`, `neutral`, `stone`
- `red`, `orange`, `amber`, `yellow`
- `green`, `emerald`, `teal`, `cyan`
- `blue`, `indigo`, `violet`, `purple`
- `pink`, `rose`, `fuchsia`

**Usage:**
```php
<div class="bg-green-200 border-green-400 text-green-900">Success</div>
<div class="bg-red-200 border-red-400 text-red-900">Error</div>
<div class="bg-blue-200 border-blue-400 text-blue-900">Info</div>
```

### **Buttons**

#### Standard Button Classes

**Tailwind Utility Approach:**
```php
<!-- Primary Action -->
<button class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900">
  Save
</button>

<!-- Secondary Action -->
<button class="px-4 py-2 bg-blue-200 border-2 border-blue-400 rounded text-blue-900">
  Cancel
</button>

<!-- Danger Action -->
<button class="px-4 py-2 bg-red-200 border-2 border-red-400 rounded text-red-900">
  Delete
</button>
```

**Component Class (`.btn`):**
```php
<a href="#" class="btn">Click Me</a>
```
- Uses brand colors
- Includes hover states
- Has focus styles

#### Button Patterns

**Common Button Styles:**
1. **Green (Success/Action):**
   - `bg-green-200 border-green-400 text-green-900`
   - `hover:bg-green-300`

2. **Blue (Info/Secondary):**
   - `bg-blue-200 border-blue-400 text-blue-900`
   - `hover:bg-blue-300`

3. **Red (Danger/Delete):**
   - `bg-red-200 border-red-400 text-red-900`
   - `hover:bg-red-300`

4. **Purple (Special):**
   - `bg-purple-200 border-purple-400 text-purple-900`
   - `hover:bg-purple-300`

5. **Gray (Neutral/Cancel):**
   - `bg-gray-200 border-gray-400 text-gray-900`
   - `hover:bg-gray-300`

**Button Sizes:**
- Small: `px-3 py-1 text-sm`
- Medium: `px-4 py-2 text-base` (default)
- Large: `px-6 py-3 text-lg`

**Full Width:**
- `w-full` - Full width on all screens
- `w-full sm:w-auto` - Full on mobile, auto on desktop

### **Spacing & Layout**

#### Spacing Scale

**Tailwind Spacing:**
- `p-1` = 0.25rem (4px)
- `p-2` = 0.5rem (8px)
- `p-3` = 0.75rem (12px)
- `p-4` = 1rem (16px)
- `p-6` = 1.5rem (24px)
- `p-8` = 2rem (32px)

**Common Patterns:**
- Container padding: `p-4 lg:p-8`
- Card padding: `p-4` or `p-6`
- Button padding: `px-4 py-2` or `px-6 py-3`

#### Grid & Flexbox

**Grid:**
```php
<!-- Responsive Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <!-- Items -->
</div>
```

**Flexbox:**
```php
<!-- Responsive Flex -->
<div class="flex flex-col sm:flex-row gap-3">
  <!-- Items -->
</div>
```

### **Borders & Shadows**

#### Border Patterns

**Common Border Styles:**
```php
<!-- Light Border -->
<div class="border border-solid border-gray-400">

<!-- Thick Border -->
<div class="border-2 border-gray-300">

<!-- Dashed Border -->
<div class="border border-dashed border-gray-400">
```

**Border Colors:**
- `border-gray-300` - Light gray
- `border-gray-400` - Medium gray
- `border-green-400` - Green accent
- `border-blue-400` - Blue accent

### **Responsive Design**

#### Breakpoints

**Tailwind Defaults:**
- `sm:` - 640px and up
- `md:` - 768px and up
- `lg:` - 1024px and up
- `xl:` - 1280px and up
- `2xl:` - 1536px and up

**Common Patterns:**
```php
<!-- Stack on mobile, side-by-side on desktop -->
<div class="flex flex-col md:flex-row gap-4">

<!-- Hide on mobile, show on desktop -->
<div class="hidden md:block">

<!-- Full width on mobile, auto on desktop -->
<button class="w-full md:w-auto">
```

## Best Practices

### 1. **Use Tailwind Classes First**
- Prefer utility classes over inline styles
- Use component classes for repeated patterns

### 2. **CSS Variables for Design Tokens**
- Use `var(--font-serif)` and `var(--font-sans)` for fonts
- Use component classes for brand colors

### 3. **Inline Styles for Dynamic Values**
- Only use inline `style=""` for:
  - Dynamic colors from database
  - Calculated values (percentages, etc.)
  - One-off overrides

### 4. **Admin CSS Overrides**
- Use `admin.css` for WordPress admin-specific fixes
- Always use `!important` for admin overrides
- Scope with `.wrap` when possible

### 5. **Component Classes**
- Create reusable component classes in `main.css` `@layer components`
- Use semantic naming: `.hotel-video-library-*`

### 6. **Color Consistency**
- Use design system colors from component classes
- Use Tailwind color scale for variations
- Document custom colors in `tailwind.config.js`

## File Structure

```
hotel-chain/
├── src/
│   └── styles/
│       ├── main.css          # Source: Tailwind + custom components
│       └── admin.css          # Admin overrides
├── assets/
│   ├── css/
│   │   └── main.css          # Compiled output (generated)
│   └── fonts/
│       └── TAN AEGEAN Regular.woff2
├── tailwind.config.js        # Tailwind configuration
├── postcss.config.js         # PostCSS configuration
└── package.json              # Build scripts
```

## Development Workflow

### 1. **Making Style Changes**

**For Tailwind utilities:**
- Edit PHP files directly with Tailwind classes
- Run `npm run dev` to watch for changes
- Changes compile automatically

**For custom components:**
- Edit `src/styles/main.css` in `@layer components`
- Run `npm run dev` to compile

**For admin overrides:**
- Edit `src/styles/admin.css`
- No build needed (direct CSS file)

### 2. **Adding New Colors**

**Option 1: Add to Tailwind Config**
```javascript
// tailwind.config.js
colors: {
  brand: { /* existing */ },
  custom: {
    500: '#your-color',
  }
}
```

**Option 2: Add Component Class**
```css
/* src/styles/main.css */
@layer components {
  .custom-color {
    color: rgb(60, 56, 55);
  }
}
```

### 3. **Adding New Fonts**

1. Add font file to `assets/fonts/`
2. Add `@font-face` in `src/styles/main.css`
3. Add CSS variable in `:root`
4. Use via `var(--font-name)` or Tailwind class

## Common Patterns

### Cards
```php
<div class="bg-white rounded p-4 border border-solid border-gray-400">
  <!-- Content -->
</div>
```

### Buttons
```php
<button class="px-4 py-2 bg-green-200 border-2 border-green-400 rounded text-green-900">
  Action
</button>
```

### Headings
```php
<h1 class="text-2xl font-bold mb-2" style="font-family: var(--font-serif);">
  Title
</h1>
```

### Responsive Grid
```php
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
  <!-- Items -->
</div>
```

### Responsive Flex
```php
<div class="flex flex-col sm:flex-row gap-3">
  <!-- Items -->
</div>
```

## Summary

- **Primary Framework:** Tailwind CSS
- **Build Tool:** Tailwind CLI via npm scripts
- **Custom Styles:** Component classes in `main.css`
- **Admin Overrides:** `admin.css` with `!important`
- **Fonts:** CSS variables (`--font-serif`, `--font-sans`)
- **Colors:** Brand palette in Tailwind config + design system classes
- **Buttons:** Utility classes with consistent patterns
- **Responsive:** Tailwind breakpoints (`sm:`, `md:`, `lg:`)

