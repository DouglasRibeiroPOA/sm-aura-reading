# Dashboard – User Reports Listing
## Requirements Document

**Version:** 1.1.0
**Created:** 2025-12-26
**Updated:** 2025-12-26
**Status:** ✅ Complete - Implemented
**Feature Type:** Read-Only Presentation Layer with Auth Dependency

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Prerequisites & Dependencies](#prerequisites--dependencies)
3. [Implementation Decisions](#implementation-decisions)
4. [Feature Scope](#feature-scope)
5. [User Access & Authentication](#user-access--authentication)
6. [Data Requirements](#data-requirements)
7. [Template Structure](#template-structure)
8. [Backend Implementation](#backend-implementation)
9. [Pagination Requirements](#pagination-requirements)
10. [UI/UX Requirements](#ui-ux-requirements)
11. [Security & Constraints](#security--constraints)
12. [Implementation Checklist](#implementation-checklist)

---

## 📖 Overview

### Purpose
Provide logged-in users with a dedicated page to view their past palm reading reports in a clean, organized, paginated list.

### Target Users
- Authenticated users (logged in via Account Service)
- Users with at least one generated reading (paid or free)

### Access Point
- Link/button in logged-in user dashboard
- Redirects to dedicated reports page (separate from main teaser flow)

### Key Principle
**Read-only presentation layer** - No editing, no regeneration, no business logic changes. Display existing data only.

---

## 🔗 Prerequisites & Dependencies

### Required Before Implementation

#### 1. Account Linking Feature (Critical Dependency)

**Purpose:** Automatically link free readings to user account when they log in or create an account.

**Problem:**
- Users can generate free readings before creating an account (`account_id = NULL`)
- After account creation, these old readings are orphaned
- Reports listing page won't show old free readings (query filters by `account_id`)

**Solution:**
When a user logs in or creates an Account Service account, migrate their existing free readings:

**Implementation Location:** `includes/class-sm-auth-handler.php`

**Trigger Points:**
1. **First-time login:** When user logs in via Account Service for the first time
2. **Account creation callback:** When JWT callback receives new account confirmation

**Migration Logic:**
```php
/**
 * Link existing free readings to newly authenticated account.
 *
 * @param string $account_id Account Service user ID
 * @param string $email User email address
 */
public static function link_existing_readings_to_account( $account_id, $email ) {
    global $wpdb;

    // Update readings table
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->sm_readings} r
            INNER JOIN {$wpdb->sm_leads} l ON r.lead_id = l.id
            SET r.account_id = %s
            WHERE l.email = %s
              AND r.account_id IS NULL",
            $account_id,
            $email
        )
    );

    // Update leads table
    $wpdb->query(
        $wpdb->prepare(
            "UPDATE {$wpdb->sm_leads}
            SET account_id = %s
            WHERE email = %s
              AND account_id IS NULL",
            $account_id,
            $email
        )
    );

    SM_Logger::log(
        'info',
        'ACCOUNT_LINKING',
        'Linked existing free readings to account',
        array(
            'account_id' => $account_id,
            'email' => $email,
        )
    );
}
```

**Integration Points:**
- `SM_Auth_Handler::handle_jwt_callback()` - After successful JWT validation
- `SM_Auth_Handler::process_login()` - After login confirmation

**Testing Requirements:**
1. ✅ User with free reading (account_id = NULL) creates account
2. ✅ Old reading appears in reports listing after login
3. ✅ account_id correctly updated in both tables
4. ✅ No duplicate readings created
5. ✅ Email matching is case-insensitive
6. ✅ Multiple free readings all linked correctly

**Timeline:** Must be implemented BEFORE reports listing feature goes live.

---

## ✅ Implementation Decisions

All clarification questions have been answered and approved:

### 1. Report Title Generation
**Decision:** Hybrid approach
- Extract title from `content_data` JSON (opening section or first heading)
- Fallback to `"Palm Reading from {date}"` if extraction fails
- Example: `"Palm Reading from November 15, 2023"`

### 2. Reading Duration Display
**Decision:** Calculate estimated duration
- Count words in `content_data` JSON
- Formula: `word_count ÷ 200 words/min`
- Display: `"~4 min"` or `"~15 min"`
- Rounds to nearest minute

### 3. Action Buttons Functionality
**Decision:** View functional, others show "Coming soon" toast
- ✅ **"View"** → Navigate to report page (`/?sm_report=1&lead_id={lead_id}`)
- ⏳ **"Download"** → Toast: "PDF download coming soon"
- ⏳ **"Share"** → Toast: "Sharing feature coming soon"
- ⏳ **"Delete"** → Toast: "Delete feature coming soon"

### 4. Database JOIN Strategy
**Decision:** Include JOIN with `wp_sm_leads` table
- Minimal performance impact
- Provides user name/email for display
- Future-proofs for multi-user accounts

### 5. Data Caching
**Decision:** No caching for MVP
- Queries are fast enough (<50ms typical)
- Always shows latest data
- Optimize later if performance issue arises

### 6. Items Per Page Selector
**Decision:** Make selector functional
- User can select 10, 20, or 30 items per page
- URL parameter: `?per_page=20`
- Backend validates and applies selection

### 7. Filtering & Sorting
**Decision:** Add to future roadmap
- MVP: Pagination only, fixed sort (newest first)
- Future: Date filters, type filters, custom sorting

### 8. Page Access Point
**Decision:** Use existing dashboard button
- Button already exists in logged-in user dashboard
- No new navigation elements needed

### 9. Back to Dashboard Button
**Decision:** Link to main logged-in dashboard page
- Explicit URL (not browser back)
- Update template link from static file to WordPress dashboard URL

### 10. Account Linking
**Decision:** Migrate old free readings when user creates account
- **See Prerequisites section above** - This is a dependency
- Must be implemented before reports listing goes live

---

## 🎯 Feature Scope

### ✅ In Scope
- **Account linking feature** (prerequisite - see above)
- Display list of reports generated by the currently logged-in user
- Show report metadata: title (extracted/generated), date, estimated reading time
- Backend-driven pagination (10/20/30 items per page, user-selectable)
- Action buttons per report:
  - ✅ **View** (functional) - Navigate to full report
  - ⏳ **Download, Share, Delete** (placeholder toasts) - Future features
- Responsive design (matches existing dashboard UI patterns)
- Empty state when user has no reports
- JOIN with leads table for user name/email display
- No caching (optimize later if needed)

### ❌ Out of Scope (MVP)
- Report editing or regeneration
- Filtering/sorting by date, type, or status (future roadmap)
- Search functionality (future roadmap)
- Bulk actions (select multiple, bulk delete)
- Report analytics or statistics
- Functional Download/Share/Delete buttons (placeholders only)
- Data caching (optimize later if needed)

### ❌ Out of Scope (Permanent)
- Changes to existing teaser flow
- Changes to credit system
- New business logic or state management

---

## 🔐 User Access & Authentication

### Authentication Requirements
- ✅ User MUST be logged in (valid JWT token)
- ✅ Redirect to login page if not authenticated
- ✅ Only display reports belonging to the logged-in user (via `account_id`)

### Data Filtering
Reports are filtered by:
1. **Primary Filter:** `account_id` matches logged-in user's Account Service ID
2. **Ordering:** Most recent first (`created_at DESC`)
3. **Pagination:** 10 reports per page

### Security Constraints
- ❌ NEVER expose reports from other users
- ✅ Validate user session on every page load
- ✅ Sanitize all output data
- ✅ Use WordPress nonces for any future actions (delete, etc.)

---

## 💾 Data Requirements

### Database Schema

#### Primary Table: `wp_sm_readings`
Available columns after all migrations (v1.4.5):

| Column | Type | Description | Template Mapping |
|--------|------|-------------|------------------|
| `id` | CHAR(36) | Reading ID | ✅ Report ID |
| `lead_id` | CHAR(36) | Lead ID | ✅ Join to leads table |
| `account_id` | VARCHAR(255) | Account Service user ID | ✅ Filter by logged-in user |
| `reading_type` | VARCHAR(50) | Default: 'palm_teaser' | ❓ Could generate title from this |
| `content_data` | LONGTEXT | JSON blob | ❓ Could extract title from JSON |
| `reading_html` | LONGTEXT | HTML rendering | ✅ Full report content |
| `prompt_template_used` | VARCHAR(50) | Prompt version | ✅ Metadata |
| `unlocked_section` | VARCHAR(100) | Unlocked section ID | ℹ️ Legacy field |
| `unlock_count` | INT | Number of unlocks | ✅ Metadata |
| `has_purchased` | TINYINT(1) | Full reading purchased | ✅ Paid vs Free indicator |
| `created_at` | DATETIME | Generation timestamp | ✅ Report date/time |
| `updated_at` | DATETIME | Last update | ✅ Secondary timestamp |

#### Secondary Table: `wp_sm_leads`
Join on `lead_id` to get user information:

| Column | Type | Description | Template Mapping |
|--------|------|-------------|------------------|
| `id` | CHAR(36) | Lead ID | ✅ Join key |
| `name` | VARCHAR(255) | User name | ✅ Display name |
| `email` | VARCHAR(255) | User email | ✅ User identifier |
| `account_id` | VARCHAR(255) | Account Service ID | ✅ Match logged-in user |

### Database Query Structure
```sql
SELECT
    r.id,
    r.account_id,
    r.reading_type,
    r.content_data,
    r.created_at,
    r.updated_at,
    r.has_purchased,
    r.unlock_count,
    l.name,
    l.email
FROM wp_sm_readings r
INNER JOIN wp_sm_leads l ON r.lead_id = l.id
WHERE r.account_id = %s  -- Logged-in user's account_id
ORDER BY r.created_at DESC
LIMIT %d OFFSET %d
```

---

## 🎨 Template Structure

### Base Template
**File:** `reportsGridTemplate.html`
**Usage:** Existing HTML template with embedded CSS and JavaScript
**Action:** Use as-is for UI structure, connect backend data

### Template Data Requirements

The template expects the following data per report:

```javascript
{
    id: "abc-123",                        // Report ID (UUID)
    title: "Palm Reading from Nov 15",    // ✅ Generated/extracted
    date: "2023-11-15",                   // ✅ From created_at
    time: "14:30",                        // ✅ From created_at
    readingTime: "~4 min",                // ✅ Calculated estimate
    status: "completed"                   // ✅ Hardcoded
}
```

### Data Mapping Strategy (Approved)

| Template Field | Database Source | Generation Logic |
|----------------|-----------------|------------------|
| `id` | `r.id` | Direct mapping (UUID) |
| `title` | `r.content_data` (JSON) | **Hybrid approach:**<br>1. Try to extract from JSON opening/heading<br>2. Fallback: `"Palm Reading from {formatted_date}"` |
| `date` | `r.created_at` | PHP: `date('Y-m-d', strtotime($created_at))` |
| `time` | `r.created_at` | PHP: `date('H:i', strtotime($created_at))` |
| `readingTime` | `r.content_data` (JSON) | **Calculate estimate:**<br>1. Parse JSON, count total words<br>2. Divide by 200 words/min<br>3. Format: `"~X min"` (rounded) |
| `status` | Hardcoded | Always `"completed"` |

---

## 🔧 Backend Implementation

### WordPress Integration

#### 1. Template File
**Location:** `templates/user-reports.php`
**Purpose:** WordPress template that displays the reports listing page

**Structure:**
```php
<?php
/**
 * Template Name: User Reports Listing
 * Description: Displays past palm reading reports for logged-in users
 */

// Authentication check
if ( ! SM_Auth_Handler::is_user_logged_in() ) {
    wp_redirect( SM_Auth_Handler::get_login_url() );
    exit;
}

// Get logged-in user's account_id
$account_id = SM_Auth_Handler::get_current_user_account_id();

// Pagination parameters
$page = isset( $_GET['paged'] ) ? max( 1, intval( $_GET['paged'] ) ) : 1;
$per_page = isset( $_GET['per_page'] ) ? intval( $_GET['per_page'] ) : 10;

// Validate per_page (only allow 10, 20, 30)
if ( ! in_array( $per_page, array( 10, 20, 30 ), true ) ) {
    $per_page = 10;
}

$offset = ( $page - 1 ) * $per_page;

// Fetch reports
$reports = SM_Reports_Handler::get_user_reports( $account_id, $per_page, $offset );
$total_reports = SM_Reports_Handler::get_user_reports_count( $account_id );
$total_pages = ceil( $total_reports / $per_page );

// Load template HTML from reportsGridTemplate.html
// Replace JavaScript sample data with actual backend data
// Render page
?>
```

#### 2. New Handler Class
**Location:** `includes/class-sm-reports-handler.php`
**Purpose:** Centralize reports data fetching logic

**Methods:**
```php
class SM_Reports_Handler {
    /**
     * Get reports for a specific user with pagination
     *
     * @param string $account_id Account Service user ID
     * @param int    $limit      Number of reports per page (10, 20, or 30)
     * @param int    $offset     SQL offset for pagination
     * @return array Array of formatted report objects
     */
    public static function get_user_reports( $account_id, $limit = 10, $offset = 0 );

    /**
     * Get total count of reports for a user
     *
     * @param string $account_id Account Service user ID
     * @return int Total number of reports
     */
    public static function get_user_reports_count( $account_id );

    /**
     * Generate report title from reading data (hybrid approach)
     *
     * @param object $reading Reading database row
     * @return string Generated title
     */
    private static function generate_report_title( $reading );

    /**
     * Calculate estimated reading time from content
     *
     * @param string $content_data JSON string of reading content
     * @return string Formatted time estimate (e.g., "~4 min")
     */
    private static function calculate_reading_time( $content_data );

    /**
     * Count total words in reading content JSON
     *
     * @param string $content_data JSON string
     * @return int Total word count
     */
    private static function count_words_in_content( $content_data );

    /**
     * Format report data for template consumption
     *
     * @param object $reading Reading database row with joined lead data
     * @return array Formatted report array for JavaScript
     */
    private static function format_report_for_template( $reading );
}
```

#### 3. REST API Endpoint (Optional)
**Endpoint:** `GET /wp-json/soulmirror/v1/reports/list`
**Purpose:** Alternative AJAX-based approach (if preferred)

**Parameters:**
- `page` (int) - Page number (default: 1)
- `per_page` (int) - Items per page (default: 10)

**Response:**
```json
{
    "success": true,
    "data": {
        "reports": [
            {
                "id": "abc-123",
                "title": "Palm Teaser Reading",
                "date": "2023-11-15",
                "time": "14:30",
                "readingTime": "N/A",
                "status": "completed"
            }
        ],
        "pagination": {
            "current_page": 1,
            "total_pages": 3,
            "total_reports": 27,
            "per_page": 10
        }
    }
}
```

---

## 📊 Pagination Requirements

### Pagination Logic
- **Items per page:** 10 (hardcoded for MVP, configurable later)
- **Page parameter:** `?paged=1`, `?paged=2`, etc.
- **Backend-driven:** SQL `LIMIT` and `OFFSET` clauses
- **Controls:** First, Previous, Next, Last buttons
- **Page info:** "Showing 1-10 of 27 readings"

### Template Pagination UI
The template already includes:
- ✅ Pagination controls (first, prev, next, last buttons)
- ✅ Page info display ("Page 1 of 3")
- ✅ Items per page selector (10, 20, 30) - **Disable for MVP**

### Implementation Notes
- Disable "Items per page" selector initially (hardcode to 10)
- Use WordPress pagination best practices (`paginate_links()`)
- Maintain page state on refresh
- No AJAX pagination for MVP (full page reload)

---

## 🎨 UI/UX Requirements

### Design Consistency
- ✅ Follow existing dashboard UI patterns
- ✅ Use same color scheme, fonts, spacing
- ✅ Match card styles, button styles, typography
- ✅ Consistent header/footer layout

### Responsive Design
The template already handles:
- ✅ Desktop view (4-column grid)
- ✅ Tablet view (3-column grid)
- ✅ Mobile view (single column, cards)

### Empty State
When user has no reports:
- ✅ Show empty state icon and message
- ✅ Display "Start Your First Reading" button
- ✅ Link back to dashboard

### Action Buttons
Per report, the template includes:
- **View** - Navigate to report page
- **Download** - Download as PDF (future feature)
- **Share** - Share via link (future feature)
- **Delete** - Delete report (future feature)

**MVP Implementation:**
- ✅ "View" button - Link to report page (`/?sm_report=1&lead_id={lead_id}`)
- ⏳ "Download" - Show placeholder toast: "Coming soon"
- ⏳ "Share" - Show placeholder toast: "Coming soon"
- ⏳ "Delete" - Show placeholder toast: "Coming soon"

---

## 🔒 Security & Constraints

### Security Requirements
1. ✅ **Authentication:** Validate JWT token on every request
2. ✅ **Authorization:** Only show reports belonging to logged-in user
3. ✅ **Input Validation:** Sanitize page number, per_page parameters
4. ✅ **Output Escaping:** Escape all data before rendering
5. ✅ **SQL Injection Prevention:** Use `$wpdb->prepare()` for all queries

### Implementation Constraints
1. ❌ **DO NOT modify existing functionality:**
   - Free user flow
   - Logged-in user flow
   - Credit system
   - Authentication system
   - Teaser reading generation

2. ✅ **DO maintain:**
   - Backward compatibility
   - Existing database schema
   - Existing API endpoints
   - Existing template files (`assets/script.js`, `assets/styles.css`)

3. ✅ **DO follow WordPress best practices:**
   - Use WordPress coding standards
   - Leverage WordPress pagination functions
   - Use WordPress sanitization/escaping functions
   - Follow plugin file structure conventions

---

## 📝 Implementation Checklist

### Phase 0: Prerequisites (MUST complete first)
- [ ] **Account Linking Feature**
  - [ ] Add `link_existing_readings_to_account()` method to `SM_Auth_Handler`
  - [ ] Call linking method from JWT callback handler
  - [ ] Call linking method from login handler
  - [ ] Test with free reading → account creation flow
  - [ ] Test with multiple free readings
  - [ ] Verify case-insensitive email matching
  - [ ] Add logging for audit trail

### Phase 1: Backend Foundation
- [ ] Create `SM_Reports_Handler` class (`includes/class-sm-reports-handler.php`)
- [ ] Implement `get_user_reports()` method (with JOIN)
- [ ] Implement `get_user_reports_count()` method
- [ ] Implement `generate_report_title()` method (hybrid approach)
- [ ] Implement `calculate_reading_time()` method (word count ÷ 200)
- [ ] Implement `count_words_in_content()` helper method
- [ ] Implement `format_report_for_template()` method
- [ ] Add pagination logic with per_page validation
- [ ] Write unit tests for all methods

### Phase 2: Template Integration
- [ ] Create `templates/user-reports.php`
- [ ] Load `reportsGridTemplate.html` structure as base
- [ ] Replace sample JavaScript data with backend data (PHP variables)
- [ ] Implement server-side rendering for reports array
- [ ] Add authentication check (redirect to login if not logged in)
- [ ] Add empty state handling
- [ ] Update "Back to Dashboard" button link (from static to WordPress URL)
- [ ] Make items-per-page selector functional (10/20/30 options)

### Phase 3: Action Buttons
- [ ] **"View" button** - Link to report page (`/?sm_report=1&lead_id={lead_id}`)
- [ ] **"Download" button** - Toast notification: "PDF download coming soon"
- [ ] **"Share" button** - Toast notification: "Sharing feature coming soon"
- [ ] **"Delete" button** - Toast notification: "Delete feature coming soon"
- [ ] Test all button interactions

### Phase 4: Pagination
- [ ] Backend pagination with per_page parameter (10/20/30)
- [ ] Validate per_page input (whitelist values)
- [ ] Update pagination controls (first, prev, next, last buttons)
- [ ] Display page info ("Showing X-Y of Z")
- [ ] Test with 10, 20, 30 items per page
- [ ] Test with multiple pages (seed 30+ test reports)
- [ ] Test edge cases (page 0, page beyond max, invalid per_page)

### Phase 5: Testing & Polish
**Account Linking Tests:**
- [ ] User with free reading (account_id = NULL) creates account
- [ ] Old free reading appears in reports listing after login
- [ ] Multiple free readings all linked correctly
- [ ] Email matching is case-insensitive

**Reports Listing Tests:**
- [ ] Logged-in user with reports sees correct list
- [ ] Logged-in user with no reports sees empty state
- [ ] Non-logged-in user redirected to login
- [ ] Report titles generated correctly (hybrid approach)
- [ ] Reading time estimates calculated correctly
- [ ] Pagination works correctly (10/20/30 per page)
- [ ] Page navigation (first, prev, next, last) works
- [ ] View button navigates to correct report
- [ ] Download/Share/Delete buttons show "Coming soon" toasts
- [ ] Back to Dashboard button works
- [ ] Responsive design (mobile, tablet, desktop)

**Automated Tests:**
- [ ] Write E2E test for account linking flow
- [ ] Write E2E test for reports listing page
- [ ] Run full test suite (`npm test`)
- [ ] Verify no regressions in existing flows

### Phase 6: Documentation & Deployment
- [ ] Update `CONTEXT.md` with reports listing feature
- [ ] Document `SM_Reports_Handler` class (inline PHPDoc)
- [ ] Document account linking feature (inline PHPDoc)
- [ ] Add code comments for all new methods
- [ ] Update `CLAUDE.md` if needed
- [ ] Test in staging environment
- [ ] Deploy to production

---

## 📚 References

- **Database Schema:** `includes/class-sm-database.php`
- **Authentication:** `includes/class-sm-auth-handler.php`
- **Template Base:** `reportsGridTemplate.html`
- **System Context:** `CONTEXT.md`
- **Testing Guide:** `TESTING.md`

---

## ✅ Approval Checklist

All requirements confirmed and approved:
- [x] Scope is clear and agreed upon
- [x] All 10 implementation decisions made and documented
- [x] UI/UX approach approved (use existing template)
- [x] Action button behavior confirmed (View functional, others placeholder)
- [x] Navigation flow defined (existing dashboard button, back to dashboard)
- [x] Security requirements understood (JWT auth, account_id filtering)
- [x] Account linking prerequisite identified (Phase 0)
- [x] No impact on existing functionality guaranteed (presentation layer only)

---

## 📋 Implementation Summary

**Status:** ✅ **APPROVED - Ready for Development**

**Prerequisites:**
1. ✅ Account linking feature (Phase 0) - MUST be completed first

**Core Feature:**
- Read-only reports listing page for logged-in users
- Pagination (10/20/30 items per page, user-selectable)
- Report data: auto-generated title, date, estimated reading time
- Action buttons: View (functional), Download/Share/Delete (placeholders)

**Key Decisions:**
1. Report title: Hybrid (extract from JSON, fallback to date-based)
2. Reading time: Calculate from word count (÷ 200 wpm)
3. Pagination: Functional selector (10/20/30 options)
4. Account linking: Migrate free readings on login/account creation
5. No caching (optimize later if needed)
6. JOIN with leads table for user name/email
7. Future roadmap: Filtering, sorting, functional Download/Share/Delete

**Estimated Effort:**
- Phase 0 (Account Linking): 1-2 days
- Phases 1-6 (Reports Listing): 3-4 days
- **Total: 4-6 days**

**Next Step:** Archived - implementation complete

---

**Last Updated:** 2025-12-26
**Author:** Development Team
**Status:** Ready for Implementation
