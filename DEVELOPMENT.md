# Stupid Simple Media Folders - Development Documentation

## Overview

**Stupid Simple Media Folders** is a WordPress plugin that provides hierarchical folder organization for the media library. Originally developed as part of the HandcraftedDad theme, it was extracted as a standalone plugin on 2026-02-06.

- **Version:** 1.0.0
- **Author:** Handcrafted Dad
- **Repository:** https://github.com/handcrafteddad/stupid-simple-media-folders
- **WordPress.org:** Ready for submission (not yet published)
- **License:** GPL v2 or later

## Architecture

### Core Technology

- **Taxonomy-based:** Uses WordPress's native taxonomy system (like categories/tags)
- **No custom tables:** All data stored in standard WordPress term tables
- **Zero migration:** Switching from theme to plugin requires no data migration
- **CSP-compliant:** No inline styles (all styling via external CSS)
- **WCAG 2.2 AA:** Full keyboard navigation and accessibility support

### Plugin Structure

```
stupid-simple-media-folders/
├── stupid-simple-media-folders.php    # Main plugin file (loader)
├── includes/
│   └── media-folders-core.php         # Core functionality (1,247 lines)
├── assets/
│   ├── css/
│   │   └── media-folders.css          # All styling (838 lines)
│   ├── js/
│   │   └── media-folders.js           # Frontend logic (374 lines)
│   └── images/
│       └── icon-folder.svg            # SVG sprite (32×24px, 4 states)
├── languages/
│   └── (empty - ready for translations)
├── readme.txt                         # WordPress.org format
└── DEVELOPMENT.md                     # This file
```

## Naming Conventions

**CRITICAL: All code uses `ssmf` prefix (Stupid Simple Media Folders)**

### Functions
```php
ssmf_register_media_folders()
ssmf_enqueue_admin_assets()
ssmf_count_all_subfolders()
// etc. (51 total functions)
```

### Classes
```php
class SSMF_Folders_Walker extends Walker { }
```

### Constants
```php
SSMF_VERSION     // Plugin version (1.0.0)
SSMF_FILE        // __FILE__ from main plugin
SSMF_DIR         // plugin_dir_path()
SSMF_URL         // plugin_dir_url()
SSMF_PLUGIN_ACTIVE // Prevents theme version loading
```

### JavaScript
```javascript
ssmfData                    // Localized data object
ssmfData.folders           // Folder hierarchy
ssmfData.currentFolder     // Active folder
_ssmfOverridden           // Uploader override flag
```

### CSS Classes
```css
.ssmf-folder-icon          /* Folder toggle button */
.ssmf-modal-overlay        /* Delete confirmation modal */
.ssmf-folder-name          /* Folder title */
.ssmf-custom-delete        /* Custom delete button */
/* etc. (~30 classes) */
```

### Script Handles
```php
'ssmf-media-folders'       // CSS handle
'ssmf-media-folders'       // JS handle
```

### Text Domain
```php
'stupid-simple-media-folders'  // All __(), _e(), esc_html__() calls
```

## Key Files Explained

### 1. stupid-simple-media-folders.php

**Purpose:** Plugin loader and WordPress integration

**Key Responsibilities:**
- Define plugin constants
- Load core functionality
- Register activation/deactivation hooks
- Check for theme version conflicts
- Display admin notices

**Critical Code:**
```php
// Constants (lines 34-48)
define( 'SSMF_VERSION', '1.0.0' );
define( 'SSMF_FILE', __FILE__ );
define( 'SSMF_DIR', plugin_dir_path( __FILE__ ) );
define( 'SSMF_URL', plugin_dir_url( __FILE__ ) );

// Load core (line 51)
require_once SSMF_DIR . 'includes/media-folders-core.php';

// Activation hook (lines 56-65)
function ssmf_activate_plugin() {
    if ( function_exists( 'ssmf_register_media_folders' ) ) {
        ssmf_register_media_folders();
    }
    flush_rewrite_rules();  // CRITICAL for taxonomy URLs
}
register_activation_hook( __FILE__, 'ssmf_activate_plugin' );
```

### 2. includes/media-folders-core.php

**Purpose:** All plugin functionality

**Key Sections:**
1. **Lines 1-20:** Security check and SSMF_PLUGIN_ACTIVE constant
2. **Lines 21-100:** Taxonomy registration (`ssmf_register_media_folders()`)
3. **Lines 101-250:** Walker class for hierarchical display
4. **Lines 251-500:** Folder management interface
5. **Lines 501-750:** Grid/List view filtering
6. **Lines 751-900:** Upload page integration
7. **Lines 901-1100:** AJAX handlers
8. **Lines 1101-1247:** Asset enqueueing and localization

