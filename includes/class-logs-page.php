<?php
/**
 * Print Logs Admin Page.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_PrintNode_Logs_Page
 */
class GF_PrintNode_Logs_Page {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ) );
	}

	/**
	 * Register the menu page.
	 */
	public static function add_menu_page() {
		add_menu_page(
			esc_html__( 'Print Logs', 'gf-printnode' ),
			esc_html__( 'Print Logs', 'gf-printnode' ),
			'manage_options', // Or a custom capability if defined later.
			'gf_printnode_logs',
			array( __CLASS__, 'render_page' ),
			'dashicons-printer',
			58
		);
	}

	/**
	 * Render the page content.
	 */
	public static function render_page() {
		require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-logs-list-table.php';

		$list_table = new GF_PrintNode_Logs_List_Table();
		$list_table->prepare_items();
		
		?>
		<div class="wrap" id="gf-printnode-logs-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Print Logs', 'gf-printnode' ); ?></h1>

			<?php
			if ( isset( $_GET['deleted'] ) ) {
				$deleted = absint( $_GET['deleted'] );
				echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%s log deleted.', '%s logs deleted.', $deleted, 'gf-printnode' ), $deleted ) . '</p></div>';
			}
			if ( isset( $_GET['reprinted'] ) ) {
				$reprinted = absint( $_GET['reprinted'] );
				echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( _n( '%s job rescheduled for reprint.', '%s jobs rescheduled for reprint.', $reprinted, 'gf-printnode' ), $reprinted ) . '</p></div>';
			}
			?>

			<form method="get">
				<input type="hidden" name="page" value="gf_printnode_logs" />
				<?php 
				$list_table->search_box( esc_html__( 'Search Logs', 'gf-printnode' ), 'gf-printnode-search' ); 
				?>
			</form>
			
			<form id="gf-printnode-logs-filter" method="get">
				<input type="hidden" name="page" value="gf_printnode_logs" />
				<div class="alignleft actions">
					<select name="status">
						<option value="all"><?php esc_html_e( 'All Statuses', 'gf-printnode' ); ?></option>
						<option value="queued" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'queued' ); ?>><?php esc_html_e( 'Queued', 'gf-printnode' ); ?></option>
						<option value="processing" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'processing' ); ?>><?php esc_html_e( 'Processing', 'gf-printnode' ); ?></option>
						<option value="sent" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'sent' ); ?>><?php esc_html_e( 'Sent', 'gf-printnode' ); ?></option>
						<option value="error" <?php selected( isset( $_GET['status'] ) ? $_GET['status'] : '', 'error' ); ?>><?php esc_html_e( 'Error', 'gf-printnode' ); ?></option>
					</select>
					<input type="submit" name="filter_action" id="post-query-submit" class="button" value="<?php esc_attr_e( 'Filter', 'gf-printnode' ); ?>">
				</div>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<style>
			.gf-printnode-status {
				display: inline-block;
				padding: 3px 6px;
				border-radius: 3px;
				font-size: 11px;
				font-weight: 600;
			}
			.status-info { background: #e5f5fa; color: #007cba; }
			.status-success { background: #edfaef; color: #008a20; }
			.status-error { background: #fcf0f1; color: #d63638; }
			.status-default { background: #f0f0f1; color: #3c434a; }
		</style>
		<?php
	}
}
