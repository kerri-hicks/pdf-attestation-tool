<?php
/**
 * Plugin Name: PC PDF Attestation Tool
 * Plugin URI: https://example.com/pc-pdf-attestation-tool
 * Description: Enforce mandatory accessibility attestation for all PDF uploads with permanent audit trail
 * Version: 1.0.0
 * Author: Providence College
 * Author URI: https://example.com
 * License: PolyForm Noncommercial License 1.0.0
 * License URI: https://polyformproject.org/licenses/noncommercial/1.0.0/
 * Text Domain: pc-pdf-attestation-tool
 * Domain Path: /languages
 * Network: true
 *
 * This plugin requires WordPress Multisite and manages PDF uploads across a network
 * of sites with mandatory accessibility attestation.
 *
 * @package PCPDFAttestationTool
 * @author Providence College
 * @license PolyForm Noncommercial License 1.0.0
 */

// Prevent direct access to this file
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Define plugin constants for use throughout the plugin
 */
define( 'PC_PDF_ATTESTATION_TOOL_VERSION', '1.0.0' );
define( 'PC_PDF_ATTESTATION_TOOL_FILE', __FILE__ );
define( 'PC_PDF_ATTESTATION_TOOL_DIR', plugin_dir_path( __FILE__ ) );
define( 'PC_PDF_ATTESTATION_TOOL_URL', plugin_dir_url( __FILE__ ) );

/**
 * Include all required plugin files BEFORE activation hook
 * These files contain the core functionality of the plugin
 */
require_once PC_PDF_ATTESTATION_TOOL_DIR . 'includes/class-pdf-attestation-database.php';
require_once PC_PDF_ATTESTATION_TOOL_DIR . 'includes/class-pdf-attestation-upload.php';
require_once PC_PDF_ATTESTATION_TOOL_DIR . 'includes/class-pdf-attestation-admin.php';
require_once PC_PDF_ATTESTATION_TOOL_DIR . 'includes/functions-pdf-attestation.php';

/**
 * Create activation hook callback function
 * Must be a named function, not a class method, for proper firing
 */
function pc_pdf_attestation_create_table_on_activation() {
	// Ensure database class is loaded
	require_once PC_PDF_ATTESTATION_TOOL_DIR . 'includes/class-pdf-attestation-database.php';
	
	// Call the static table creation method
	PC_PDF_Attestation_Database::create_table();
}

/**
 * Register plugin activation hook to create database table
 * This runs only once when the plugin is first activated
 * Must be called immediately, before any other hooks
 */
register_activation_hook( PC_PDF_ATTESTATION_TOOL_FILE, 'pc_pdf_attestation_create_table_on_activation' );

/**
 * Initialize the plugin by instantiating main classes
 * This happens on the 'plugins_loaded' hook to ensure WordPress is fully loaded
 */
add_action( 'plugins_loaded', function() {
	// Initialize database operations class
	new PC_PDF_Attestation_Database();
	
	// Initialize upload handling class
	new PC_PDF_Attestation_Upload();
	
	// Initialize admin interface and menu
	if ( is_admin() ) {
		new PC_PDF_Attestation_Admin();
	}
} );

/**
 * Ensure plugin only runs on multisite installations
 * This check prevents the plugin from activating on single-site WordPress
 */
add_action( 'plugins_loaded', function() {
	// Only proceed if this is a multisite installation
	if ( ! is_multisite() ) {
		// Show admin notice if plugin is somehow active on single-site
		add_action( 'admin_notices', function() {
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'PC PDF Attestation Tool Error:', 'pc-pdf-attestation-tool' ); ?></strong>
					<?php esc_html_e( 'This plugin requires WordPress Multisite to function. It has been deactivated.', 'pc-pdf-attestation-tool' ); ?>
				</p>
			</div>
			<?php
		} );
		
		// Deactivate the plugin if not multisite
		deactivate_plugins( plugin_basename( PC_PDF_ATTESTATION_TOOL_FILE ) );
	}
}, 9 ); // Priority 9 to run before other plugins_loaded hooks