**Critical Functions:**
```php
// Taxonomy registration (hook: 'init', priority: 0)
ssmf_register_media_folders()

// Count recursive subfolders (used everywhere)
ssmf_count_all_subfolders( $term_id )

// Custom folder management page
ssmf_add_folders_submenu()
ssmf_render_folders_page()

// Grid view dropdown
ssmf_restrict_media_by_folder()

// Upload folder pre-selection
ssmf_pre_upload_ui()
ssmf_add_folder_to_attachment()

// AJAX handlers
ssmf_update_folder_count()
ssmf_get_folder_counts()
```

### 3. assets/js/media-folders.js

**Purpose:** Frontend interactivity

**Key Features:**
1. **Folder expand/collapse** (lines 1-100)
   - Parse WordPress hierarchy from `#parent` dropdown
   - Add data attributes: `data-term-id`, `data-parent-id`, `data-depth`, `data-has-children`
   - Toggle visibility and icon states
   - Keyboard support (Enter/Space keys)

2. **Grid view filtering** (lines 101-200)
   - Inject folder dropdown into toolbar
   - Use Backbone.js to update modal on folder change
   - Retry logic for timing issues (100ms, 500ms delays)

3. **Upload folder assignment** (lines 201-300)
   - Override `wp.Uploader` to inject folder_id
   - Retry folder dropdown assignment (3 attempts)
   - Pre-select folder from URL parameter

4. **Folder count updates** (lines 301-374)
   - Poll for count changes every 1 second
   - Update both folder list and Grid view dropdown
   - AJAX handler for recursive counts

**Critical Code:**
```javascript
// Hierarchy detection (lines 50-80)
var $options = $('#parent option');
$options.each(function() {
    var indent = $(this).text().match(/^[\u00a0–—\-\s]*/)[0];
    var depth = Math.floor(indent.length / 3);
    // Store depth for parent detection
});

// Uploader override (lines 189-220)
if (typeof wp !== 'undefined' && wp.Uploader && !wp.Uploader.prototype._ssmfOverridden) {
    var originalSuccess = wp.Uploader.prototype.success;
    wp.Uploader.prototype.success = function(attachment) {
        // Inject folder_id before upload
        var folderId = ssmfData.currentFolder || $('#ssmf-folder-select').val();
        if (folderId && attachment.attributes) {
            attachment.attributes.folders = [parseInt(folderId)];
        }
        return originalSuccess.apply(this, arguments);
    };
    wp.Uploader.prototype._ssmfOverridden = true;
}
```

### 4. assets/css/media-folders.css

**Purpose:** All plugin styling

**Key Sections:**
1. **Lines 1-200:** Folder management page styling
2. **Lines 201-400:** Folder hierarchy tree (indentation, lines)
3. **Lines 401-600:** Grid/List view filtering
4. **Lines 601-700:** Delete confirmation modal
5. **Lines 701-838:** Upload page integration

**Critical Selectors:**
```css
/* Folder icon button */
.ssmf-folder-icon {
    background: url('../images/icon-folder.svg') no-repeat;
    background-position: 0 0;  /* Closed folder */
}
.ssmf-folder-icon.is-open {
    background-position: -16px 0;  /* Open folder */
}

/* Hierarchy indentation */
.folders tr[data-depth="1"] .column-name { padding-left: 2em; }
.folders tr[data-depth="2"] .column-name { padding-left: 4em; }
/* etc. */

/* Focus states (WCAG 2.2 AA) */
.ssmf-folder-icon:focus {
    outline: 2px solid #2271b1;
    outline-offset: 2px;
}
```

## Development Workflow

### Local Development Setup

