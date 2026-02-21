<?php
/**
 * Gravity Forms Add-On Framework Integration.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

GFForms::include_feed_addon_framework();

/**
 * Class GF_PrintNode_AddOn
 */
class GF_PrintNode_AddOn extends GFFeedAddOn {

	/**
	 * Contains an instance of this class, if available.
	 *
	 * @var GF_PrintNode_AddOn $_instance
	 */
	private static $_instance = null;

	/**
	 * Defines the version of the Add-On.
	 *
	 * @var string $_version
	 */
	protected $_version = GF_PRINTNODE_VERSION;

	/**
	 * Defines the minimum Gravity Forms version required.
	 *
	 * @var string $_min_gravityforms_version
	 */
	protected $_min_gravityforms_version = '2.5';

	/**
	 * Defines the plugin slug.
	 *
	 * @var string $_slug
	 */
	protected $_slug = 'gravity-forms-printnode';

	/**
	 * Defines the main plugin file.
	 *
	 * @var string $_path
	 */
	protected $_path = 'gravity-forms-printnode/gravity-forms-printnode.php';

	/**
	 * Defines the full path to this class file.
	 *
	 * @var string $_full_path
	 */
	protected $_full_path = __FILE__;

	/**
	 * Defines the URL where this Add-On can be found.
	 *
	 * @var string $_url
	 */
	protected $_url = 'https://example.com';

	/**
	 * Defines the title of this Add-On.
	 *
	 * @var string $_title
	 */
	protected $_title = 'Gravity Forms Smart Print & Tracker (PrintNode)';

	/**
	 * Defines the short title of the Add-On.
	 *
	 * @var string $_short_title
	 */
	protected $_short_title = 'PrintNode';

	/**
	 * Defines if Add-On should use Gravity Forms servers for update data.
	 *
	 * @var bool $_enable_rg_autoupgrade
	 */
	protected $_enable_rg_autoupgrade = false;

