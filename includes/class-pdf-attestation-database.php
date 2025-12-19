<?php
/**
 * PDF Attestation Database Class
 *
 * Handles all database operations including:
 * - Creating/maintaining the network-wide attestation table
 * - Inserting new attestation records
 * - Querying attestation history
 * - Database cleanup and management
 *
 * @package PDFAttestationTool
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PDF_Attestation_Database
 *
 * Manages database operations for PDF attestations across the multisite network.
 * Uses $wpdb->base_prefix to ensure all data is stored in one network-wide table.
 */
class PDF_Attestation_Database {

	/**
	 * The WordPress database object
	 *
	 * @var object $wpdb
	 */
	protected $wpdb;

	/**
	 * The name of the attestations table
	 *
	 * @var string $table_name
	 */
	protected $table_name;

	/**
	 * Constructor - Initialize database class
	 * Runs every time the plugin loads to set up database references
	 */
	public function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;

		// Set table name using base_prefix to ensure network-wide table
		// base_prefix creates: wp_pdf_attestations (not wp_3_pdf_attestations)
		$this->table_name = $this->wpdb->base_prefix . 'pdf_attestations';
	}

	/**
	 * Create the network-wide attestation table on plugin activation
	 *
	 * This static method is called via register_activation_hook to create the database
	 * table when the plugin is first activated. It checks if the table already exists
	 * to avoid errors on reactivation.
	 *
	 * The table stores a permanent audit trail of all PDF uploads and attestations
	 * across the entire WordPress multisite network, with fields indexed for efficient
	 * searching and filtering by date, user, blog, and filename.
	 *
	 * @static
	 * @return bool True if table was created, false otherwise
	 */
	/**
	 * Ensure the table exists, creating it if necessary
	 * This is called before any database operations
	 *
	 * @static
	 * @return bool True if table exists or was created
	 */
	public static function ensure_table_exists() {
		global $wpdb;

		$table_name = $wpdb->base_prefix . 'pdf_attestations';

		// Check if table already exists
		$table_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( ! empty( $table_exists ) ) {
			// Table exists, but check if it needs the file_status column
			$column_exists = $wpdb->get_results(
				$wpdb->prepare(
					'SHOW COLUMNS FROM ' . esc_sql( $table_name ) . ' LIKE %s',
					'file_status'
				)
			);

			// If file_status column doesn't exist, add it
			if ( empty( $column_exists ) ) {
				$wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE ' . esc_sql( $table_name ) . ' ADD COLUMN file_status varchar(50) NOT NULL DEFAULT %s AFTER attestation_status',
						'active'
					)
				);
			}

			return true; // Table already exists
		}

		// Table doesn't exist, create it
		$charset_collate = $wpdb->get_charset_collate();

		// Use dbDelta for safe table creation with properly escaped table name
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		
		$sql = 'CREATE TABLE ' . esc_sql( $table_name ) . " (
			id bigint(20) NOT NULL AUTO_INCREMENT PRIMARY KEY,
			uid varchar(255) NOT NULL UNIQUE,
			blog_id bigint(20) NOT NULL,
			user_id bigint(20) NOT NULL,
			username varchar(100) NOT NULL,
			filename varchar(255) NOT NULL,
			timestamp datetime NOT NULL,
			attestation_status tinyint(1) NOT NULL DEFAULT 1,
			file_status varchar(50) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			KEY blog_id (blog_id),
			KEY user_id (user_id),
			KEY timestamp (timestamp),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Use dbDelta for safe table creation
		dbDelta( $sql );

		// Verify table was created
		$table_check = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		return ! empty( $table_check );
	}

	public static function create_table() {
		// For backward compatibility with activation hook
		return self::ensure_table_exists();
	}

	/**
	 * Insert a new attestation record into the database
	 *
	 * Called when a user successfully uploads a PDF and confirms the accessibility
	 * attestation checkbox. Creates a permanent record with all relevant metadata
	 * for compliance auditing.
	 *
	 * @param array $attestation_data {
	 *     Array of attestation record data
	 *
	 *     @type string $uid              Unique identifier combining date, blog, user, filename, hash
	 *     @type int    $blog_id          WordPress blog/site ID where PDF was uploaded
	 *     @type int    $user_id          WordPress user ID of uploader
	 *     @type string $username         WordPress user login name
	 *     @type string $filename         Original PDF filename
	 *     @type string $timestamp        Upload date/time in MySQL format
	 * }
	 *
	 * @return int|false The attestation record ID on success, false on failure
	 */
	public function insert_attestation( $attestation_data ) {
		// Ensure table exists before trying to insert
		if ( ! self::ensure_table_exists() ) {
			return false;
		}

		// Validate required fields are present and not empty
		$required_fields = array( 'uid', 'blog_id', 'user_id', 'username', 'filename', 'timestamp' );
		foreach ( $required_fields as $field ) {
			if ( ! isset( $attestation_data[ $field ] ) || empty( $attestation_data[ $field ] ) ) {
				return false;
			}
		}

		// Prepare data for insertion - sanitize all values
		$data_to_insert = array(
			'uid'               => sanitize_text_field( $attestation_data['uid'] ),
			'blog_id'           => absint( $attestation_data['blog_id'] ),
			'user_id'           => absint( $attestation_data['user_id'] ),
			'username'          => sanitize_text_field( $attestation_data['username'] ),
			'filename'          => sanitize_text_field( $attestation_data['filename'] ),
			'timestamp'         => sanitize_text_field( $attestation_data['timestamp'] ),
			'attestation_status' => 1, // Always 1 (true) since attestation is required to upload
		);

		// Define the format for each column to ensure proper data types
		$format = array( '%s', '%d', '%d', '%s', '%s', '%s', '%d' );

		// Insert record into database
		$result = $this->wpdb->insert(
			$this->table_name,
			$data_to_insert,
			$format
		);

		// Return false if insert failed, otherwise return the insert ID
		if ( ! $result ) {
			return false;
		}

		return $this->wpdb->insert_id;
	}

	/**
	 * Get attestation records from the database with optional filtering
	 *
	 * Retrieves attestation records with flexible filtering and sorting options.
	 * Used by the admin interface to display and search attestation history.
	 * Handles pagination to manage large datasets efficiently.
	 *
	 * @param array $args {
	 *     Optional. Filter and pagination arguments
	 *
	 *     @type string   $search          Optional search term to match against filename or username
	 *     @type string   $date_from       Optional start date in YYYY-MM-DD format
	 *     @type string   $date_to         Optional end date in YYYY-MM-DD format
	 *     @type int      $blog_id         Optional blog ID to filter records
	 *     @type int      $user_id         Optional user ID to filter records
	 *     @type int      $offset          Optional offset for pagination (default 0)
	 *     @type int      $limit           Optional number of records to return (default 100)
	 * }
	 *
	 * @return array Array of attestation record objects
	 */
	public function get_attestations( $args = array() ) {
		// Parse arguments with sensible defaults
		$args = wp_parse_args(
			$args,
			array(
				'search'    => '',
				'date_from' => '',
				'date_to'   => '',
				'blog_id'   => 0,
				'user_id'   => 0,
				'offset'    => 0,
				'limit'     => 100,
				'orderby'   => 'timestamp',
				'order'     => 'DESC',
			)
		);

		// Build a safe query using WHERE clauses as an array
		$where_clauses = array( '1=1' );
		$where_values  = array();

		// Add search filter
		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$where_clauses[] = '(filename LIKE %s OR username LIKE %s)';
			$where_values[] = $search;
			$where_values[] = $search;
		}

		// Add date from filter
		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'DATE(timestamp) >= %s';
			$where_values[] = sanitize_text_field( $args['date_from'] );
		}

		// Add date to filter
		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'DATE(timestamp) <= %s';
			$where_values[] = sanitize_text_field( $args['date_to'] );
		}

		// Add blog filter
		if ( ! empty( $args['blog_id'] ) ) {
			$where_clauses[] = 'blog_id = %d';
			$where_values[] = absint( $args['blog_id'] );
		}

		// Add user filter
		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$where_values[] = absint( $args['user_id'] );
		}

		// Build the WHERE clause
		$where_sql = implode( ' AND ', $where_clauses );

		// Validate and sanitize orderby
		$allowed_orderby = array( 'blog_id', 'username', 'timestamp', 'filename', 'user_id' );
		if ( ! in_array( $args['orderby'], $allowed_orderby, true ) ) {
			$args['orderby'] = 'timestamp';
		}

		// Validate order
		$order = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		// Build final query with proper escaping
		$sql = $this->wpdb->prepare(
			'SELECT * FROM ' . esc_sql( $this->table_name ) . ' WHERE ' . $where_sql . ' ORDER BY ' . esc_sql( $args['orderby'] ) . ' ' . esc_sql( $order ) . ' LIMIT %d OFFSET %d',
			array_merge( $where_values, array( absint( $args['limit'] ), absint( $args['offset'] ) ) )
		);

		// Execute the query and return results
		$results = $this->wpdb->get_results( $sql );

		return $results;
	}
	}

	/**
	 * Count total attestation records with optional filtering
	 *
	 * Returns the total count of attestation records, useful for pagination
	 * and displaying result counts in the admin interface.
	 *
	 * @param array $args Same filter arguments as get_attestations()
	 *
	 * @return int Total number of records matching the filters
	 */
	public function count_attestations( $args = array() ) {
		// Parse arguments with sensible defaults
		$args = wp_parse_args(
			$args,
			array(
				'search'    => '',
				'date_from' => '',
				'date_to'   => '',
				'blog_id'   => 0,
				'user_id'   => 0,
			)
		);

		// Start building count query
		$query = "SELECT COUNT(*) FROM {$this->table_name} WHERE 1=1";

		// Apply the same filters as get_attestations
		if ( ! empty( $args['search'] ) ) {
			$search = '%' . $this->wpdb->esc_like( $args['search'] ) . '%';
			$query  .= $this->wpdb->prepare(
				' AND (filename LIKE %s OR username LIKE %s)',
				$search,
				$search
			);
		}

		if ( ! empty( $args['date_from'] ) ) {
			$query .= $this->wpdb->prepare(
				' AND DATE(timestamp) >= %s',
				sanitize_text_field( $args['date_from'] )
			);
		}

		if ( ! empty( $args['date_to'] ) ) {
			$query .= $this->wpdb->prepare(
				' AND DATE(timestamp) <= %s',
				sanitize_text_field( $args['date_to'] )
			);
		}

		if ( ! empty( $args['blog_id'] ) ) {
			$query .= $this->wpdb->prepare(
				' AND blog_id = %d',
				absint( $args['blog_id'] )
			);
		}

		if ( ! empty( $args['user_id'] ) ) {
			$query .= $this->wpdb->prepare(
				' AND user_id = %d',
				absint( $args['user_id'] )
			);
		}

		// Execute count query
		$count = $this->wpdb->get_var( $query );

		return absint( $count );
	}

	/**
	 * Check if a UID already exists in the database
	 *
	 * Called during UID generation to ensure uniqueness. If a UID already exists,
	 * a new random suffix is generated and checked again.
	 *
	 * @param string $uid The UID to check for uniqueness
	 *
	 * @return bool True if UID exists, false if unique
	 */
	public function uid_exists( $uid ) {
		// Check if the UID is already in the database
		$result = $this->wpdb->get_var(
			$this->wpdb->prepare(
				'SELECT id FROM ' . esc_sql( $this->table_name ) . ' WHERE uid = %s',
				$uid
			)
		);

		// Return true if UID was found, false if unique
		return ! empty( $result );
	}

	/**
	 * Update the file status for an attestation record
	 *
	 * Called when a PDF is deleted or replaced in the media library.
	 * Updates the file_status column to track lifecycle.
	 *
	 * @param int    $attachment_id WordPress attachment ID
	 * @param string $status         New status: 'active', 'deleted', or 'replaced'
	 *
	 * @return bool True if updated, false otherwise
	 */
	public function update_file_status( $attachment_id, $status = 'deleted' ) {
		// Get the attachment post
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		// Get the filename from the attachment
		$filename = basename( get_attached_file( $attachment_id ) );

		// Find attestation record with this filename
		$result = $this->wpdb->update(
			$this->table_name,
			array( 'file_status' => sanitize_text_field( $status ) ),
			array( 'filename' => $filename ),
			array( '%s' ),
			array( '%s' )
		);

		return $result !== false;
	}

	/**
	 * Get the table name for external use
	 *
	 * Other classes may need to reference the table name for queries.
	 *
	 * @return string The full table name with prefix
	 */
	public function get_table_name() {
		return $this->table_name;
	}
}
