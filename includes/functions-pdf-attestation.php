<?php
/**
 * PDF Attestation Tool - Helper Functions
 *
 * Contains utility functions used throughout the plugin for common operations.
 *
 * @package PDFAttestationTool
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a database instance for queries
 *
 * Helper function to get the PDF_Attestation_Database class instance.
 * Useful for quick database operations throughout the plugin.
 *
 * @return PDF_Attestation_Database Database instance
 */
function pdf_attestation_get_database() {
	return new PDF_Attestation_Database();
}

/**
 * Check if PDF attestation tool is properly set up
 *
 * Verifies that the required database table exists and the plugin
 * is ready to use. Can be called to check plugin status.
 *
 * @return bool True if plugin is ready, false otherwise
 */
function pdf_attestation_is_ready() {
	// Verify this is multisite
	if ( ! is_multisite() ) {
		return false;
	}

	// Check if database table exists
	global $wpdb;
	$table_name = $wpdb->base_prefix . 'pdf_attestations';

	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table_name
		)
	);

	return ! empty( $table_exists );
}

/**
 * Get human-readable status text for an attestation
 *
 * Converts the boolean attestation status to readable text.
 *
 * @param bool $status The attestation status (true/false)
 *
 * @return string Status text
 */
function pdf_attestation_get_status_text( $status ) {
	return $status ? __( 'Attested', 'pdf-attestation-tool' ) : __( 'Not Attested', 'pdf-attestation-tool' );
}

/**
 * Format a timestamp for display
 *
 * Converts a MySQL timestamp to a readable format using WordPress's
 * date formatting functions.
 *
 * @param string $timestamp MySQL format timestamp
 *
 * @return string Formatted date and time
 */
function pdf_attestation_format_timestamp( $timestamp ) {
	return wp_date( 'M j, Y g:i a', strtotime( $timestamp ) );
}

/**
 * Log an event for debugging (optional)
 *
 * Creates debug log entries for troubleshooting if debugging is enabled.
 * Uses WordPress's error log functionality.
 *
 * @param string $message Message to log
 * @param string $level   Optional. Log level: 'error', 'warning', 'info'
 *
 * @return void
 */
function pdf_attestation_log( $message, $level = 'info' ) {
	// Only log if debugging is enabled in wp-config.php
	if ( ! defined( 'WP_DEBUG_LOG' ) || ! WP_DEBUG_LOG ) {
		return;
	}

	// Format the message
	$log_message = sprintf(
		'[PDF Attestation] [%s] %s',
		strtoupper( $level ),
		$message
	);

	// Use WordPress's error logging
	error_log( $log_message );
}

/**
 * Get a clean blog name for UID generation
 *
 * Takes a blog name and sanitizes it for use in UIDs.
 *
 * @param string $blog_name The blog name to sanitize
 *
 * @return string Sanitized blog name
 */
function pdf_attestation_sanitize_blog_name( $blog_name ) {
	// Lowercase
	$sanitized = strtolower( $blog_name );

	// Remove non-alphanumeric and non-hyphen, non-space characters
	$sanitized = preg_replace( '/[^a-z0-9\s-]/', '', $sanitized );

	// Convert spaces to hyphens
	$sanitized = preg_replace( '/[\s]+/', '-', $sanitized );

	// Limit to 15 characters
	$sanitized = substr( $sanitized, 0, 15 );

	// Remove trailing hyphens
	$sanitized = rtrim( $sanitized, '-' );

	return $sanitized;
}

/**
 * Get a list of all blogs in the network
 *
 * Helper function to get all active sites/blogs in the WordPress network.
 *
 * @return array Array of blog/site objects
 */
function pdf_attestation_get_blogs() {
	// Use get_sites() for WordPress 4.6+
	return get_sites();
}

/**
 * Get the current blog name
 *
 * Gets the properly formatted name of the current blog.
 *
 * @return string Blog name
 */
function pdf_attestation_get_current_blog_name() {
	return get_bloginfo( 'name' );
}

/**
 * Check if user can upload files
 *
 * Helper to check if a user has permission to upload files.
 *
 * @param int $user_id Optional. User ID, defaults to current user
 *
 * @return bool True if user can upload, false otherwise
 */
function pdf_attestation_user_can_upload( $user_id = 0 ) {
	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	return user_can( $user_id, 'upload_files' );
}

/**
 * Get the URL to the PDF upload tool
 *
 * Returns the URL to the PDF upload form for the current site.
 *
 * @return string URL to the upload tool
 */
function pdf_attestation_get_upload_url() {
	return admin_url( 'tools.php?page=pdf-attestation-upload' );
}

/**
 * Get the URL to the network admin attestation records page
 *
 * Returns the URL to view all attestation records.
 *
 * @return string URL to attestation records page
 */
function pdf_attestation_get_records_url() {
	return network_admin_url( 'admin.php?page=pdf-attestation-records' );
}