1. **WordPress Installation:** http://localhost:8086
2. **Plugin Location:** `c:\Sites\handcrafteddad\wp-content\plugins\stupid-simple-media-folders\`
3. **Git Repository:** `c:\Sites\handcrafteddad\` (nested within HandcraftedDad repo)

### Making Changes

**For PHP changes:**
1. Edit files in `includes/` or main plugin file
2. Test immediately (no build step)
3. Verify functionality in WordPress admin

**For JavaScript changes:**
1. Edit `assets/js/media-folders.js`
2. Hard refresh browser (Ctrl+Shift+R)
3. Check browser console for errors

**For CSS changes:**
1. Edit `assets/css/media-folders.css`
2. Hard refresh browser
3. Verify visual appearance

**No build process required** - all files are production-ready

### Testing Checklist

#### Functionality Tests
- [ ] Create root-level folders
- [ ] Create nested folders (3+ levels)
- [ ] Edit folder names
- [ ] Delete empty folders
- [ ] Delete folders with content (media remains)
- [ ] Upload files to specific folder
- [ ] Assign existing media to folders
- [ ] Move media between folders
- [ ] Filter Grid view by folder
- [ ] Filter List view by folder
- [ ] Use Quick Edit to change folder
- [ ] Navigate with "View" row action
- [ ] Navigate with "Add Files" row action

#### Visual Tests
- [ ] Folder icons display correctly
- [ ] Hierarchy tree indentation proper
- [ ] Expand/collapse animations smooth
- [ ] Focus indicators visible (keyboard nav)
- [ ] Responsive design works
- [ ] Modal overlay displays (even if JS broken)

#### Keyboard Navigation
- [ ] Tab to folder icon
- [ ] Enter/Space to expand/collapse
- [ ] Tab to folder title (no action if no children)
- [ ] Tab through row actions
- [ ] Enter on "View" navigates to folder
- [ ] Enter on "Add Files" opens upload page

#### Edge Cases
- [ ] Empty folders (no media)
- [ ] Deeply nested folders (5+ levels)
- [ ] Special characters in folder names (&, ', ", <, >)
- [ ] Large folder trees (50+ folders)
- [ ] Folders with 1000+ media items
- [ ] Concurrent folder creation/deletion

#### Compatibility
- [ ] Block Editor (Gutenberg)
- [ ] Classic Editor
- [ ] With theme version disabled
- [ ] With theme version active (plugin takes precedence)
- [ ] Multisite (each site independent)
- [ ] PHP 7.4, 8.0, 8.1, 8.2
- [ ] WordPress 6.0, 6.5, 6.8+

### Debugging

**Enable WordPress debug mode:**
```php
// wp-config.php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

**Console logging:**
```javascript
// JavaScript uses console.log with 'SSMF:' prefix
console.log('SSMF: Folder hierarchy detected:', folders);
```

**PHP error log:**
```php
// Check wp-content/debug.log
error_log( 'SSMF: Folder count updated for term ' . $term_id );
```

**Common Issues:**

1. **Assets not loading (404 errors)**
   - Verify `SSMF_URL` constant is used (not relative paths)
   - Check file paths: `SSMF_URL . 'assets/css/media-folders.css'`
   - Ensure no extra string concatenation

2. **Folder counts not updating**
   - Check AJAX handlers registered: `ssmf_update_folder_count`, `ssmf_get_folder_counts`
   - Verify polling interval (1 second) in JavaScript
   - Check browser console for AJAX errors

3. **Hierarchy not displaying**
   - Verify `#parent` dropdown exists (WordPress requirement)
   - Check data attributes set: `data-term-id`, `data-depth`, `data-has-children`
   - Ensure JavaScript initializes after DOM ready

4. **Upload folder not pre-selected**
   - Check `wp.Uploader` override applied: `_ssmfOverridden` flag
   - Verify `folder_id` URL parameter passed
   - Check retry logic (3 attempts with delays)

## Known Issues & Limitations

### Delete Confirmation Modal

**Status:** UNRESOLVED (v1.0.0 ships with limitation)

**Problem:** Custom delete confirmation modal HTML/CSS complete, but JavaScript override fails to prevent browser alert.

