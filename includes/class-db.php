<?php
/**
 * Database operations and table generation.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_PrintNode_DB
 */
class GF_PrintNode_DB {

	/**
	 * Print Logs Table Name
	 */
	const TABLE_LOGS = 'gf_printnode_logs'; // we prepend the wp prefix dynamically

	/**
	 * Init
	 */
	public static function init() {
		// Hook the cleanup action globally.
		add_action( 'gf_printnode_daily_cleanup', array( __CLASS__, 'cleanup_old_logs' ) );
	}

	/**
	 * Generate or update the custom tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$wpdb->hide_errors();

		$charset_collate = $wpdb->get_charset_collate();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name = $wpdb->prefix . self::TABLE_LOGS;

		$sql = "CREATE TABLE $table_name (
			id bigint(20) unsigned NOT NULL auto_increment,
			entry_id bigint(20) unsigned NOT NULL,
			form_id bigint(20) unsigned NOT NULL,
			identifier varchar(255) DEFAULT '' NOT NULL,
			printer_id int(11) DEFAULT 0 NOT NULL,
			status varchar(50) DEFAULT 'queued' NOT NULL,
			job_id int(11) DEFAULT NULL,
			pdf_path varchar(255) DEFAULT NULL,
			response text,
			created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
			PRIMARY KEY  (id),
			KEY entry_id (entry_id),
			KEY form_id (form_id),
			KEY status (status)
		) $charset_collate;";

		dbDelta( $sql );

		// Schedule daily cleanup if not already scheduled.
		if ( ! wp_next_scheduled( 'gf_printnode_daily_cleanup' ) ) {
			wp_schedule_event( time(), 'daily', 'gf_printnode_daily_cleanup' );
		}
	}

	/**
	 * Insert a new print log.
	 *
	 * @param array $args Log details.
	 * @return int|false ID of inserted row or false.
	 */
	public static function insert_log( $args ) {
		global $wpdb;

		$defaults = array(
			'entry_id'   => 0,
			'form_id'    => 0,
			'identifier' => '',
			'printer_id' => 0,
			'status'     => 'queued',
			'job_id'     => null,
			'pdf_path'   => null,
			'response'   => '',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $args, $defaults );

		$inserted = $wpdb->insert(
			$wpdb->prefix . self::TABLE_LOGS,
			$data
		);

		if ( $inserted ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update an existing log.
	 *
	 * @param int   $log_id  Log ID.
	 * @param array $data Data to update.
	 * @return int|false Number of rows affected or false on error.
	 */
	public static function update_log( $log_id, $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		return $wpdb->update(
			$wpdb->prefix . self::TABLE_LOGS,
			$data,
			array( 'id' => $log_id )
		);
	}

	/**
	 * Get a log by ID.
	 *
	 * @param int $log_id Log ID.
	 * @return object|null
	 */
	public static function get_log( $log_id ) {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_LOGS;

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE id = %d", $log_id ) );
	}

	/**
	 * Cleanup old logs and their associated PDF files.
	 */
	public static function cleanup_old_logs() {
		global $wpdb;

		// We need to fetch the setting to check if auto-delete is enabled.
		// Since we haven't built the addon settings class fully yet, we'll
		// placeholder this to fetch a generic option or we'll fetch via GFFeedAddOn later.
		$auto_delete  = get_option( 'gf_printnode_auto_delete_logs', 'yes' );
		$retention_days = apply_filters( 'gf_printnode_log_retention_days', 7 );

		if ( 'yes' !== $auto_delete ) {
			return;
		}

		$table_name = $wpdb->prefix . self::TABLE_LOGS;
		$date_limit = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

		// Find logs older than limit.
		$old_logs = $wpdb->get_results( $wpdb->prepare( "SELECT id, pdf_path FROM $table_name WHERE created_at < %s", $date_limit ) );

		if ( ! empty( $old_logs ) ) {
			foreach ( $old_logs as $log ) {
				// Delete PDF file physically if exists.
				if ( ! empty( $log->pdf_path ) && file_exists( $log->pdf_path ) ) {
					@unlink( $log->pdf_path );
				}
				// Delete DB row.
				$wpdb->delete( $table_name, array( 'id' => $log->id ) );
			}
		}
	}
}

// Initialize.
GF_PrintNode_DB::init();
