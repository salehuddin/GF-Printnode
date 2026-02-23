<?php
/**
 * Print Logs List Table.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * GF_PrintNode_Logs_List_Table Class
 */
class GF_PrintNode_Logs_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( array(
			'singular' => 'log',
			'plural'   => 'logs',
			'ajax'     => false,
		) );
	}

	/**
	 * Prepare the items for the table to process
	 */
	public function prepare_items() {
		global $wpdb;

		$this->process_bulk_action();

		$columns  = $this->get_columns();
		$hidden   = array();
		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		// Pagination parameters.
		$per_page     = $this->get_items_per_page( 'gf_printnode_logs_per_page', 20 );
		$current_page = $this->get_pagenum();

		// Fetch Data.
		$table_name = $wpdb->prefix . GF_PrintNode_DB::TABLE_LOGS;

		// Build Query.
		$where = 'WHERE 1=1';
		$args  = array();

		// Search.
		if ( isset( $_REQUEST['s'] ) && ! empty( $_REQUEST['s'] ) ) {
			$search = '%' . $wpdb->esc_like( wp_unslash( $_REQUEST['s'] ) ) . '%';
			$where .= ' AND ( identifier LIKE %s OR entry_id LIKE %s OR job_id LIKE %s OR form_id LIKE %s )';
			$args[] = $search;
			$args[] = $search;
			$args[] = $search;
			$args[] = $search;
		}

		// Status filter.
		if ( isset( $_REQUEST['status'] ) && ! empty( $_REQUEST['status'] ) && 'all' !== $_REQUEST['status'] ) {
			$where .= ' AND status = %s';
			$args[] = wp_unslash( $_REQUEST['status'] );
		}

		// Order & OrderBy.
		$orderby = isset( $_REQUEST['orderby'] ) ? sanitize_sql_orderby( wp_unslash( $_REQUEST['orderby'] ) ) : 'created_at';
		$order   = isset( $_REQUEST['order'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['order'] ) ) : 'DESC';

		$allowed_orderby = array( 'id', 'entry_id', 'form_id', 'identifier', 'printer_id', 'status', 'job_id', 'created_at' );
		if ( ! in_array( $orderby, $allowed_orderby, true ) ) {
			$orderby = 'created_at';
		}
		
		$order = ( 'ASC' === strtoupper( $order ) ) ? 'ASC' : 'DESC';

		// Count Total.
		$query_total = "SELECT COUNT(id) FROM $table_name $where";
		if ( ! empty( $args ) ) {
			$query_total = $wpdb->prepare( $query_total, $args );
		}
		$total_items = $wpdb->get_var( $query_total );

		// Fetch Items.
		$offset = ( $current_page - 1 ) * $per_page;
		$query  = "SELECT * FROM $table_name $where ORDER BY $orderby $order LIMIT %d OFFSET %d";

		$final_args = $args;
		$final_args[] = $per_page;
		$final_args[] = $offset;

		$this->items = $wpdb->get_results( $wpdb->prepare( $query, $final_args ), ARRAY_A );

		// Set Pagination parameters.
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page'    => $per_page,
			'total_pages' => ceil( $total_items / $per_page ),
		) );
	}

	/**
	 * Specify columns headers.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'id'         => esc_html__( 'ID', 'gf-printnode' ),
			'created_at' => esc_html__( 'Date', 'gf-printnode' ),
			'identifier' => esc_html__( 'Identifier', 'gf-printnode' ),
			'form_id'    => esc_html__( 'Source Form', 'gf-printnode' ),
			'entry_id'   => esc_html__( 'Entry ID', 'gf-printnode' ),
			'printer_id' => esc_html__( 'Printer ID', 'gf-printnode' ),
			'status'     => esc_html__( 'Status', 'gf-printnode' ),
			'job_id'     => esc_html__( 'PrintNode Job ID', 'gf-printnode' ),
			'actions'    => esc_html__( 'Actions', 'gf-printnode' ),
		);
	}

	/**
	 * Column: sortables.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'id'         => array( 'id', false ),
			'created_at' => array( 'created_at', true ),
			'identifier' => array( 'identifier', false ),
			'form_id'    => array( 'form_id', false ),
			'entry_id'   => array( 'entry_id', false ),
			'status'     => array( 'status', false ),
		);
	}

	/**
	 * Default column render.
	 *
	 * @param array  $item        A singular item (one full row's worth of data).
	 * @param string $column_name The name/slug of the column to be processed.
	 * @return string Text or HTML to be placed inside the column <td>
	 */
	protected function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'id':
			case 'created_at':
			case 'entry_id':
			case 'printer_id':
			case 'job_id':
				return esc_html( $item[ $column_name ] );
			case 'identifier':
				return '<strong>' . esc_html( $item[ $column_name ] ) . '</strong>';
			default:
				return print_r( $item, true );
		}
	}

	/**
	 * Column: form_id
	 *
	 * @param array $item
	 * @return string
	 */
	protected function column_form_id( $item ) {
		$form_id = absint( $item['form_id'] );
		$form_title = sprintf( esc_html__( 'Form #%d', 'gf-printnode' ), $form_id );
		
		if ( class_exists( 'GFAPI' ) ) {
			$form = GFAPI::get_form( $form_id );
			if ( ! empty( $form ) && ! is_wp_error( $form ) ) {
				$form_title = esc_html( $form['title'] );
			}
		}

		$form_url = admin_url( 'admin.php?page=gf_edit_forms&id=' . $form_id );
		return sprintf( '<a href="%s">%s</a>', esc_url( $form_url ), $form_title );
	}

	/**
	 * Render Status Column.
	 *
	 * @param array $item
	 * @return string
	 */
	protected function column_status( $item ) {
		$status = strtolower( $item['status'] );
		$class  = '';
		switch ( $status ) {
			case 'queued':
			case 'processing':
				$class = 'info';
				break;
			case 'sent':
			case 'success':
				$class = 'success';
				break;
			case 'error':
			case 'failed':
				$class = 'error';
				break;
			default:
				$class = 'default';
		}

		$output = '<span class="gf-printnode-status status-' . esc_attr( $class ) . '">' . esc_html( strtoupper( $status ) ) . '</span>';
		
		if ( 'error' === $class && ! empty( $item['response'] ) ) {
			$output .= '<div class="gf-printnode-response" style="font-size: 11px; margin-top: 4px; color: #d63638;">' . esc_html( $item['response'] ) . '</div>';
		}

		return $output;
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="log[]" value="%s" />', esc_attr( $item['id'] ) );
	}

	/**
	 * Actions column.
	 *
	 * @param array $item
	 * @return string
	 */
	protected function column_actions( $item ) {
		
		$actions = array();

		// Details
		$entry_url = admin_url( 'admin.php?page=gf_entries&view=entry&id=' . absint( $item['entry_id'] ) ); // Assuming standard GF entry URL

		// Let's just create Reprint and View PDF here.
		
		$reprint_url = wp_nonce_url( admin_url( 'admin.php?page=gf_printnode_logs&action=reprint&log=' . $item['id'] ), 'reprint_log_' . $item['id'] );
		
		$actions['reprint'] = sprintf( '<a href="%s" class="button button-small">%s</a>', esc_url( $reprint_url ), esc_html__( 'Reprint/Retry', 'gf-printnode' ) );

		if ( ! empty( $item['pdf_path'] ) && file_exists( $item['pdf_path'] ) ) {
			$pdf_url = content_url( 'uploads/gf_printnode_previews/' . basename( $item['pdf_path'] ) );
			$actions['view_pdf'] = sprintf( '<a href="%s" target="_blank" class="button button-small">%s</a>', esc_url( $pdf_url ), esc_html__( 'View PDF', 'gf-printnode' ) );
		}

		$delete_url = wp_nonce_url( admin_url( 'admin.php?page=gf_printnode_logs&action=delete_single&log=' . $item['id'] ), 'delete_single_log_' . $item['id'] );
		$actions['delete'] = sprintf( '<a href="%s" class="button button-small" style="color: #d63638; border-color: #d63638;" onclick="return confirm(\'%s\');">%s</a>', esc_url( $delete_url ), esc_attr__( 'Are you sure you want to delete this log?', 'gf-printnode' ), esc_html__( 'Delete', 'gf-printnode' ) );

		return join( ' ', $actions );
	}

	/**
	 * Get bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'delete' => esc_html__( 'Delete', 'gf-printnode' ),
			'reprint_bulk' => esc_html__( 'Reprint', 'gf-printnode' ),
		);
	}

	/**
	 * Detect and process bulk actions.
	 */
	public function process_bulk_action() {
		
		$action = $this->current_action();

		if ( ! $action ) {
			return;
		}

		// Handle single reprint via actions column.
		if ( 'reprint' === $action ) {
			$log_id = isset( $_REQUEST['log'] ) ? absint( $_REQUEST['log'] ) : 0;
			
			if ( $log_id ) {
				check_admin_referer( 'reprint_log_' . $log_id );
				$this->trigger_reprint( array( $log_id ) );
				
				wp_safe_redirect( add_query_arg( 'reprinted', 1, admin_url( 'admin.php?page=gf_printnode_logs' ) ) );
				exit;
			}
		}

		if ( 'delete_single' === $action ) {
			$log_id = isset( $_REQUEST['log'] ) ? absint( $_REQUEST['log'] ) : 0;
			if ( $log_id ) {
				check_admin_referer( 'delete_single_log_' . $log_id );
				require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
				global $wpdb;
				$log = GF_PrintNode_DB::get_log( $log_id );
				if ( $log && ! empty( $log->pdf_path ) && file_exists( $log->pdf_path ) ) {
					@unlink( $log->pdf_path );
				}
				$wpdb->delete( $wpdb->prefix . GF_PrintNode_DB::TABLE_LOGS, array( 'id' => $log_id ) );
				
				wp_safe_redirect( add_query_arg( 'deleted', 1, admin_url( 'admin.php?page=gf_printnode_logs' ) ) );
				exit;
			}
		}

		// Handle bulk deletes or reprints.
		if ( isset( $_POST['log'] ) ) {
			
			// Verify nonce here if it's a bulk action form POST
			check_admin_referer( 'bulk-' . $this->_args['plural'] );

			$log_ids = array_map( 'absint', $_POST['log'] );

			if ( 'delete' === $action ) {
				require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
				global $wpdb;
				foreach ( $log_ids as $id ) {
					$log = GF_PrintNode_DB::get_log( $id );
					if ( $log && ! empty( $log->pdf_path ) && file_exists( $log->pdf_path ) ) {
						@unlink( $log->pdf_path );
					}
					$wpdb->delete( $wpdb->prefix . GF_PrintNode_DB::TABLE_LOGS, array( 'id' => $id ) );
				}
				
				wp_safe_redirect( add_query_arg( 'deleted', count( $log_ids ), admin_url( 'admin.php?page=gf_printnode_logs' ) ) );
				exit;
			}

			if ( 'reprint_bulk' === $action ) {
				$this->trigger_reprint( $log_ids );
				wp_safe_redirect( add_query_arg( 'reprinted', count( $log_ids ), admin_url( 'admin.php?page=gf_printnode_logs' ) ) );
				exit;
			}
		}
	}

	/**
	 * Helper to reschedule existing logs for printing.
	 * This fetches the original GF entry data and re-triggers the feed.
	 */
	private function trigger_reprint( $log_ids ) {
		if ( ! class_exists( 'GFAPI' ) ) {
			return;
		}

		$addon = GF_PrintNode_AddOn::get_instance();
		
		foreach ( $log_ids as $id ) {
			$log = GF_PrintNode_DB::get_log( $id );
			if ( $log && $log->entry_id ) {
				$entry = GFAPI::get_entry( $log->entry_id );
				if ( ! is_wp_error( $entry ) ) {
					$form = GFAPI::get_form( $entry['form_id'] );
					// We get all feeds for this form and just re-process them.
					// NOTE: A more accurate reprint would save the exact HTML and just create a new AS job to print it again.
					// We will use the direct DB HTML approach for accuracy and to avoid duplicating Feed process logic unnecessarily.
					
					// However, we didn't save the original HTML in DB to save space, we generate it on the fly.
					// So let's re-trigger the feed logic.
					$feeds = $addon->get_feeds( $entry['form_id'] );
					foreach ( $feeds as $feed ) {
						if ( $addon->is_feed_condition_met( $feed, $form, $entry ) ) {
							$addon->process_feed( $feed, $entry, $form );
						}
					}
				}
			}
		}
	}
}
