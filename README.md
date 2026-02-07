# Stupid Simple Media Folders

A WordPress plugin for organizing media library with hierarchical folders. Extracted from HandcraftedDad theme on 2026-02-06.

## Quick Links

- **Documentation:** [DEVELOPMENT.md](DEVELOPMENT.md) - Comprehensive development guide
- **WordPress.org:** [readme.txt](readme.txt) - Official plugin readme
- **GitHub:** https://github.com/handcrafteddad/stupid-simple-media-folders
- **Author:** [Handcrafted Dad](https://handcrafteddad.com)

## Installation

1. Place in `wp-content/plugins/stupid-simple-media-folders/`
2. Activate via WordPress admin
3. Navigate to **Media > Folders** to create folders
4. No configuration needed - it just works

## Development

### No Build Process

All files are production-ready. Edit directly:
- **PHP:** `includes/media-folders-core.php`
- **JavaScript:** `assets/js/media-folders.js`
- **CSS:** `assets/css/media-folders.css`

### Naming Convention

**Everything uses `ssmf` prefix:**

```php
// Functions (51 total)
ssmf_register_media_folders()
ssmf_enqueue_admin_assets()

// Classes
class SSMF_Folders_Walker

// Constants
SSMF_VERSION
SSMF_URL

// CSS
.ssmf-folder-icon

// JavaScript
ssmfData
```

### Testing

```bash
# Activate plugin
wp plugin activate stupid-simple-media-folders

# Create test folder
wp term create folders "Test Folder"

# List all folders
wp term list folders

# Check taxonomy registered
wp taxonomy list --fields=name,label
```

## Architecture

- **Taxonomy-based:** Uses WordPress's native term system
- **Zero database impact:** No custom tables
- **CSP-compliant:** No inline styles
- **WCAG 2.2 AA:** Full keyboard navigation

## Key Features

✅ Hierarchical folders with unlimited nesting
✅ Multiple folder assignment per file
✅ Grid and List view filtering
✅ Direct upload to specific folders
✅ Visual expand/collapse tree
✅ Recursive subfolder counts
✅ File type summary display
✅ Keyboard navigation
✅ Zero configuration

## Known Issues

- **Delete modal:** Browser alert shows instead of custom modal (JS override incomplete)
- **Performance:** O(n²) hierarchy detection for large folder trees (500+ folders)

## Recent Enhancements

✅ **File Type Icons** (v1.0.1) - Replaced text with color-coded Dashicons

## Future Enhancements

- Drag-and-drop folder reordering
- Bulk folder assignment (List view)
- Folder templates
- Folder colors/icons
- Folder permissions (multisite)

## Files

| File | Lines | Purpose |
|------|-------|---------|
| `stupid-simple-media-folders.php` | 101 | Main plugin file, loader |
| `includes/media-folders-core.php` | 1,247 | All functionality |
| `assets/js/media-folders.js` | 374 | Frontend interactivity |
| `assets/css/media-folders.css` | 838 | All styling |
| `assets/images/icon-folder.png` | - | Icon sprite (4 states) |

## API

### Filters

```php
// Modify taxonomy arguments
add_filter( 'ssmf_taxonomy_args', function( $args ) {
    $args['hierarchical'] = false;
    return $args;
});
```

### Actions

```php
// Before folder page renders
do_action( 'ssmf_before_folders_page' );

// After folder count updates
do_action( 'ssmf_folder_count_updated', $term_id, $count );
```

### JavaScript Events

```javascript
// Folder expanded
jQuery(document).on('ssmf:folder:expanded', function(e, termId) {
    console.log('Folder expanded:', termId);
});
```

### Data Access

```php
// Get all folders
$folders = get_terms( array(
    'taxonomy' => 'folders',
    'hide_empty' => false,
));

// Get media in folder
$attachments = get_posts( array(
    'post_type' => 'attachment',
    'tax_query' => array(
        array(
            'taxonomy' => 'folders',
            'field' => 'slug',
            'terms' => 'my-folder',
        ),
    ),
));

// Get recursive count
$count = ssmf_count_all_subfolders( $term_id );
```

## Theme Compatibility

Works seamlessly with HandcraftedDad theme:

- **Plugin active:** Plugin version runs (prefix: `ssmf_`)
- **Plugin inactive:** Theme version runs (prefix: `_hcd_`)
- **Zero data migration:** Taxonomy name unchanged ('folders')
- **No conflicts:** Plugin defines `SSMF_PLUGIN_ACTIVE` constant that theme checks

## Requirements

- WordPress 6.0+
- PHP 7.4+
- jQuery (WordPress core)

## License

GPL v2 or later

## Support

- **GitHub Issues:** https://github.com/handcrafteddad/stupid-simple-media-folders/issues
- **Documentation:** See [DEVELOPMENT.md](DEVELOPMENT.md)
- **WordPress.org:** https://wordpress.org/support/plugin/stupid-simple-media-folders/ (when published)

---

**Version:** 1.0.0
**Last Updated:** 2026-02-06
**Status:** Production ready, WordPress.org ready