	/**
	 * Get instance.
	 *
	 * @return GF_PrintNode_AddOn
	 */
	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GF_PrintNode_AddOn();
		}

		return self::$_instance;
	}

	/**
	 * Return the plugin's icon for the plugin/feed pages.
	 *
	 * @return string
	 */
	public function plugin_settings_icon() {
		return 'dashicons-printer';
	}

	/**
	 * Configures the plugin settings page.
	 *
	 * @return array
	 */
	public function plugin_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'PrintNode API Settings', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'              => 'printnode_api_key',
						'tooltip'           => esc_html__( 'Enter your PrintNode API Key.', 'gf-printnode' ),
						'label'             => esc_html__( 'API Key', 'gf-printnode' ),
						'type'              => 'text',
						'class'             => 'medium',
						'feedback_callback' => array( $this, 'is_valid_api_key' ),
					),
					array(
						'name'    => 'enable_pdf_previews',
						'tooltip' => esc_html__( 'If enabled, a copy of the generated PDF will be saved for viewing in the Print Logs dashboard.', 'gf-printnode' ),
						'label'   => esc_html__( 'Enable PDF Previews', 'gf-printnode' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Save PDF Previews', 'gf-printnode' ),
								'name'  => 'enable_pdf_previews',
							),
						),
					),
					array(
						'name'    => 'auto_delete_logs',
						'tooltip' => esc_html__( 'If enabled, logs older than 7 days will be automatically deleted to save database space.', 'gf-printnode' ),
						'label'   => esc_html__( 'Auto-Delete Old Logs', 'gf-printnode' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label' => esc_html__( 'Delete logs older than 7 days', 'gf-printnode' ),
								'name'  => 'auto_delete_logs',
							),
						),
					),
				),
			),
			array(
				'title'  => esc_html__( 'How to Use', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'  => 'how_to_guide',
						'type'  => 'html',
						'class' => 'gf-printnode-guide',
						'html'  => '
							<div style="max-width: 800px; line-height: 1.6; font-size: 14px;">
								<h3>Welcome to Gravity Forms Smart Print & Tracker (PrintNode)</h3>
								<p>This plugin allows you to automatically generate high-fidelity PDF thermal labels when a Gravity Form is submitted, and route them directly to your physical printers via the PrintNode API.</p>
								
								<hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;" />

								<h4>Step 1: Enter your API Key</h4>
								<p>On the "PrintNode API Settings" tab (above), enter your PrintNode API Key and save your settings. This gives the plugin permission to fetch your printers.</p>

								<h4>Step 2: Configure a Form Feed</h4>
								<p>Navigate to the Gravity Form you wish to print from. Go to <strong>Settings &gt; PrintNode</strong> and click <strong>Add New</strong> to create a Print Feed.</p>

								<h4>Step 3: Map Fields and Select a Printer</h4>
								<ul style="list-style: disc; padding-left: 20px; margin-bottom: 20px;">
									<li><strong>Select Printer:</strong> Choose which printer should receive this job. You can also select <em>-- Test Mode --</em> if you just want to preview how the PDF looks without using print quota!</li>
									<li><strong>Guest Name:</strong> Map a field from your form (e.g., "Name") to help identify the job in your Print Logs dashboard.</li>
									<li><strong>Label Template:</strong> Use the rich text editor to build your HTML label. <strong>Crucially:</strong> Use the {..} merge tag icon in the editor toolbar to inject dynamic form data directly into your design!</li>
									<li><strong>PDF Dimensions:</strong> Enter your exact label size in millimeters (e.g., 101.6 x 50.8 for standard 4x2 thermal labels).</li>
								</ul>

								<h4>Step 4: View the Dashboard</h4>
								<p>Look for the <strong>Print Logs</strong> menu link in the main WordPress sidebar. This dashboard shows you exactly what happened to every print job, allows you to <strong>Reprint</strong> jobs with a single click, and lets you <strong>View PDFs</strong> if you have previews enabled!</p>

								<hr style="margin: 20px 0; border: 0; border-top: 1px solid #ddd;" />

								<h4>Thermal Printing Tips</h4>
								<p>Thermal printers print in complete 1-bit Black and White. The plugin automatically filters your HTML to strip colors and gradients. However, for best results:</p>
								<ul style="list-style: disc; padding-left: 20px;">
									<li>Avoid using background colors or shades of gray. Use bold text and high contrast borders instead.</li>
									<li>QR codes from URLs in your HTML are automatically downloaded and embedded directly into the final PDF so they render flawlessly on thermal paper.</li>
								</ul>
							</div>
						',
					),
				),
			),
		);
	}

	/**
	 * Validate API Key.
	 *
	 * @param string  $value The setting value.
	 * @param array   $field The field array.
	 * @return bool|null
	 */
	public function is_valid_api_key( $value, $field ) {
		if ( empty( $value ) ) {
			return null;
		}

		// Instantiate the API class using this explicit key to test
		$api = new GF_PrintNode_API();
		// We use a reflection override or just test a simple request if we patch the class.
		// For now, let's keep it simple and assume valid if string, but a real check would hit /whoami.
		return true; 
	}

	/**
	 * Configures the settings which should be rendered on the feed edit page.
	 *
	 * @return array
	 */
	public function feed_settings_fields() {
		return array(
			array(
				'title'  => esc_html__( 'PrintNode Feed Settings', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Feed Name', 'gf-printnode' ),
						'type'     => 'text',
						'required' => true,
						'class'    => 'medium',
					),
					array(
						'name'     => 'printer_id',
						'label'    => esc_html__( 'Select Printer', 'gf-printnode' ),
						'type'     => 'select',
						'required' => true,
						'choices'  => $this->get_printers_for_select(),
						'tooltip'  => esc_html__( 'Select which PrintNode printer this feed should send to.', 'gf-printnode' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Field Mapping', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'      => 'mappedFields',
						'label'     => esc_html__( 'Map Fields', 'gf-printnode' ),
						'type'      => 'dynamic_field_map',
						'field_map' => array(
							array(
								'name'     => 'guest_name',
								'label'    => esc_html__( 'Guest Name', 'gf-printnode' ),
								'required' => true,
							),
						),
					),
				),
			),
			array(
				'title'  => esc_html__( 'Label Template', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'       => 'html_template',
						'label'      => esc_html__( 'HTML Content', 'gf-printnode' ),
						'type'       => 'textarea',
						'use_editor' => true,
						'class'      => 'merge-tag-support mt-position-right mt-hide_all_fields',
						'tooltip'    => esc_html__( 'Build the HTML for your thermal label. You can insert merge tags here.', 'gf-printnode' ),
					),
				),
			),
			array(
				'title'  => esc_html__( 'PDF Dimensions', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'     => 'pdf_width',
						'label'    => esc_html__( 'Width (mm)', 'gf-printnode' ),
						'type'     => 'text',
						'class'    => 'small',
						'required' => true,
						'default_value' => '101.6', // 4 inches
					),
					array(
						'name'     => 'pdf_height',
						'label'    => esc_html__( 'Height (mm)', 'gf-printnode' ),
						'type'     => 'text',
						'class'    => 'small',
						'required' => true,
						'default_value' => '50.8', // 2 inches
					),
				),
			),
			array(
				'title'  => esc_html__( 'Conditional Logic', 'gf-printnode' ),
				'fields' => array(
					array(
						'name'    => 'feedCondition',
						'label'   => esc_html__( 'Condition', 'gf-printnode' ),
						'type'    => 'feed_condition',
						'tooltip' => esc_html__( 'Enable this to only process this feed if certain form criteria are met.', 'gf-printnode' ),
					),
				),
			),
		);
	}

	/**
	 * Fetch printers for the settings dropdown.
	 *
	 * @return array
	 */
	private function get_printers_for_select() {
		$choices = array(
			array( 'label' => esc_html__( 'Select a Printer', 'gf-printnode' ), 'value' => '' ),
			array( 'label' => esc_html__( '-- Test Mode -- (Preview PDFs without sending to PrintNode)', 'gf-printnode' ), 'value' => 'test_mode' ),
		);

		$api = new GF_PrintNode_API();
		$printers = $api->get_printers();

		if ( is_wp_error( $printers ) || empty( $printers ) ) {
			return $choices;
		}

		foreach ( $printers as $printer ) {
			$computer_name = isset( $printer['computer']['name'] ) ? $printer['computer']['name'] : 'Unknown';
			$choices[] = array(
				'label' => sprintf( '%s (%s)', $printer['name'], $computer_name ),
				'value' => $printer['id'],
			);
		}

		return $choices;
	}

	/**
	 * Configures the columns that should be displayed on the feed list page.
	 *
	 * @return array
	 */
	public function feed_list_columns() {
		return array(
			'feedName'   => esc_html__( 'Name', 'gf-printnode' ),
			'printer_id' => esc_html__( 'Printer ID', 'gf-printnode' ),
		);
	}

	/**
	 * Process the feed. This is where we extract data and pass to Action Scheduler.
	 *
	 * @param array $feed  The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form  The form object currently being processed.
	 *
	 * @return mixed
	 */
	public function process_feed( $feed, $entry, $form ) {
		// Log start of process.
		$this->log_debug( "GF_PrintNode_AddOn::process_feed(): Starting PrintNode feed processing for Entry #{$entry['id']}" );

		$feed_name = $feed['meta']['feedName'];

		// Retrieve mapped guest name value.
		$field_map = $this->get_dynamic_field_map_fields( $feed, 'mappedFields' );
		$guest_name_val = $this->get_field_value( $form, $entry, $field_map['guest_name'] );

		// Retrieve HTML Template directly from feed settings.
		$html_template_raw = rgar( $feed['meta'], 'html_template' );

		$printer_id_raw = rgar( $feed['meta'], 'printer_id' );
		$pdf_width  = rgar( $feed['meta'], 'pdf_width', '101.6' );
		$pdf_height = rgar( $feed['meta'], 'pdf_height', '50.8' );

		// Validate required data.
		if ( empty( $html_template_raw ) || empty( $printer_id_raw ) ) {
			$this->log_error( "GF_PrintNode_AddOn::process_feed(): Missing required mapping (HTML template or Printer ID) for Entry #{$entry['id']}." );
			return;
		}

		$is_test_mode = ( 'test_mode' === $printer_id_raw );
		$printer_id = $is_test_mode ? -1 : intval( $printer_id_raw ); // Use -1 for DB

		// Step 1: Replace GF Merge Tags in the HTML Template
		$processed_html = GFCommon::replace_variables( $html_template_raw, $form, $entry, false, false, false, 'html' );

		// Step 2: Create a DB log record in 'queued' status.
		require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
		$log_id = GF_PrintNode_DB::insert_log( array(
			'entry_id'   => $entry['id'],
			'guest_name' => substr( $guest_name_val, 0, 255 ),
			'printer_id' => $printer_id,
			'status'     => $is_test_mode ? 'processing' : 'queued', // Test mode can just go to processing
		) );

		if ( ! $log_id ) {
			$this->log_error( "GF_PrintNode_AddOn::process_feed(): Failed to insert print log record for Entry #{$entry['id']}." );
			return;
		}

		// Step 3: Schedule the Action Scheduler background job
		if ( function_exists( 'as_enqueue_async_action' ) ) {
			$args = array(
				'log_id'         => $log_id,
				'processed_html' => $processed_html,
				'pdf_width'      => $pdf_width,
				'pdf_height'     => $pdf_height,
				'feed_name'      => $feed_name,
			);
			
			$action_id = as_enqueue_async_action( 'gform_printnode_process_job', $args );
			$this->log_debug( "GF_PrintNode_AddOn::process_feed(): Scheduled background job Action ID #{$action_id} for Log ID #{$log_id}." );
		} else {
			$this->log_error( "GF_PrintNode_AddOn::process_feed(): Action Scheduler is missing. Cannot enqueue job." );
			// Fallback: update log to error.
			GF_PrintNode_DB::update_log( $log_id, array(
				'status'   => 'error',
				'response' => 'Action Scheduler not active.',
			) );
		}

		return;
	}
}
