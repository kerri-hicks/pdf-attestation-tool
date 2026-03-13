<?php
/**
 * PDF Attestation Upload Class
 *
 * Handles all PDF upload operations including:
 * - Registering the dedicated upload page in admin
 * - Rendering the attestation form
 * - Processing form submissions
 * - Blocking PDFs from standard upload mechanisms
 * - Creating media library attachments for uploaded PDFs
 *
 * @package PCPDFAttestationTool
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PC_PDF_Attestation_Upload
 *
 * Handles the entire PDF upload workflow including form display, validation,
 * and integration with WordPress media library while blocking PDFs from
 * standard upload mechanisms.
 */
class PC_PDF_Attestation_Upload {

	/**
	 * Database class instance for storing attestations
	 *
	 * @var PC_PDF_Attestation_Database $database
	 */
	protected $database;

	/**
	 * Constructor - Initialize upload handler and register hooks
	 *
	 * Sets up all the WordPress hooks and filters needed to:
	 * - Display the upload form in the admin menu
	 * - Process form submissions
	 * - Block PDFs from non-attestation uploads
	 */
	public function __construct() {
		// Initialize database class for storing attestation records
		$this->database = new PC_PDF_Attestation_Database();

		// Register admin page for the upload form (displays on each site)
		add_action( 'admin_menu', array( $this, 'register_upload_page' ) );

		// Intercept and handle upload form submissions
		add_action( 'admin_init', array( $this, 'handle_upload_form' ) );

		// Block PDFs from being uploaded through standard mechanisms
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'block_pdf_uploads' ) );

		// Block PDFs via REST API uploads
		add_filter( 'rest_pre_insert_attachment', array( $this, 'block_pdf_rest_uploads' ), 10, 2 );

		// Block PDFs uploaded via XMLRPC (wp_upload_bits bypasses wp_handle_upload_prefilter)
		add_filter( 'wp_upload_bits', array( $this, 'block_pdf_upload_bits' ) );
	}

	/**
	 * Register the PDF upload page in the admin menu
	 *
	 * Adds a menu item in Tools for authorized users to access the dedicated
	 * PDF upload form. Only users with the 'upload_files' capability can see
	 * and access this page.
	 *
	 * @return void
	 */
	public function register_upload_page() {
		// Check if current user has permission to upload files
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		// Register the admin page under Media menu
		// Page slug: pdf-attestation-upload
		// Menu title: PDF Upload Tool
		// Capability: upload_files (same as media library upload)
		add_submenu_page(
			'upload.php',                                    // Parent menu slug (Media)
			'PC PDF Attestation Upload',                        // Page title (browser tab)
			'PC PDF Upload Tool',                               // Menu title
			'upload_files',                                  // Capability required
			'pc-pdf-attestation-upload',                        // Page slug
			array( $this, 'render_upload_form' )             // Callback to render page
		);
	}

	/**
	 * Render the PDF attestation upload form
	 *
	 * Displays the complete upload interface including:
	 * - File input for selecting a single PDF
	 * - Required accessibility attestation checkbox
	 * - Submit button
	 * - JavaScript validation
	 * - Previous upload history (optional)
	 *
	 * This form uses WordPress nonces for CSRF protection.
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_upload_form() {
		// Verify user has required capability
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload PDFs.', 'pc-pdf-attestation-tool' ) );
		}

		// Get current blog info for display and UID generation
		$blog_id   = get_current_blog_id();
		$blog_name = get_bloginfo( 'name' );

		// Start output buffering for the form
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PC PDF Upload Tool', 'pc-pdf-attestation-tool' ); ?></h1>

			<style>
				.pc-pdf-info-box {
					background: #f6f7f7;
					border: 1px solid #c3c4c7;
					border-left: 4px solid #72777c;
					border-radius: 4px;
					padding: 14px 18px;
					margin: 16px 0 20px 0;
					max-width: 800px;
				}
				.pc-pdf-info-box h2 {
					color: #1d2327;
					font-size: 13px;
					font-weight: 700;
					margin: 0 0 10px 0;
					padding: 0;
					border: none;
				}
				.pc-pdf-info-box ul {
					margin: 0;
					padding: 0 0 0 18px;
					list-style: disc;
					font-size: 13px;
					color: #3c3c3c;
					line-height: 1.7;
				}
				.pc-pdf-form-box {
					background: #fff;
					border: 1px solid #ddd;
					border-radius: 6px;
					padding: 20px 24px;
					max-width: 800px;
					margin-bottom: 24px;
				}
				.pc-pdf-form-box h2 {
					font-size: 15px;
					font-weight: 600;
					margin: 0 0 16px 0;
					padding: 0 0 12px 0;
					border-bottom: 1px solid #eee;
					color: #1d2327;
				}
				.pc-pdf-attestation-block {
					background: #fff5f5;
					border: 2px solid #c0392b;
					border-radius: 4px;
					padding: 16px;
					margin-top: 8px;
				}
				.pc-pdf-attestation-block p {
					font-size: 13px;
					font-weight: 600;
					color: #1d2327;
					margin: 0 0 12px 0;
					line-height: 1.6;
				}
				.pc-pdf-attestation-block ul {
					margin: 0 0 14px 18px;
					padding: 0;
					list-style: disc;
					font-size: 13px;
					font-weight: 600;
					color: #1d2327;
					line-height: 1.7;
				}
				.pc-pdf-checkbox-label {
					display: flex;
					gap: 10px;
					align-items: flex-start;
					font-size: 13px;
					font-weight: 600;
					color: #1d2327;
					cursor: pointer;
				}
				.pc-pdf-checkbox-label input[type="checkbox"] {
					width: 18px;
					height: 18px;
					margin-top: 1px;
					flex-shrink: 0;
					cursor: pointer;
				}
			</style>

			<?php
			// Display any success or error messages from form processing
			if ( isset( $_GET['pc_pdf_upload_success'] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p>
						<?php esc_html_e( 'PDF uploaded and attested successfully. The file is now available in the media library.', 'pc-pdf-attestation-tool' ); ?>
					</p>
				</div>
				<?php
			}

			if ( isset( $_GET['pc_pdf_upload_error'] ) ) {
				$error_code = isset( $_GET['pc_pdf_error_code'] ) ? sanitize_key( $_GET['pc_pdf_error_code'] ) : '';
				$error_messages = array(
					'attestation_missing' => __( 'You must attest to the accessibility of this file before uploading.', 'pc-pdf-attestation-tool' ),
					'no_file'             => __( 'Please select a PDF file to upload.', 'pc-pdf-attestation-tool' ),
					'invalid_type'        => __( 'Only PDF files are allowed. Please select a valid PDF.', 'pc-pdf-attestation-tool' ),
					'upload_failed'       => __( 'The file could not be uploaded. Please try again.', 'pc-pdf-attestation-tool' ),
					'db_failed'           => __( 'Failed to create attestation record. Please contact support.', 'pc-pdf-attestation-tool' ),
				);
				$error_message = isset( $error_messages[ $error_code ] ) ? $error_messages[ $error_code ] : __( 'An error occurred during upload.', 'pc-pdf-attestation-tool' );
				?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $error_message ); ?></p>
				</div>
				<?php
			}
			?>

			<!-- Informational Notice -->
			<div class="pc-pdf-info-box">
				<h2>&#128203; <?php esc_html_e( 'Please Read Before Uploading', 'pc-pdf-attestation-tool' ); ?></h2>
				<ul>
					<li><?php esc_html_e( 'The College does not support nor encourage the use of PDF files on providence.edu websites.', 'pc-pdf-attestation-tool' ); ?></li>
					<li><?php esc_html_e( 'If you upload a PDF, the Web Services team will not be able to assist in making it accessible.', 'pc-pdf-attestation-tool' ); ?></li>
					<li><?php esc_html_e( 'PDF files that are not accessible may put the College at risk of violating federal accessibility requirements, including the Americans with Disabilities Act (ADA) and Section 504 of the Rehabilitation Act.', 'pc-pdf-attestation-tool' ); ?></li>
				</ul>
			</div>

			<!-- Upload Form -->
			<div class="pc-pdf-form-box">
				<h2><?php esc_html_e( 'Upload a PDF', 'pc-pdf-attestation-tool' ); ?></h2>

				<form method="post" enctype="multipart/form-data" id="pdf-attestation-form">
					<?php
					// WordPress nonce for CSRF protection
					wp_nonce_field( 'pc_pdf_attestation_upload_nonce', 'pc_pdf_attestation_nonce' );
					?>

					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row">
									<label for="pdf-file"><?php esc_html_e( 'Select PDF File', 'pc-pdf-attestation-tool' ); ?></label>
								</th>
								<td>
									<input
										type="file"
										id="pdf-file"
										name="pdf_file"
										accept=".pdf"
										required
										aria-describedby="pdf-file-description"
									/>
									<p class="description" id="pdf-file-description">
										<?php esc_html_e( 'Select a single PDF file for upload.', 'pc-pdf-attestation-tool' ); ?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<?php esc_html_e( 'Uploading To', 'pc-pdf-attestation-tool' ); ?>
								</th>
								<td>
									<p>
										<?php
										/* translators: %s: The current site name */
										printf( esc_html__( '%s', 'pc-pdf-attestation-tool' ), '<strong>' . esc_html( $blog_name ) . '</strong>' );
										?>
									</p>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<?php esc_html_e( 'Acknowledgment', 'pc-pdf-attestation-tool' ); ?>
								</th>
								<td>
									<div class="pc-pdf-attestation-block">
										<p id="attestation-help">
											<?php esc_html_e( '&#9997;&#65039; By checking the box below, you confirm all of the following:', 'pc-pdf-attestation-tool' ); ?>
										</p>
										<ul>
											<li><?php esc_html_e( 'You have personally reviewed this PDF for accessibility compliance.', 'pc-pdf-attestation-tool' ); ?></li>
											<li><?php esc_html_e( 'This PDF conforms to current WCAG standards for PDF accessibility.', 'pc-pdf-attestation-tool' ); ?></li>
											<li><?php esc_html_e( 'You understand that the College does not support PDF files and cannot assist with accessibility issues after upload.', 'pc-pdf-attestation-tool' ); ?></li>
											<li><?php esc_html_e( 'You are personally responsible for ensuring this document meets all applicable accessibility requirements.', 'pc-pdf-attestation-tool' ); ?></li>
										</ul>
										<label class="pc-pdf-checkbox-label" for="pdf-attestation-checkbox">
											<input
												type="checkbox"
												id="pdf-attestation-checkbox"
												name="pc_pdf_attestation_checked"
												value="1"
												aria-describedby="attestation-help"
											/>
											<?php esc_html_e( 'I have read the above, I attest this PDF is accessible, and I am personally responsible for its compliance.', 'pc-pdf-attestation-tool' ); ?>
										</label>
									</div>
								</td>
							</tr>
						</tbody>
					</table>

					<?php
					submit_button(
						__( 'Upload PDF', 'pc-pdf-attestation-tool' ),
						'primary',
						'submit',
						true,
						array( 'id' => 'pdf-upload-button' )
					);
					?>
				</form>
			</div>

			<?php
			// Display recent uploads from current user on this site
			$this->display_recent_uploads();
			?>
		</div>

		<?php
		// Enqueue JavaScript for client-side validation and form handling
		$this->enqueue_upload_scripts();
	}

	/**
	 * Enqueue JavaScript for upload form validation
	 *
	 * Adds client-side validation to:
	 * - Verify file is selected
	 * - Verify attestation checkbox is checked
	 * - Provide helpful error messages before submission
	 *
	 * @return void
	 */
	protected function enqueue_upload_scripts() {
		// Get the current page to only enqueue on the upload form
		if ( ! isset( $_GET['page'] ) || 'pc-pdf-attestation-upload' !== $_GET['page'] ) {
			return;
		}

		// Define inline JavaScript for form validation
		$js_code = "
		(function() {
			'use strict';

			// Get form and elements
			const form = document.getElementById( 'pdf-attestation-form' );
			const fileInput = document.getElementById( 'pdf-file' );
			const checkbox = document.getElementById( 'pdf-attestation-checkbox' );

			if ( ! form || ! fileInput || ! checkbox ) {
				return; // Exit if form elements not found
			}

			// Prevent form submission if validation fails
			form.addEventListener( 'submit', function( e ) {
				// Check if file is selected
				if ( ! fileInput.files || fileInput.files.length === 0 ) {
					e.preventDefault();
					alert( '" . esc_js( __( 'Please select a PDF file to upload.', 'pc-pdf-attestation-tool' ) ) . "' );
					fileInput.focus();
					return false;
				}

				// Check if only one file is selected
				if ( fileInput.files.length > 1 ) {
					e.preventDefault();
					alert( '" . esc_js( __( 'You can only upload one PDF at a time. Please select a single file and try again.', 'pc-pdf-attestation-tool' ) ) . "' );
					return false;
				}

				// Check if attestation checkbox is checked
				if ( ! checkbox.checked ) {
					e.preventDefault();
					alert( '" . esc_js( __( 'You must attest to the accessibility of this file before uploading.', 'pc-pdf-attestation-tool' ) ) . "' );
					checkbox.focus();
					return false;
				}

				// All validation passed
				return true;
			} );

			// Disable file input from accepting multiple files
			fileInput.setAttribute( 'multiple', 'false' );
		})();
		";

		// Register a minimal handle so wp_add_inline_script() has something to attach to,
		// then enqueue it so WordPress outputs the script tag correctly.
		wp_register_script( 'pc-pdf-attestation-upload', false, array(), false, true );
		wp_enqueue_script( 'pc-pdf-attestation-upload' );
		wp_add_inline_script( 'pc-pdf-attestation-upload', $js_code );
	}

	/**
	 * Handle PDF attestation form submission
	 *
	 * Processes the upload form when submitted including:
	 * - Verifying nonce for CSRF protection
	 * - Validating form data
	 * - Generating unique UID
	 * - Creating attestation record
	 * - Moving file to uploads directory
	 * - Creating media library attachment
	 * - Redirecting with success/error message
	 *
	 * @return void Handles redirect, never returns
	 */
	public function handle_upload_form() {
		// Only process if on the upload page
		if ( ! isset( $_GET['page'] ) || 'pc-pdf-attestation-upload' !== $_GET['page'] ) {
			return;
		}

		// Only process POST requests
		if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		// Verify the nonce for CSRF protection
		if ( ! isset( $_POST['pc_pdf_attestation_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pc_pdf_attestation_nonce'] ) ), 'pc_pdf_attestation_upload_nonce' ) ) {
			wp_safe_redirect( add_query_arg( 'pc_pdf_upload_error', '1', admin_url( 'upload.php?page=pc-pdf-attestation-upload' ) ) );
			exit;
		}

		// Verify user has capability to upload files
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( esc_html__( 'You do not have permission to upload PDFs.', 'pc-pdf-attestation-tool' ) );
		}

		// Check if attestation checkbox was checked
		if ( ! isset( $_POST['pc_pdf_attestation_checked'] ) || '1' !== $_POST['pc_pdf_attestation_checked'] ) {
			$error_url = add_query_arg(
				array(
					'pc_pdf_upload_error' => '1',
					'pc_pdf_error_code'   => 'attestation_missing',
				),
				admin_url( 'upload.php?page=pc-pdf-attestation-upload' )
			);
			wp_redirect( $error_url );
			exit;
		}

		// Check if a file was uploaded
		if ( ! isset( $_FILES['pdf_file'] ) || empty( $_FILES['pdf_file']['tmp_name'] ) ) {
			$error_url = add_query_arg(
				array(
					'pc_pdf_upload_error' => '1',
					'pc_pdf_error_code'   => 'no_file',
				),
				admin_url( 'upload.php?page=pc-pdf-attestation-upload' )
			);
			wp_redirect( $error_url );
			exit;
		}

		// Check file type by MIME type
		$file_type = wp_check_filetype( $_FILES['pdf_file']['name'] );
		if ( 'application/pdf' !== $file_type['type'] && 'application/x-pdf' !== $file_type['type'] ) {
			$error_url = add_query_arg(
				array(
					'pc_pdf_upload_error' => '1',
					'pc_pdf_error_code'   => 'invalid_type',
				),
				admin_url( 'upload.php?page=pc-pdf-attestation-upload' )
			);
			wp_redirect( $error_url );
			exit;
		}

		// Add the PDF MIME type temporarily so wp_handle_upload() accepts it
		add_filter( 'upload_mimes', array( $this, 'allow_pdf_mime_type' ) );

		// Use WordPress to handle the file upload securely
		$uploaded_file = wp_handle_upload(
			$_FILES['pdf_file'],
			array( 'test_form' => false )
		);

		// Remove the temporary MIME type filter
		remove_filter( 'upload_mimes', array( $this, 'allow_pdf_mime_type' ) );

		// Check for upload errors
		if ( isset( $uploaded_file['error'] ) ) {
			$error_url = add_query_arg(
				array(
					'pc_pdf_upload_error' => '1',
					'pc_pdf_error_code'   => 'upload_failed',
				),
				admin_url( 'upload.php?page=pc-pdf-attestation-upload' )
			);
			wp_redirect( $error_url );
			exit;
		}

		// Get current user and blog information for attestation record
		$current_user = wp_get_current_user();
		$user_id      = $current_user->ID;
		$username     = $current_user->user_login;
		$blog_id      = get_current_blog_id();
		$blog_name    = get_bloginfo( 'name' );
		$filename     = basename( $_FILES['pdf_file']['name'] );
		$timestamp    = current_time( 'mysql' );

		// Generate unique UID for this attestation record
		$uid = $this->generate_uid( $blog_id, $blog_name, $username, $filename );

		// Prepare attestation data for database insertion
		$attestation_data = array(
			'uid'       => $uid,
			'blog_id'   => $blog_id,
			'user_id'   => $user_id,
			'username'  => $username,
			'filename'  => $filename,
			'timestamp' => $timestamp,
		);

		// Insert attestation record into database
		$attestation_id = $this->database->insert_attestation( $attestation_data );

		// Check if attestation record was created
		if ( ! $attestation_id ) {
			// Clean up uploaded file since database record failed
			if ( ! empty( $uploaded_file['file'] ) ) {
				wp_delete_file( $uploaded_file['file'] );
			}

			$error_url = add_query_arg(
				array(
					'pc_pdf_upload_error' => '1',
					'pc_pdf_error_code'   => 'db_failed',
				),
				admin_url( 'upload.php?page=pc-pdf-attestation-upload' )
			);
			wp_redirect( $error_url );
			exit;
		}

		// Create WordPress attachment post for the PDF
		// This makes the PDF available in the media library
		$attachment_data = array(
			'post_mime_type' => 'application/pdf',
			'post_title'     => sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		// Insert the attachment post
		$attachment_id = wp_insert_attachment( $attachment_data, $uploaded_file['file'] );

		// Generate attachment metadata (like file size, dimensions if applicable)
		// This is done in the background by WordPress
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $uploaded_file['file'] );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Redirect to success page
		$success_url = add_query_arg( 'pc_pdf_upload_success', '1', admin_url( 'upload.php?page=pc-pdf-attestation-upload' ) );
		wp_redirect( $success_url );
		exit;
	}

	/**
	 * Generate a unique UID for an attestation record
	 *
	 * Creates a human-readable unique identifier combining:
	 * - Current date (YYYY-MM-DD)
	 * - Blog ID and truncated blog name
	 * - Username
	 * - Sanitized filename
	 * - Random hash suffix (6-8 characters)
	 *
	 * Example: PDF-2025-12-10-blogid_5_registrar-kerri-accessible-pdf-handbook-a7f2k9
	 *
	 * @param int    $blog_id   WordPress blog/site ID
	 * @param string $blog_name Blog name to include in UID
	 * @param string $username  Uploader's WordPress username
	 * @param string $filename  Original PDF filename
	 *
	 * @return string Unique UID for the attestation record
	 */
	protected function generate_uid( $blog_id, $blog_name, $username, $filename ) {
		// Get current date in YYYY-MM-DD format
		$date = gmdate( 'Y-m-d' );

		// Sanitize blog name: lowercase, max 15 chars, remove special chars, convert spaces to hyphens
		$blog_name_sanitized = strtolower( $blog_name );
		$blog_name_sanitized = preg_replace( '/[^a-z0-9\s-]/', '', $blog_name_sanitized );
		$blog_name_sanitized = preg_replace( '/[\s]+/', '-', $blog_name_sanitized );
		$blog_name_sanitized = substr( $blog_name_sanitized, 0, 15 );

		// Sanitize username: lowercase, alphanumeric only
		$username_sanitized = strtolower( $username );
		$username_sanitized = preg_replace( '/[^a-z0-9]/', '', $username_sanitized );

		// Sanitize filename: lowercase, remove extension, convert special chars to hyphens
		$filename_sanitized = strtolower( basename( $filename, '.pdf' ) );
		$filename_sanitized = preg_replace( '/[^a-z0-9\s-]/', '', $filename_sanitized );
		$filename_sanitized = preg_replace( '/[\s]+/', '-', $filename_sanitized );
		$filename_sanitized = substr( $filename_sanitized, 0, 50 ); // Limit filename part

		// Generate random suffix (6-8 characters)
		$random_suffix = substr( str_replace( '=', '', base64_encode( wp_rand() . microtime() ) ), 0, 8 );
		$random_suffix = strtolower( preg_replace( '/[^a-z0-9]/', '', $random_suffix ) );

		// Construct the UID
		$uid = sprintf(
			'PDF-%s-blogid_%d_%s-%s-%s-%s',
			$date,
			absint( $blog_id ),
			$blog_name_sanitized,
			$username_sanitized,
			$filename_sanitized,
			substr( $random_suffix, 0, 8 )
		);

		// Verify UID is unique, if not, generate a new suffix and try again
		$attempt = 0;
		while ( $this->database->uid_exists( $uid ) && $attempt < 5 ) {
			$random_suffix = substr( str_replace( '=', '', base64_encode( wp_rand() . microtime() ) ), 0, 8 );
			$random_suffix = strtolower( preg_replace( '/[^a-z0-9]/', '', $random_suffix ) );

			$uid = sprintf(
				'PDF-%s-blogid_%d_%s-%s-%s-%s',
				$date,
				absint( $blog_id ),
				$blog_name_sanitized,
				$username_sanitized,
				$filename_sanitized,
				substr( $random_suffix, 0, 8 )
			);

			$attempt++;
		}

		return $uid;
	}

	/**
	 * Display recent PDF uploads by current user on current site
	 *
	 * Shows a table of the user's most recent PDF uploads with details
	 * including upload date, filename, blog name, and uploader name.
	 *
	 * @return void Outputs HTML table
	 */
	protected function display_recent_uploads() {
		// Get database and query recent uploads by current user
		$database = new PC_PDF_Attestation_Database();
		$user_id  = get_current_user_id();
		$blog_id  = get_current_blog_id();

		// Get recent uploads by this user on this site
		$attestations = $database->get_attestations(
			array(
				'user_id' => $user_id,
				'blog_id' => $blog_id,
				'limit'   => 10,
				'offset'  => 0,
			)
		);

		// Only display if there are uploads
		if ( empty( $attestations ) ) {
			return;
		}

		// Display a table of recent uploads
		?>
		<div class="pdf-recent-uploads" style="margin-top: 40px;">
			<h2><?php esc_html_e( 'Your Recent PDF Uploads', 'pc-pdf-attestation-tool' ); ?></h2>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Filename', 'pc-pdf-attestation-tool' ); ?></th>
						<th><?php esc_html_e( 'Site', 'pc-pdf-attestation-tool' ); ?></th>
						<th><?php esc_html_e( 'Uploaded By', 'pc-pdf-attestation-tool' ); ?></th>
						<th><?php esc_html_e( 'Upload Date', 'pc-pdf-attestation-tool' ); ?></th>
						<th><?php esc_html_e( 'Status', 'pc-pdf-attestation-tool' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $attestations as $record ) {
						// Get the site name
						$site_name = get_blog_option( $record->blog_id, 'blogname' );

						// Get the user's display name
						$user = get_user_by( 'id', $record->user_id );
						$user_display = $user ? $user->display_name : $record->username;

						// Format the timestamp for display
						$upload_date = wp_date( 'M j, Y g:i a', strtotime( $record->timestamp ) );

						// Determine status display
						$status = $record->attestation_status ? __( 'Attested', 'pc-pdf-attestation-tool' ) : __( 'Not Attested', 'pc-pdf-attestation-tool' );
						?>
						<tr>
							<td><?php echo esc_html( $record->filename ); ?></td>
							<td><?php echo esc_html( $site_name ); ?></td>
							<td><?php echo esc_html( $user_display ); ?></td>
							<td><?php echo esc_html( $upload_date ); ?></td>
							<td><span class="badge"><?php echo esc_html( $status ); ?></span></td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Block PDF uploads from the standard WordPress media library
	 *
	 * This filter is called before any file upload happens. If a PDF is being
	 * uploaded and it's NOT from the dedicated attestation tool (no valid nonce),
	 * the upload is blocked with a helpful error message.
	 *
	 * @param array $file The file being uploaded
	 *
	 * @return array|array The file array, or error array to block upload
	 */
	public function block_pdf_uploads( $file ) {
		// Get the file extension and type
		$file_type = wp_check_filetype( $file['name'] );

		// Only block if it's a PDF
		if ( 'application/pdf' !== $file_type['type'] && 'application/x-pdf' !== $file_type['type'] ) {
			return $file; // Not a PDF, allow it
		}

		// Check if this is coming from the attestation tool (has valid nonce)
		$is_from_attestation_tool = false;

		// Check for nonce in POST data
		if ( isset( $_POST['pc_pdf_attestation_nonce'] ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST['pc_pdf_attestation_nonce'] ) );
			if ( wp_verify_nonce( $nonce, 'pc_pdf_attestation_upload_nonce' ) ) {
				$is_from_attestation_tool = true;
			}
		}

		// If this PDF is not from the attestation tool, block it
		if ( ! $is_from_attestation_tool ) {
			$file['error'] = __( 'PDF uploads are not allowed through the standard media library. Please use the PDF Upload Tool to upload PDFs.', 'pc-pdf-attestation-tool' );
		}

		return $file;
	}

	/**
	 * Block PDF uploads via REST API
	 *
	 * Prevents PDFs from being uploaded through the WordPress REST API,
	 * which could bypass the attestation requirement. Only attestation
	 * tool uploads are allowed.
	 *
	 * @param array  $prepared_attachment The prepared attachment post array
	 * @param object $attachment          The attachment object
	 *
	 * @return array|WP_Error The attachment or error
	 */
	public function block_pdf_rest_uploads( $prepared_attachment, $attachment ) {
		// Check if the file is a PDF by checking the post mime type
		if ( ! isset( $prepared_attachment['post_mime_type'] ) ) {
			return $prepared_attachment;
		}

		$mime_type = $prepared_attachment['post_mime_type'];

		// Block if it's a PDF
		if ( 'application/pdf' === $mime_type || 'application/x-pdf' === $mime_type ) {
			return new WP_Error(
				'pdf_blocked',
				__( 'PDF uploads are not allowed through the REST API. Please use the PDF Upload Tool.', 'pc-pdf-attestation-tool' )
			);
		}

		return $prepared_attachment;
	}

	/**
	 * Block PDF uploads via wp_upload_bits() (used by XMLRPC)
	 *
	 * wp_upload_bits() is used by the XMLRPC media upload path and does not
	 * trigger wp_handle_upload_prefilter. Returning a non-array from this
	 * filter causes WordPress to treat the value as an error string and abort.
	 *
	 * @param array $upload_data Array containing 'name', 'bits', and 'time' keys
	 *
	 * @return array|string Original array to allow upload, or error string to block
	 */
	public function block_pdf_upload_bits( $upload_data ) {
		$file_type = wp_check_filetype( $upload_data['name'] );

		if ( 'application/pdf' === $file_type['type'] || 'application/x-pdf' === $file_type['type'] ) {
			return __( 'PDF uploads are not allowed through this method. Please use the PDF Upload Tool.', 'pc-pdf-attestation-tool' );
		}

		return $upload_data;
	}

	/**
	 * Temporary filter to allow PDFs through wp_handle_upload()
	 *
	 * This filter is added temporarily when the attestation upload form
	 * is processing to allow wp_handle_upload() to accept PDF files.
	 * It's removed immediately after.
	 *
	 * @param array $mimes Current allowed MIME types
	 *
	 * @return array Modified MIME types including PDF
	 */
	public function allow_pdf_mime_type( $mimes ) {
		// Add PDF MIME types to the allowed list
		$mimes['pdf'] = 'application/pdf';

		return $mimes;
	}
}
