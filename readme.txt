=== Stupid Simple Media Folders ===
Contributors: handcrafteddad
Tags: media, folders, organization, media library, taxonomy
Requires at least: 6.0
Tested up to: 6.8
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Organize your WordPress media library with hierarchical folders. Zero configuration, lightweight, taxonomy-based.

== Description ==

Stupid Simple Media Folders provides a clean, intuitive way to organize your WordPress media library using hierarchical folders. Built on WordPress's native taxonomy system, it requires zero configuration and works seamlessly with your existing media.

**Why "Stupid Simple"?**

Because it just works. No complicated settings, no database modifications, no learning curve. Install, activate, and start organizing your media immediately.

= Key Features =

* **Hierarchical Folders** - Create nested folders with unlimited depth
* **Multiple Folder Assignment** - Files can belong to multiple folders
* **Filtering** - Filter Grid and List views by folder
* **Direct Upload** - Upload files directly to specific folders
* **Visual Hierarchy** - Expand/collapse folder tree with visual indicators
* **File Type Summary** - See what types of files are in each folder at a glance
* **Subfolder Counts** - Recursive counting shows total nested content
* **Keyboard Navigation** - Full WCAG 2.2 AA accessibility compliance
* **Zero Database Impact** - Uses WordPress taxonomies (no custom tables)
* **Performance Optimized** - Lightweight and fast

= Perfect For =

* Photographers managing large image libraries
* Agencies organizing client assets
* Publishers with extensive media collections
* Anyone who wants their media library to actually make sense

= Technical Highlights =

* Taxonomy-based (uses WordPress's built-in term system)
* No custom database tables
* CSP-compliant (no inline styles)
* WCAG 2.2 Level AA accessible
* Lightweight JavaScript (no jQuery dependencies in future versions)
* Works with any theme

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/stupid-simple-media-folders`, or install through WordPress's plugin installer
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to **Media > Folders** to start creating folders
4. Upload media or assign existing media to your folders

That's it! No settings to configure.

== Frequently Asked Questions ==

= Does this physically move my files? =

No. Stupid Simple Media Folders uses WordPress taxonomies (like categories for posts) to organize your media. Your files stay exactly where they are on the server. This is safer and more flexible than physically moving files.

= Can I nest folders? =

Yes! Create as many levels of nested folders as you need. There's no artificial limit.

= What happens to my media if I delete a folder? =

The folder category is removed, but your media files remain safely in your library. Deleting a folder never deletes your files.

= Can I assign one file to multiple folders? =

Absolutely! Just like a post can have multiple categories, a media file can belong to multiple folders.

= Does this work with the Block Editor (Gutenberg)? =

Yes! The folder system works in both the Classic and Block editors.

= Is this compatible with other media plugins? =

Generally yes, since it uses WordPress's standard taxonomy system. However, some advanced media management plugins might conflict. Test in a staging environment if you have concerns.

= Can I move media between folders? =

Yes. Simply edit the media file and change its folder assignment, or use Quick Edit in List view.

= What happens if I deactivate the plugin? =

Your folder structure and assignments are stored in WordPress's database, so they'll be preserved. If you reactivate later, everything will be exactly as you left it.

= Does this work on multisite? =

Yes! Each site in a multisite network gets its own folder structure.

= Can developers extend this? =

Absolutely! The plugin is built with standard WordPress hooks and filters. Check the code for available actions and filters.

== Screenshots ==

1. Folder management screen with hierarchical tree view
2. Grid view with folder filter dropdown
3. Upload page with folder pre-selection
4. List view showing folder assignments

== Changelog ==

= 1.0.0 - 2026-02-06 =
* Initial release
* Hierarchical folder system
* Grid and List view filtering
* Direct upload to folders
* Expand/collapse folder tree
* Keyboard navigation (WCAG 2.2 AA)
* Visual file type indicators
* Subfolder counting
* SVG sprite optimization
* Full accessibility support

== Upgrade Notice ==

= 1.0.0 =
Initial release. Install and start organizing your media library today!

== Credits ==

Developed by [Handcrafted Dad](https://handcrafteddad.com)

Icons use WordPress blue color scheme for consistency with the admin interface.

== Support ==

For support, feature requests, or bug reports, please visit our [GitHub repository](https://github.com/handcrafteddad/stupid-simple-media-folders) or the [WordPress.org support forums](https://wordpress.org/support/plugin/stupid-simple-media-folders/).

== Privacy Policy ==

Stupid Simple Media Folders does not collect, store, or transmit any user data. All folder organization is stored locally in your WordPress database using standard taxonomy tables.