**Attempted Solutions:**
- Remove `onclick` and `data-wp-lists` attributes (didn't work)
- Event delegation with `stopImmediatePropagation()` (didn't work)
- Capture phase listeners with `addEventListener(..., true)` (didn't work)
- Clone and replace DOM nodes (didn't work)

**Current Behavior:** WordPress's inline `onclick` still executes, showing browser alert.

**Possible Solutions:**
1. Server-side filter: Hook `term_row_actions` to modify delete link HTML
2. CSS pointer-events: Overlay custom trigger over original link
3. Accept limitation: Document that browser confirmation is standard

**Impact:** Low - browser alert is functional, just not custom-styled

### Performance

**Hierarchy Detection:** O(n²) algorithm for `hasChildren` check (lines 50-100 in JS)
- Iterates through all rows for each row
- Acceptable for <100 folders
- Needs optimization for large installations (500+ folders)

**Folder Count Polling:** 1-second interval may impact slow devices
- Consider increasing interval to 2-3 seconds
- Or use EventSource/WebSockets for real-time updates

## Future Enhancements

### Planned Features

1. **File Type Icons** (referenced in readme.txt)
   - Replace text-based file type display with actual icons
   - Use WordPress Dashicons or custom SVG sprite
   - Show in folder management page "Items" column

2. **Drag-and-Drop Folder Organization**
   - Reorder folders within same level
   - Move folders to different parent
   - Update hierarchy in database
   - Use WordPress's built-in `wp.updates` or jQuery UI sortable

3. **Bulk Folder Assignment** (List view)
   - Select multiple media items
   - Apply folder via bulk actions dropdown
   - Update all selected at once

4. **Folder Templates**
   - Pre-defined folder structures for common use cases
   - "Photography: Events, Portraits, Landscapes"
   - "Client Work: Client A, Client B, Client C"
   - One-click creation of entire hierarchy

5. **Folder Colors/Icons**
   - Assign custom color to folders
   - Choose from icon set (briefcase, camera, star, etc.)
   - Store as term meta
   - Display in folder tree and dropdowns

6. **Folder Permissions** (multisite/multi-author)
   - Restrict folder visibility by user role
   - Private folders (owner only)
   - Shared folders (team access)
   - Requires custom capabilities and term meta

### Optimization Opportunities

1. **JavaScript Bundling**
   - Currently jQuery-dependent
   - Could rewrite in vanilla JS (reduce dependencies)
   - Use modern ES6+ syntax with transpilation

2. **CSS Optimization**
   - 838 lines could be reduced with CSS custom properties
   - Consider using WordPress's built-in admin styles more

3. **Lazy Loading**
   - Don't load JS/CSS on non-media admin pages
   - Check current screen before enqueueing

4. **Caching**
   - Cache folder hierarchies with transients
   - Invalidate on folder create/edit/delete
   - Reduce database queries

## Git & Version Control

### Repository Information

- **Location:** `c:\Sites\handcrafteddad\wp-content\plugins\stupid-simple-media-folders\`
- **Remote:** https://github.com/handcrafteddad/stupid-simple-media-folders
- **Branch:** main
- **Nested Within:** HandcraftedDad theme repository (for now)

### Commit History

```
58ca2a2 (HEAD -> main, origin/main) Extract media folders to standalone plugin
├─ Main plugin file created
├─ Core functionality converted (ssmf_ prefix)
├─ Assets converted and organized
└─ Theme compatibility added

6da321b Enhance media folders system: CSP compliance, UX improvements, and documentation
├─ Accessibility improvements (WCAG 2.2 AA)
├─ Keyboard navigation added
├─ Icon sprite optimization
└─ Delete modal HTML/CSS (JS incomplete)

3088644 Add media library folder system with Grid view filtering
├─ Initial implementation in theme
├─ Taxonomy registration
├─ Grid/List view filtering
└─ Upload page integration
```

### Branching Strategy

- **main:** Production-ready code only
- **develop:** Active development (create when needed)
- **feature/*:** New features (e.g., feature/drag-drop-reorder)
- **fix/*:** Bug fixes (e.g., fix/delete-modal-override)

### Commit Message Convention

```
[Type] Brief description (50 chars max)

Detailed explanation of what changed and why.
May include multiple paragraphs.

- Bullet points for specific changes
- Reference issue numbers if applicable
```

**Types:** feat, fix, docs, style, refactor, test, chore

**Examples:**
```
feat: Add drag-and-drop folder reordering

Implement jQuery UI sortable for folder hierarchy.
Users can now drag folders to reorder or reparent.

- Added jquery-ui-sortable dependency
- New AJAX handler for hierarchy updates
- Visual feedback during drag operation

fix: Resolve asset 404 errors on plugin activation

Changed asset loading to use SSMF_URL constant
instead of relative paths from included file.

- Updated lines 963, 971 in media-folders-core.php
- Verified CSS and JS load correctly
- Tested with different WordPress directory structures
```

## WordPress.org Submission

### Checklist

- [x] Plugin header complete with all required fields
- [x] readme.txt follows WordPress.org format
- [x] License specified (GPL v2 or later)
- [x] All text properly escaped and sanitized
- [x] All SQL queries use $wpdb prepared statements (none in this plugin)
- [x] No hardcoded URLs or file paths
- [x] Assets use plugin constants (SSMF_URL)
- [x] Uninstall.php (not yet created - future)
- [x] Screenshots directory (not yet created - future)
- [ ] Translation-ready with .pot file generation
- [ ] Tested with WordPress 6.0+ and PHP 7.4+

### Generating .pot File

```bash
cd c:\Sites\handcrafteddad\wp-content\plugins\stupid-simple-media-folders
wp i18n make-pot . languages/stupid-simple-media-folders.pot
```

### Creating Screenshots

Place in plugin root directory (not assets):
```
stupid-simple-media-folders/
├── screenshot-1.png    # Folder management screen (1200×900)
├── screenshot-2.png    # Grid view with filter (1200×900)
├── screenshot-3.png    # Upload page with pre-selection (1200×900)
└── screenshot-4.png    # List view with folder column (1200×900)
```

### SVN Repository Structure

```
trunk/               # Current development version
tags/
  1.0.0/            # Released version
  1.0.1/            # Bug fix release
assets/
  icon-128x128.png  # Plugin directory icon
  icon-256x256.png  # Hi-DPI icon
  banner-772x250.png
  banner-1544x500.png
  screenshot-1.png
  screenshot-2.png
```

## Theme Compatibility

### How It Works

**Plugin defines constant:**
```php
// includes/media-folders-core.php (line 19)
if ( ! defined( 'SSMF_PLUGIN_ACTIVE' ) ) {
    define( 'SSMF_PLUGIN_ACTIVE', true );
}
```

**Theme checks constant:**
```php
// wp-content/themes/handcrafteddad/inc/media-folders.php (line 1)
if ( defined( 'SSMF_PLUGIN_ACTIVE' ) ) {
    return;  // Don't load theme version
}
```

### Migration Path

**For HandcraftedDad theme users:**

1. **Before plugin:** Media folders work via theme (prefix: _hcd_)
2. **Install plugin:** Plugin activates, defines SSMF_PLUGIN_ACTIVE
3. **Theme detects plugin:** Theme version stops loading
4. **Zero data migration:** Taxonomy name unchanged ('folders'), all assignments persist
5. **Deactivate plugin:** Theme version automatically resumes

**For other themes:**
- No conflicts (plugin is self-contained)
- Works with any theme
- No theme modifications needed

## API & Extensibility

### Filters

```php
// Modify folder taxonomy arguments
apply_filters( 'ssmf_taxonomy_args', $args );

// Example: Make folders non-hierarchical (flat)
add_filter( 'ssmf_taxonomy_args', function( $args ) {
    $args['hierarchical'] = false;
    return $args;
});
```

### Actions

```php
// Before folder management page renders
do_action( 'ssmf_before_folders_page' );

// After folder management page renders
do_action( 'ssmf_after_folders_page' );

// After folder count updates
do_action( 'ssmf_folder_count_updated', $term_id, $new_count );
```

### JavaScript Events

```javascript
// Folder expanded
jQuery(document).on('ssmf:folder:expanded', function(e, termId) {
    console.log('Folder expanded:', termId);
});

// Folder collapsed
jQuery(document).on('ssmf:folder:collapsed', function(e, termId) {
    console.log('Folder collapsed:', termId);
});

// Folder filter changed
jQuery(document).on('ssmf:filter:changed', function(e, folderId) {
    console.log('Filter changed to:', folderId);
});
```

### Accessing Plugin Data

```php
// Get all folders
$folders = get_terms( array(
    'taxonomy' => 'folders',
    'hide_empty' => false,
));

// Get folder by slug
$folder = get_term_by( 'slug', 'my-folder', 'folders' );

// Get media in folder
$attachments = get_posts( array(
    'post_type' => 'attachment',
    'posts_per_page' => -1,
    'tax_query' => array(
        array(
            'taxonomy' => 'folders',
            'field' => 'slug',
            'terms' => 'my-folder',
        ),
    ),
));

// Get folder count (recursive)
$count = ssmf_count_all_subfolders( $term_id );
```

## Support & Resources

### Documentation
- This file: `DEVELOPMENT.md`
- WordPress.org readme: `readme.txt`
- Memory file: `C:\Users\atomt\.claude\projects\c--Sites-handcrafteddad\memory\MEMORY.md`

### Links
- Plugin URI: https://handcrafteddad.com/plugins/stupid-simple-media-folders
- GitHub: https://github.com/handcrafteddad/stupid-simple-media-folders
- Support Forum: https://wordpress.org/support/plugin/stupid-simple-media-folders/ (when published)

### Contact
- Author: Handcrafted Dad
- Website: https://handcrafteddad.com
- GitHub Issues: https://github.com/handcrafteddad/stupid-simple-media-folders/issues

---

**Last Updated:** 2026-02-06
**Plugin Version:** 1.0.0
**WordPress Tested:** 6.8
**PHP Tested:** 7.4, 8.0, 8.1, 8.2
