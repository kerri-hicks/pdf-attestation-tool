<?php
/**
 * PDF Attestation Admin Class
 *
 * Handles the network admin interface for viewing and managing attestation records:
 * - Displays all PDF attestation records from all sites
 * - Provides search and filter functionality
 * - Exports data for compliance auditing
 * - Manages pagination for large datasets
 *
 * @package PDFAttestationTool
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PDF_Attestation_Admin
 *
 * Creates a network admin page for viewing attestation records across all sites
 * in the WordPress multisite network. Only accessible from Network Admin dashboard.
 */
class PDF_Attestation_Admin {

	/**
	 * Database class instance
	 *
	 * @var PDF_Attestation_Database $database
	 */
	protected $database;

	/**
	 * Constructor - Initialize admin interface
	 *
	 * Sets up the network admin menu and page for viewing attestation records.
	 */
	public function __construct() {
		// Initialize database class for queries
		$this->database = new PDF_Attestation_Database();

		// Register the network admin page
		add_action( 'network_admin_menu', array( $this, 'register_network_admin_page' ) );

		// Handle CSV export functionality
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
	}

	/**
	 * Get all sites that have PDF attestation records
	 *
	 * @return array Array of site objects with PDFs
	 */
	protected function get_sites_with_pdfs() {
		global $wpdb;
		$table_name = $wpdb->base_prefix . 'pdf_attestations';

		// Get distinct blog IDs that have records
		$blog_ids = $wpdb->get_col( "SELECT DISTINCT blog_id FROM {$table_name} ORDER BY blog_id" );

		if ( empty( $blog_ids ) ) {
			return array();
		}

		$sites = array();
		foreach ( $blog_ids as $blog_id ) {
			$site = get_blog_details( $blog_id );
			if ( $site ) {
				$sites[] = $site;
			}
		}

		return $sites;
	}

	/**
	 * Get all users that have PDF attestation records
	 *
	 * @return array Array of user objects with PDFs
	 */
	protected function get_users_with_pdfs() {
		global $wpdb;
		$table_name = $wpdb->base_prefix . 'pdf_attestations';

		// Get distinct user IDs that have records
		$user_ids = $wpdb->get_col( "SELECT DISTINCT user_id FROM {$table_name} ORDER BY user_id" );

		if ( empty( $user_ids ) ) {
			return array();
		}

		$users = array();
		foreach ( $user_ids as $user_id ) {
			$user = get_user_by( 'id', $user_id );
			if ( $user ) {
				$users[] = $user;
			}
		}

		return $users;
	}

	/**
	 * Register the network admin page
	 *
	 * Adds the PDF Attestation menu to the Network Admin dashboard.
	 * This page is only visible to super-admins managing the entire network.
	 *
	 * @return void
	 */
	public function register_network_admin_page() {
		// Check if current user is a super-admin with network management capabilities
		if ( ! current_user_can( 'manage_network' ) ) {
			return;
		}

		// Add a top-level menu item in Network Admin
		add_menu_page(
			'PDF Attestation Records',                       // Page title
			'PDF Attestations',                              // Menu title
			'manage_network',                                // Capability required
			'pdf-attestation-records',                       // Menu slug
			array( $this, 'render_attestation_page' ),       // Callback
			'dashicons-clipboard',                           // Icon
			25                                               // Position in menu
		);
	}

	/**
	 * Render the network admin attestation records page
	 *
	 * Displays a comprehensive table of all PDF attestation records from all sites
	 * with search, filter, and export capabilities. Handles pagination for large
	 * datasets.
	 *
	 * @return void Outputs HTML directly
	 */
	public function render_attestation_page() {
		// Verify user capability
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to view attestation records.', 'pdf-attestation-tool' ) );
		}

		// Get query parameters for filtering and pagination
		$search    = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
		$date_from = isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '';
		$date_to   = isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '';
		$blog_id   = isset( $_GET['blog_id'] ) ? absint( $_GET['blog_id'] ) : 0;
		$user_id   = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
		$paged     = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
		$per_page  = isset( $_GET['per_page'] ) ? absint( $_GET['per_page'] ) : 100;
		$orderby   = isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : 'timestamp';
		$order     = isset( $_GET['order'] ) ? sanitize_text_field( wp_unslash( $_GET['order'] ) ) : 'DESC';

		// Validate orderby parameter
		$allowed_orderby = array( 'blog_id', 'username', 'timestamp' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'timestamp';
		}

		// Validate order parameter
		if ( 'ASC' !== strtoupper( $order ) ) {
			$order = 'DESC';
		}

