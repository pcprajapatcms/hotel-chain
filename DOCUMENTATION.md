# Hotel Chain WordPress Theme - Technical Documentation

## Table of Contents

1. [Overview](#overview)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Architecture](#architecture)
5. [Database Schema](#database-schema)
6. [Service Providers](#service-providers)
7. [Repositories](#repositories)
8. [Admin Pages](#admin-pages)
9. [Frontend Pages](#frontend-pages)
10. [Custom Roles](#custom-roles)
11. [Guest Expiration System](#guest-expiration-system)
12. [URL Routing](#url-routing)
13. [AJAX Endpoints](#ajax-endpoints)
14. [Asset Management](#asset-management)
15. [Video Request Workflow](#video-request-workflow)
16. [File Structure](#file-structure)

---

## Overview

**Hotel Chain** is a custom object-oriented WordPress theme designed for managing a hotel chain video distribution platform. It allows administrators to create hotel accounts, upload videos, and assign videos to specific hotels. Hotel users can browse the video library, request access to videos, and manage their assigned videos.

### Key Features

- **Hotel Account Management**: Create, edit, and manage hotel accounts with unique registration URLs
- **Video Library**: Upload, categorize, and manage videos with metadata
- **Video Assignment System**: Assign videos to hotels with request/approval workflow
- **Dual Status Management**: Admin-controlled and hotel-controlled video status
- **Custom User Roles**: Hotel and Guest roles with specific capabilities
- **AJAX-Powered UI**: Smooth, no-reload interactions for video management
- **Tailwind CSS**: Modern, responsive UI styling
- **PSR-4 Autoloading**: Clean, organized PHP code structure

---

## Requirements

- **WordPress**: 6.6 or higher (tested up to 6.7)
- **PHP**: 8.0 or higher
- **Node.js**: For Tailwind CSS compilation

---

## Installation

1. Clone or copy the theme to `wp-content/themes/hotel-chain`
2. Run `composer install` to install PHP dependencies
3. Run `npm install` to install Node dependencies
4. Run `npm run build` to compile Tailwind CSS (or `npm run dev` for watch mode)
5. Activate the theme in WordPress Admin > Appearance > Themes
6. Database tables are automatically created on theme activation

---

## Architecture

The theme follows a **Service Provider Pattern** where each feature is encapsulated in a service provider class that implements `ServiceProviderInterface`.

### Main Entry Point

- File: `functions.php`
- Loads Composer autoloader and initializes `HotelChain\Theme::init()`

### Theme Bootstrap

- File: `app/Theme.php`
- Registers all service providers in the constructor
- Calls `register()` method on each provider during boot

---

## Database Schema

The theme creates 5 custom database tables (prefixed with `wp_hotel_chain_`):

### 1. Hotels Table (`wp_hotel_chain_hotels`)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| user_id | bigint(20) | WordPress user ID |
| hotel_code | varchar(50) | Unique hotel code |
| hotel_name | varchar(255) | Hotel name |
| hotel_slug | varchar(255) | URL-friendly slug |
| contact_email | varchar(255) | Contact email |
| contact_phone | varchar(50) | Contact phone |
| address | varchar(255) | Street address |
| city | varchar(100) | City |
| country | varchar(100) | Country |
| access_duration | int(11) | Guest access duration in days |
| license_start | datetime | License start date |
| license_end | datetime | License end date |
| registration_url | varchar(500) | Guest registration URL |
| landing_url | varchar(500) | Hotel landing page URL |
| status | varchar(20) | active, inactive, suspended |
| created_at | datetime | Creation timestamp |
| updated_at | datetime | Last update timestamp |

### 2. Hotel Video Assignments Table (`wp_hotel_chain_hotel_video_assignments`)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| hotel_id | bigint(20) | Foreign key to hotels |
| video_id | bigint(20) | Internal video ID |
| assigned_by | bigint(20) | User ID who assigned |
| assigned_at | datetime | Assignment timestamp |
| status | varchar(20) | pending, active, inactive (admin-controlled) |
| status_by_hotel | varchar(20) | active, inactive (hotel-controlled) |

### 3. Video Metadata Table (`wp_hotel_chain_video_metadata`)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| video_id | bigint(20) | Internal video ID |
| video_file_id | bigint(20) | WordPress attachment ID |
| slug | varchar(255) | URL-friendly slug |
| title | varchar(255) | Video title |
| description | longtext | Video description |
| category | varchar(255) | Category name |
| tags | text | Comma-separated tags |
| thumbnail_id | bigint(20) | Thumbnail attachment ID |
| thumbnail_url | varchar(500) | Thumbnail URL |
| duration_seconds | int(11) | Video duration |
| duration_label | varchar(50) | Formatted duration (e.g., "5:30") |
| file_size | bigint(20) | File size in bytes |
| file_format | varchar(20) | File format (e.g., "mp4") |
| resolution_width | int(11) | Video width |
| resolution_height | int(11) | Video height |
| default_language | varchar(50) | Default language |
| total_views | int(11) | View count |
| total_completions | int(11) | Completion count |
| avg_completion_rate | decimal(5,2) | Average completion percentage |
| created_at | datetime | Upload timestamp |
| updated_at | datetime | Last update timestamp |

### 4. Guests Table (`wp_hotel_chain_guests`)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| hotel_id | bigint(20) | Foreign key to hotels |
| user_id | bigint(20) | WordPress user ID (if registered) |
| guest_code | varchar(50) | Unique guest code |
| first_name | varchar(100) | Guest first name |
| last_name | varchar(100) | Guest last name |
| email | varchar(255) | Guest email |
| phone | varchar(50) | Guest phone |
| registration_code | varchar(50) | Hotel registration code used |
| access_start | datetime | Access start date |
| access_end | datetime | Access end date |
| status | varchar(20) | active, expired, revoked |
| created_at | datetime | Registration timestamp |
| updated_at | datetime | Last update timestamp |

### 5. Video Views Table (`wp_hotel_chain_video_views`)

| Column | Type | Description |
|--------|------|-------------|
| id | bigint(20) | Primary key |
| video_id | bigint(20) | Video ID |
| hotel_id | bigint(20) | Hotel ID |
| guest_id | bigint(20) | Guest ID |
| user_id | bigint(20) | WordPress user ID |
| viewed_at | datetime | View timestamp |
| view_duration | int(11) | Seconds watched |
| completion_percentage | decimal(5,2) | Completion percentage |
| completed | tinyint(1) | Whether video was completed |
| ip_address | varchar(45) | Viewer IP address |
| user_agent | varchar(255) | Browser user agent |

---

## Service Providers

All service providers implement `ServiceProviderInterface` with a `register()` method.

### Setup Providers

| Provider | File | Purpose |
|----------|------|---------|
| ThemeSupport | app/Setup/ThemeSupport.php | Register WordPress theme supports |
| Assets | app/Setup/Assets.php | Enqueue CSS/JS assets |
| CustomLogin | app/Setup/CustomLogin.php | Custom login pages for admin, hotel, and guest |
| GuestExpiration | app/Setup/GuestExpiration.php | Automatic guest account expiration and access validation |
| HotelRoutes | app/Setup/HotelRoutes.php | Hotel URL routing (/hotel/{slug}) |
| Videos | app/Setup/Videos.php | Video URL routing (/videos/{slug}) |
| Roles | app/Setup/Roles.php | Custom user roles (Hotel, Guest) |
| Sidebars | app/Setup/Sidebars.php | Widget areas |
| MenuVisibility | app/Setup/MenuVisibility.php | Hide admin menu items based on user roles |

### Database Providers

| Provider | File | Purpose |
|----------|------|---------|
| Migration | app/Database/Migration.php | Database table creation/updates |
| Schema | app/Database/Schema.php | Table schema definitions |

---

## Repositories

Repositories provide a data access layer for database operations.

### HotelRepository

**File**: `app/Repositories/HotelRepository.php`

**Methods**:
- `create($data)` - Create new hotel
- `update($hotel_id, $data)` - Update hotel
- `delete($hotel_id)` - Delete hotel
- `get_by_id($hotel_id)` - Get by ID
- `get_by_user_id($user_id)` - Get by WordPress user ID
- `get_by_slug($slug)` - Get by slug
- `get_by_code($code)` - Get by hotel code
- `get_all($args)` - Get all with filters
- `count($args)` - Count hotels
- `get_with_user($hotel_id)` - Get hotel with user data
- `get_days_to_renewal($hotel)` - Calculate days to license renewal

### VideoRepository

**File**: `app/Repositories/VideoRepository.php`

**Methods**:
- `create_or_update($video_id, $data)` - Create or update video
- `update($video_id, $data)` - Update video
- `get_by_video_id($video_id)` - Get by internal ID
- `get_by_slug($slug)` - Get by slug
- `get_all($args)` - Get all with filters
- `get_count($category)` - Count videos
- `get_distinct_categories()` - Get unique categories
- `increment_views($video_id)` - Increment view count
- `update_completion_stats($video_id, $rate)` - Update completion statistics

### HotelVideoAssignmentRepository

**File**: `app/Repositories/HotelVideoAssignmentRepository.php`

**Methods**:
- `assign($hotel_id, $video_id, $assigned_by)` - Assign video to hotel
- `unassign($hotel_id, $video_id)` - Remove assignment
- `delete($assignment_id)` - Delete assignment record
- `update_status($assignment_id, $status)` - Update admin status
- `update_hotel_status($hotel_id, $video_id, $status)` - Update hotel status
- `request($hotel_id, $video_id, $requested_by)` - Create pending request
- `approve($assignment_id, $approved_by)` - Approve request
- `reject($assignment_id)` - Reject request
- `get_assignment($hotel_id, $video_id)` - Get specific assignment
- `get_by_id($assignment_id)` - Get by ID
- `get_hotel_videos($hotel_id, $args)` - Get hotel's videos
- `get_video_hotels($video_id, $args)` - Get video's hotels
- `get_video_assignment_count($video_id)` - Count assignments
- `get_pending_requests()` - Get all pending requests
- `get_pending_requests_count()` - Count pending requests
- `get_hotel_pending_requests($hotel_id)` - Get hotel's pending requests
- `has_pending_request($hotel_id, $video_id)` - Check for pending request
- `get_hotel_active_videos($hotel_id)` - Get fully active videos

### GuestRepository

**File**: `app/Repositories/GuestRepository.php`

**Methods**:
- `create($data)` - Create new guest record
- `update($id, $data)` - Update guest record (used for expiration status updates)
- `delete($id)` - Delete guest record
- `get_by_id($id)` - Get guest by ID
- `get_by_user_id($user_id)` - Get guest by WordPress user ID
- `get_by_hotel_and_user($hotel_id, $user_id)` - Get guest by hotel and user
- `get_by_email_and_hotel($email, $hotel_id)` - Get guest by email and hotel
- `get_by_token($token)` - Get guest by verification token
- `verify_email($guest_id)` - Mark guest email as verified and activate account
- `get_hotel_guests($hotel_id, $args)` - Get all guests for a hotel (with filters)
- `count_hotel_guests($hotel_id, $status)` - Count guests for a hotel (optionally filtered by status)

**Usage for Expiration**:
- `update($guest_id, array('status' => 'expired'))` - Manually expire a guest
- `count_hotel_guests($hotel_id, 'expired')` - Count expired guests for a hotel

---

## Admin Pages

### Hotel Accounts (HotelsPage.php)

- **Menu**: Hotel Accounts (top-level)
- **URL**: admin.php?page=hotel-chain-accounts
- **Features**:
  - Create new hotel accounts with WordPress user
  - List all hotels with search/filter
  - Export hotels as CSV
  - Auto-generates registration and landing URLs

### Hotel Details (HotelView.php)

- **Menu**: Hidden submenu
- **URL**: admin.php?page=hotel-details&hotel_id={id}
- **Features**:
  - View hotel details and statistics
  - Copy registration/landing URLs
  - Admin action buttons (edit, assign videos, deactivate, etc.)

### Video Upload (VideosPage.php)

- **Menu**: Hotel Accounts > Upload Videos
- **URL**: admin.php?page=hotel-video-upload
- **Features**:
  - Upload video files with metadata
  - Set category, tags, language
  - Auto-extract video duration and thumbnail

### Video Library (VideoLibraryPage.php)

- **Menu**: Hotel Accounts > Video Library
- **URL**: admin.php?page=hotel-video-library
- **Features**:
  - Grid view of all videos (AJAX-powered)
  - Click video to load detail panel via AJAX
  - Edit video metadata inline
  - View/manage hotel assignments (AJAX assign/unassign)
  - Export video list as CSV
  - Filter by category

### Video Requests (VideoRequestsPage.php)

- **Menu**: Hotel Accounts > Video Requests (with pending count badge)
- **URL**: admin.php?page=hotel-video-requests
- **Features**:
  - List pending video requests from hotels
  - Approve/Reject requests via AJAX
  - Auto-removes processed requests from list

### Video Taxonomy (VideoTaxonomyPage.php)

- **Menu**: Hotel Accounts > Video Taxonomy
- **URL**: admin.php?page=hotel-video-taxonomy
- **Features**:
  - Manage video categories
  - Manage video tags

### Database Tools (DatabaseToolsPage.php)

- **Menu**: Hotel Accounts > Database Tools
- **URL**: admin.php?page=hotel-database-tools
- **Features**:
  - View database table status
  - Recreate tables if needed

---

## Frontend Pages

### Hotel Dashboard (HotelDashboard.php)

Available to users with the "hotel" role.

- **Menu**: Dashboard (top-level for hotel users)
- **URL**: admin.php?page=hotel-dashboard
- **Features**:
  - Welcome message with hotel name
  - Quick links to video library

### Hotel Video Library (HotelVideoLibraryPage.php)

- **Menu**: Dashboard > Video Library
- **URL**: admin.php?page=hotel-video-library
- **Features**:
  - Browse all system videos
  - Filter by category, search
  - View video details in side panel
  - Request access to unassigned videos (AJAX)
  - Toggle active/inactive status for assigned videos (AJAX)
  - Status indicators: Active, Pending, Not Assigned

---

## Custom Roles

### Hotel Role

- **Slug**: hotel
- **Capabilities**: read, edit_posts, edit_published_posts, publish_posts, upload_files
- **Restricted**: Cannot delete posts

### Guest Role

- **Slug**: guest
- **Capabilities**: read only
- **Restricted**: Cannot edit posts or upload files

---

## Guest Expiration System

The theme includes an automatic guest account expiration system that manages guest access based on their `access_end` date.

### Overview

Guest accounts have a time-limited access period defined by the hotel's `access_duration` setting. When a guest registers, their `access_end` date is calculated based on the hotel's configured access duration (default: 30 days). The system automatically expires guest accounts when their access period ends.

### Service Provider

**File**: `app/Setup/GuestExpiration.php`

The `GuestExpiration` service provider handles all expiration-related functionality:

- **Automatic Status Updates**: Updates guest status from 'active' to 'expired' when `access_end` passes
- **Real-time Access Validation**: Checks guest access validity on every page load
- **Cron Job**: Runs daily to batch-update expired guests

### How It Works

#### 1. Access Duration Configuration

Each hotel has an `access_duration` field (in days) that determines how long guest accounts remain active:

- **Location**: Hotels table (`wp_hotel_chain_hotels.access_duration`)
- **Default**: 30 days (if not set)
- **Set By**: Administrators when creating/editing hotel accounts

#### 2. Access End Calculation

When a guest registers:

- `access_start` is set to the current date/time
- `access_end` is calculated as: `access_start + access_duration days`
- **Location**: `app/Frontend/GuestRegistration.php` (lines 493-495)

#### 3. Automatic Expiration

The system uses multiple methods to ensure guests are expired promptly:

**Daily Cron Job**:
- Runs once per day via WordPress cron
- Updates all guests where `status = 'active'` AND `access_end < NOW()` to `status = 'expired'`
- Hook: `hotel_chain_check_guest_expiration`
- **Location**: `app/Setup/GuestExpiration.php::check_and_expire_guests()`

**Admin Init Check**:
- Runs when any admin page is loaded
- Provides immediate updates when administrators visit the dashboard
- **Location**: `app/Setup/GuestExpiration.php::check_and_expire_guests()`

**Real-time Template Check**:
- Validates guest access on every page load
- Updates status immediately if `access_end` has passed
- **Location**: `app/Setup/GuestExpiration.php::check_guest_access()`

#### 4. Access Validation

The `is_guest_access_valid()` static method checks if a guest has valid access:

**Validation Rules**:
1. Guest record must exist
2. Guest status must be 'active'
3. If `access_end` is set, it must be in the future

**Usage**:
```php
use HotelChain\Setup\GuestExpiration;

$is_valid = GuestExpiration::is_guest_access_valid( $guest );
```

**Location**: `template-hotel.php` (line 36) - Used to determine if guest can access hotel content

### Guest Status Values

| Status | Description |
|--------|-------------|
| `pending` | Guest registered but email not verified |
| `active` | Guest verified and has valid access (access_end not passed) |
| `expired` | Guest's access_end date has passed |
| `revoked` | Access manually revoked by admin/hotel |

### Expiration Behavior

**When a guest expires**:
- Status automatically changes from 'active' to 'expired'
- Guest can no longer access hotel videos or content
- Guest can still log in but will see access denied messages
- Hotel dashboard shows expired guest count
- Hotel dashboard shows expiring guests (within 3 days) in recent activity

**Expired Guest Access**:
- Expired guests cannot view hotel landing pages
- Expired guests cannot access video content
- Template checks: `GuestExpiration::is_guest_access_valid( $guest )` returns `false`

### Dashboard Integration

**Hotel Dashboard** (`app/Frontend/HotelDashboard.php`):
- Shows count of expired guests in statistics
- Displays expiring guests (within 3 days) in "Recent Activity" section
- Alerts hotel administrators about upcoming expirations

### Database Fields

**Guests Table** (`wp_hotel_chain_guests`):
- `access_start` (datetime): When guest access began
- `access_end` (datetime): When guest access expires
- `status` (varchar): Current status (pending, active, expired, revoked)

**Hotels Table** (`wp_hotel_chain_hotels`):
- `access_duration` (int): Number of days guest access lasts (default: 30)

### Manual Expiration

Administrators can manually expire guests by:
- Updating guest status to 'expired' via database
- Using the GuestRepository::update() method
- Setting status through admin interface (if implemented)

### Extending Expiration

To extend a guest's access:
1. Update the `access_end` date in the guests table
2. Ensure status is set to 'active'
3. The system will automatically respect the new expiration date

---

## URL Routing

### Hotel Landing Pages

- **Pattern**: /hotel/{slug}/
- **Example**: /hotel/grand-plaza/
- **Handler**: HotelRoutes::handle_hotel_template()
- **Template**: template-hotel.php

### Video Pages

- **Pattern**: /videos/{slug}/
- **Example**: /videos/welcome-video/
- **Handler**: Videos::handle_template()
- **Template**: template-video.php

---

## AJAX Endpoints

### Admin AJAX Actions

| Action | Handler | Purpose |
|--------|---------|---------|
| hotel_chain_get_video_detail | VideoLibraryPage::ajax_get_video_detail | Load video detail panel |
| hotel_chain_ajax_assign_video | VideoLibraryPage::ajax_assign_video | Assign video to hotel |
| hotel_chain_ajax_unassign_video | VideoLibraryPage::ajax_unassign_video | Unassign video from hotel |
| hotel_chain_ajax_approve_request | VideoRequestsPage::ajax_approve | Approve video request |
| hotel_chain_ajax_reject_request | VideoRequestsPage::ajax_reject | Reject video request |

### Frontend AJAX Actions (Hotel Users)

| Action | Handler | Purpose |
|--------|---------|---------|
| hotel_request_video | HotelDashboard::handle_video_request | Request access to video |
| hotel_toggle_video_status | HotelDashboard::handle_toggle_video_status | Toggle video active/inactive |

### Nonce Keys

- Admin Video Library: hotel_chain_video_library
- Admin Video Requests: hotel_chain_video_requests
- Hotel Frontend: hotel_video_request

---

## Asset Management

### CSS Files

| File | Purpose |
|------|---------|
| src/styles/main.css | Tailwind CSS source |
| assets/css/main.css | Compiled Tailwind CSS |
| src/styles/admin.css | Admin style overrides |

### JavaScript Files

| File | Purpose |
|------|---------|
| assets/js/app.js | Frontend JavaScript |
| assets/js/admin-hotels.js | Admin JavaScript |

### NPM Scripts

| Command | Purpose |
|---------|---------|
| npm run dev | Watch mode for development |
| npm run build | Minified production build |

---

## Video Request Workflow

### Status Values

**Admin Status (status column)**
- pending: Hotel has requested access, awaiting admin approval
- active: Admin has approved, video is assigned
- inactive: Admin has deactivated the assignment

**Hotel Status (status_by_hotel column)**
- active: Hotel wants the video visible to guests
- inactive: Hotel has hidden the video from guests

### Workflow Steps

1. Hotel requests video → Creates assignment with status = 'pending'
2. Admin reviews → Sees request in Video Requests page
3. Admin approves → Sets status = 'active'
4. OR Admin rejects → Deletes the assignment record
5. Hotel manages visibility → Toggles status_by_hotel without affecting admin status
6. Admin can unassign → Deletes assignment (removes access completely)

### Visibility Logic

A video is visible to hotel guests only when:
- status = 'active' (admin approved)
- status_by_hotel = 'active' (hotel enabled)

---

## File Structure

### app/ Directory

| Folder | Contents |
|--------|----------|
| Admin/ | Admin page handlers (7 files) |
| Contracts/ | ServiceProviderInterface |
| Database/ | Migration and Schema classes |
| Frontend/ | Hotel user pages (2 files) |
| Repositories/ | Data access layer (3 files) |
| Setup/ | Theme setup providers (6 files) |
| Support/ | Helper classes |

### Root Files

| File | Purpose |
|------|---------|
| functions.php | Theme bootstrap |
| style.css | Theme header/metadata |
| composer.json | PHP dependencies |
| package.json | Node dependencies |
| tailwind.config.js | Tailwind configuration |
| phpcs.xml | Coding standards config |

### Templates

| File | Purpose |
|------|---------|
| header.php | Site header |
| footer.php | Site footer |
| page.php | Page template |
| single.php | Single post template |
| archive.php | Archive template |
| 404.php | 404 error template |
| sidebar.php | Sidebar template |
| template-hotel.php | Hotel landing page |

---

## Development Notes

### Coding Standards

- Follows WordPress Coding Standards (WPCS)
- Run `composer run phpcs` to check code style
- Run `composer run fix` to auto-fix issues

### Database Version

- Current version: 1.2.1
- Option key: hotel_chain_db_version
- Migrations run automatically when version is incremented

### Adding New Features

1. Create a service provider implementing ServiceProviderInterface
2. Register the provider in app/Theme.php
3. Use repositories for database operations
4. Follow existing patterns for admin pages and AJAX handlers

---

*Documentation for Hotel Chain Theme v1.0.0*
