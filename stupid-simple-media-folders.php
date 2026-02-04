<?php
/**
 * Stupid Simple Media Folders
 *
 * @package   Stupid_Simple_Media_Folders
 * @author    Handcrafted Dad
 * @license   GPL-2.0-or-later
 * @link      https://handcrafteddad.com
 *
 * @wordpress-plugin
 * Plugin Name:       Stupid Simple Media Folders
 * Plugin URI:        https://handcrafteddad.com/plugins/stupid-simple-media-folders
 * Description:       Organize your WordPress media library with hierarchical folders. Taxonomy-based, lightweight, and zero configuration required.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Handcrafted Dad
 * Author URI:        https://handcrafteddad.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stupid-simple-media-folders
 * Domain Path:       /languages
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
if ( ! defined( 'SSMF_VERSION' ) ) {
	define( 'SSMF_VERSION', '1.0.0' );
}

if ( ! defined( 'SSMF_FILE' ) ) {
	define( 'SSMF_FILE', __FILE__ );
}

if ( ! defined( 'SSMF_DIR' ) ) {
	define( 'SSMF_DIR', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'SSMF_URL' ) ) {
	define( 'SSMF_URL', plugin_dir_url( __FILE__ ) );
}

// Load the core functionality
require_once SSMF_DIR . 'includes/media-folders-core.php';

/**
 * Flush rewrite rules on plugin activation.
 */
function ssmf_activate_plugin() {
	// Register the taxonomy so it's available
	if ( function_exists( 'ssmf_register_media_folders' ) ) {
		ssmf_register_media_folders();
	}

	// Flush rewrite rules to make the taxonomy work properly
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'ssmf_activate_plugin' );

/**
 * Flush rewrite rules on plugin deactivation.
 */
function ssmf_deactivate_plugin() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'ssmf_deactivate_plugin' );

/**
 * Check for theme version conflict and display admin notice.
 */
function ssmf_check_theme_conflict() {
	// Check if theme has media-folders.php loaded
	$theme_file = get_template_directory() . '/inc/media-folders.php';

	if ( file_exists( $theme_file ) && function_exists( '_hcd_register_media_folders' ) ) {
		add_action( 'admin_notices', 'ssmf_theme_conflict_notice' );
	}
}
add_action( 'plugins_loaded', 'ssmf_check_theme_conflict' );

/**
 * Display admin notice about theme conflict.
 */
function ssmf_theme_conflict_notice() {
	?>
	<div class="notice notice-warning is-dismissible">
		<p>
			<strong>Stupid Simple Media Folders:</strong> The media folders feature is also active in your theme.
			The plugin version will take precedence. Consider removing the theme version to avoid conflicts.
		</p>
	</div>
	<?php
}