		// Limit per_page to reasonable values
		if ( ! in_array( $per_page, array( 100, 500, 1000 ), true ) ) {
			$per_page = 100;
		}

		// Calculate offset for pagination
		$offset = ( $paged - 1 ) * $per_page;

		// Build filter arguments
		$filter_args = array(
			'search'    => $search,
			'date_from' => $date_from,
			'date_to'   => $date_to,
			'blog_id'   => $blog_id,
			'user_id'   => $user_id,
			'offset'    => $offset,
			'limit'     => $per_page,
			'orderby'   => $orderby,
			'order'     => $order,
		);

		// Get attestation records and total count
		$attestations = $this->database->get_attestations( $filter_args );
		$total_records = $this->database->count_attestations( $filter_args );
		$total_pages  = ceil( $total_records / $per_page );

		// Get only sites that have PDF attestation records
		$sites = $this->get_sites_with_pdfs();

		// Get only users that have PDF attestation records
		$users = $this->get_users_with_pdfs();

		// Render the page
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PDF Attestation Records', 'pdf-attestation-tool' ); ?></h1>

			<?php
			// Display success message if export completed
			if ( isset( $_GET['pdf_export_success'] ) ) {
				?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Attestation records have been exported successfully.', 'pdf-attestation-tool' ); ?></p>
				</div>
				<?php
			}
			?>

			<!-- Search and Filter Section -->
			<div class="pdf-attestation-filters">
				<form method="get" action="" id="pdf-filter-form">
					<?php
					// Include the page parameter to stay on network admin
					if ( isset( $_GET['page'] ) ) {
						?>
						<input type="hidden" name="page" value="<?php echo esc_attr( $_GET['page'] ); ?>" />
						<?php
					}
					?>

					<div class="filter-row">
						<!-- Search input -->
						<div class="filter-group">
							<label for="search"><?php esc_html_e( 'Search', 'pdf-attestation-tool' ); ?></label>
							<input
								type="text"
								id="search"
								name="search"
								placeholder="<?php esc_attr_e( 'Filename or username', 'pdf-attestation-tool' ); ?>"
								value="<?php echo esc_attr( $search ); ?>"
								style="width: 200px;"
							/>
						</div>

						<!-- Date from input -->
						<div class="filter-group">
							<label for="date_from"><?php esc_html_e( 'From Date', 'pdf-attestation-tool' ); ?></label>
							<input
								type="date"
								id="date_from"
								name="date_from"
								value="<?php echo esc_attr( $date_from ); ?>"
								style="width: 150px;"
							/>
						</div>

						<!-- Date to input -->
						<div class="filter-group">
							<label for="date_to"><?php esc_html_e( 'To Date', 'pdf-attestation-tool' ); ?></label>
							<input
								type="date"
								id="date_to"
								name="date_to"
								value="<?php echo esc_attr( $date_to ); ?>"
								style="width: 150px;"
							/>
						</div>
					</div>

					<div class="filter-row">
						<!-- Blog/Site filter -->
						<div class="filter-group">
							<label for="blog_id"><?php esc_html_e( 'Site', 'pdf-attestation-tool' ); ?></label>
							<select id="blog_id" name="blog_id" style="width: 200px;">
								<option value="0"><?php esc_html_e( 'All Sites', 'pdf-attestation-tool' ); ?></option>
								<?php
								foreach ( $sites as $site ) {
									?>
									<option value="<?php echo esc_attr( $site->blog_id ); ?>" <?php selected( $blog_id, $site->blog_id ); ?>>
										<?php echo esc_html( get_blog_option( $site->blog_id, 'blogname' ) ); ?>
									</option>
									<?php
								}
								?>
							</select>
						</div>

						<!-- User filter -->
						<div class="filter-group">
							<label for="user_id"><?php esc_html_e( 'User', 'pdf-attestation-tool' ); ?></label>
							<select id="user_id" name="user_id" style="width: 200px;">
								<option value="0"><?php esc_html_e( 'All Users', 'pdf-attestation-tool' ); ?></option>
								<?php
								foreach ( $users as $user ) {
									?>
									<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_id, $user->ID ); ?>>
										<?php echo esc_html( $user->user_login ); ?>
									</option>
									<?php
								}
								?>
							</select>
						</div>

						<!-- Records per page -->
						<div class="filter-group">
							<label for="per_page"><?php esc_html_e( 'Records per page', 'pdf-attestation-tool' ); ?></label>
							<select id="per_page" name="per_page" style="width: 150px;">
								<option value="100" <?php selected( $per_page, 100 ); ?>>100</option>
								<option value="500" <?php selected( $per_page, 500 ); ?>>500</option>
								<option value="1000" <?php selected( $per_page, 1000 ); ?>>1000</option>
							</select>
						</div>
					</div>

					<div class="filter-buttons">
						<?php submit_button( __( 'Filter', 'pdf-attestation-tool' ), 'primary', 'filter', false ); ?>
						<a href="<?php echo esc_url( admin_url( 'network/admin.php?page=pdf-attestation-records' ) ); ?>" class="button">
							<?php esc_html_e( 'Reset', 'pdf-attestation-tool' ); ?>
						</a>

						<!-- CSV Export button -->
						<form method="post" style="display: inline;">
							<?php wp_nonce_field( 'pdf_attestation_export_nonce', 'export_nonce' ); ?>
							<input type="hidden" name="pdf_export_csv" value="1" />
							<?php
							// Pass current filters to export
							foreach ( $filter_args as $key => $value ) {
								if ( ! empty( $value ) ) {
									?>
									<input type="hidden" name="filter_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $value ); ?>" />
									<?php
								}
							}
							?>
							<?php submit_button( __( 'Export CSV', 'pdf-attestation-tool' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>
				</form>
			</div>

			<!-- Records count -->
			<p class="description">
				<?php
				/* translators: %d: Total number of records */
				printf( esc_html__( 'Total records: %d', 'pdf-attestation-tool' ), intval( $total_records ) );
				?>
			</p>

			<!-- Attestation records table -->
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>
							<?php
							// Create sortable link for Site column
							$site_sort_url = add_query_arg(
								array(
									'orderby' => 'blog_id',
									'order'   => ( 'blog_id' === $orderby && 'ASC' === $order ) ? 'DESC' : 'ASC',
								)
							);
							$site_indicator = ( 'blog_id' === $orderby ) ? ( 'ASC' === $order ? ' ▲' : ' ▼' ) : '';
							?>
							<a href="<?php echo esc_url( $site_sort_url ); ?>">
								<?php esc_html_e( 'Site', 'pdf-attestation-tool' ); echo esc_html( $site_indicator ); ?>
							</a>
						</th>
						<th>
							<?php
							// Create sortable link for Username column
							$username_sort_url = add_query_arg(
								array(
									'orderby' => 'username',
									'order'   => ( 'username' === $orderby && 'ASC' === $order ) ? 'DESC' : 'ASC',
								)
							);
							$username_indicator = ( 'username' === $orderby ) ? ( 'ASC' === $order ? ' ▲' : ' ▼' ) : '';
							?>
							<a href="<?php echo esc_url( $username_sort_url ); ?>">
								<?php esc_html_e( 'Username', 'pdf-attestation-tool' ); echo esc_html( $username_indicator ); ?>
							</a>
						</th>
						<th><?php esc_html_e( 'Filename', 'pdf-attestation-tool' ); ?></th>
						<th>
							<?php
							// Create sortable link for Upload Date column
							$date_sort_url = add_query_arg(
								array(
									'orderby' => 'timestamp',
									'order'   => ( 'timestamp' === $orderby && 'ASC' === $order ) ? 'DESC' : 'ASC',
								)
							);
							$date_indicator = ( 'timestamp' === $orderby ) ? ( 'ASC' === $order ? ' ▲' : ' ▼' ) : '';
							?>
							<a href="<?php echo esc_url( $date_sort_url ); ?>">
								<?php esc_html_e( 'Upload Date', 'pdf-attestation-tool' ); echo esc_html( $date_indicator ); ?>
							</a>
						</th>
						<th><?php esc_html_e( 'Status', 'pdf-attestation-tool' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					// Display each attestation record
					if ( ! empty( $attestations ) ) {
						foreach ( $attestations as $record ) {
							// Get the site name
							$site_name = get_blog_option( $record->blog_id, 'blogname' );

							// Format timestamp
							$upload_date = wp_date( 'M j, Y g:i a', strtotime( $record->timestamp ) );

							// Status display
							$status = $record->attestation_status ? __( 'Attested', 'pdf-attestation-tool' ) : __( 'Not Attested', 'pdf-attestation-tool' );
							?>
							<tr>
								<td><?php echo esc_html( $site_name ); ?></td>
								<td><?php echo esc_html( $record->username ); ?></td>
								<td><?php echo esc_html( $record->filename ); ?></td>
								<td><?php echo esc_html( $upload_date ); ?></td>
								<td><?php echo esc_html( $status ); ?></td>
							</tr>
							<?php
						}
					} else {
						?>
						<tr>
							<td colspan="5" style="text-align: center; padding: 20px;">
								<?php esc_html_e( 'No attestation records found.', 'pdf-attestation-tool' ); ?>
							</td>
						</tr>
						<?php
					}
					?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $total_pages > 1 ) { ?>
				<div class="pagination" style="margin-top: 20px;">
					<?php
					// Generate pagination links
					$page_links = paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%' ),
							'format'    => '',
							'prev_text' => __( '&laquo; Previous', 'pdf-attestation-tool' ),
							'next_text' => __( 'Next &raquo;', 'pdf-attestation-tool' ),
							'total'     => $total_pages,
							'current'   => $paged,
							'type'      => 'array',
						)
					);

					if ( is_array( $page_links ) ) {
						echo wp_kses_post( implode( ' ', $page_links ) );
					}
					?>
				</div>
			<?php } ?>
		</div>

		<style>
			.pdf-attestation-filters {
				background: #fff;
				border: 1px solid #ddd;
				padding: 15px;
				margin-bottom: 20px;
				border-radius: 4px;
			}

			.filter-row {
				display: flex;
				gap: 15px;
				margin-bottom: 15px;
				flex-wrap: wrap;
				align-items: flex-end;
			}

			.filter-group {
				display: flex;
				flex-direction: column;
			}

			.filter-group label {
				font-weight: 600;
				margin-bottom: 5px;
				font-size: 13px;
			}

			.filter-buttons {
				display: flex;
				gap: 10px;
				margin-top: 10px;
			}

			.pdf-attestation-filters a {
				text-decoration: none;
			}

			.pdf-attestation-filters a:hover {
				text-decoration: underline;
			}
		</style>
		<?php
	}

	/**
	 * Handle CSV export of attestation records
	 *
	 * Exports filtered attestation records as a CSV file for compliance
	 * auditing and record-keeping. User can download the file.
	 *
	 * @return void Outputs CSV file and exits
	 */
	public function handle_csv_export() {
		// Check if export was requested
		if ( ! isset( $_POST['pdf_export_csv'] ) ) {
			return;
		}

		// Verify nonce for security
		if ( ! isset( $_POST['export_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['export_nonce'] ) ), 'pdf_attestation_export_nonce' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'pdf-attestation-tool' ) );
		}

		// Verify user capability
		if ( ! current_user_can( 'manage_network' ) ) {
			wp_die( esc_html__( 'You do not have permission to export attestation records.', 'pdf-attestation-tool' ) );
		}

		// Build filter arguments from POST data
		$filter_args = array(
			'search'    => isset( $_POST['filter_search'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_search'] ) ) : '',
			'date_from' => isset( $_POST['filter_date_from'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_date_from'] ) ) : '',
			'date_to'   => isset( $_POST['filter_date_to'] ) ? sanitize_text_field( wp_unslash( $_POST['filter_date_to'] ) ) : '',
			'blog_id'   => isset( $_POST['filter_blog_id'] ) ? absint( $_POST['filter_blog_id'] ) : 0,
			'user_id'   => isset( $_POST['filter_user_id'] ) ? absint( $_POST['filter_user_id'] ) : 0,
			'limit'     => 999999, // Get all matching records
			'offset'    => 0,
		);

		// Get all matching records
		$attestations = $this->database->get_attestations( $filter_args );

		// If no records, show message and return
		if ( empty( $attestations ) ) {
			wp_die( esc_html__( 'No records found to export.', 'pdf-attestation-tool' ) );
		}

		// Set up CSV headers
		$filename = 'pdf-attestations-' . gmdate( 'Y-m-d-His' ) . '.csv';

		// Send headers to trigger file download
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Create file handle for output
		$output = fopen( 'php://output', 'w' );

		// Write CSV header row
		fputcsv(
			$output,
			array(
				'UID',
				'Site',
				'Blog ID',
				'Username',
				'User ID',
				'Filename',
				'Upload Date',
				'Attestation Status',
				'Created At',
			)
		);

		// Write data rows
		foreach ( $attestations as $record ) {
			// Get site name
			$site_name = get_blog_option( $record->blog_id, 'blogname' );

			// Status text
			$status_text = $record->attestation_status ? 'Attested' : 'Not Attested';

			// Write row to CSV
			fputcsv(
				$output,
				array(
					$record->uid,
					$site_name,
					$record->blog_id,
					$record->username,
					$record->user_id,
					$record->filename,
					$record->timestamp,
					$status_text,
					$record->created_at,
				)
			);
		}

		// Close the file handle
		fclose( $output );

		// Exit to prevent any other output
		exit;
	}
}
