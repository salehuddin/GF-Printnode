<?php
/**
 * Plugin Name:       Gravity Forms Smart Print & Tracker (PrintNode)
 * Description:       Self-contained plugin that generates PDF labels from GF submissions and sends them to PrintNode. Includes a tracking dashboard.
 * Version:           1.0.1
 * Author:            Salehuddin
 * Text Domain:       gf-printnode
 *
 * @package           GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'GF_PRINTNODE_VERSION', '1.0.1' );
define( 'GF_PRINTNODE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GF_PRINTNODE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GF_PRINTNODE_PLUGIN_FILE', __FILE__ );

// Load Composer autoloader if it exists (for Dompdf).
if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once GF_PRINTNODE_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * The core plugin class.
 */
final class GF_PrintNode_Core {

	/**
	 * Single instance of the class.
	 */
	private static $instance = null;

	/**
	 * Main GF_PrintNode_Core Instance.
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->includes();
		$this->init_hooks();
	}

	/**
	 * Include required core files used in admin and on the frontend.
	 */
	private function includes() {
		// Include Action Scheduler.
		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		}
	}

	/**
	 * Hook into actions and filters.
	 */
	private function init_hooks() {
		register_activation_hook( GF_PRINTNODE_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( GF_PRINTNODE_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );
		add_action( 'gform_loaded', array( $this, 'register_gf_addon' ), 5 );
	}

	/**
	 * Init the plugin classes on plugins_loaded.
	 */
	public function init() {
		// Load plugin text domain.
		load_plugin_textdomain( 'gf-printnode', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Ensure GF is active.
		if ( ! class_exists( 'GFForms' ) ) {
			return;
		}

		// Load core components.
		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
		}
		
		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-printnode-api.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-printnode-api.php';
		}

		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-pdf-engine.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-pdf-engine.php';
		}

		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-background-process.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-background-process.php';
			GF_PrintNode_Background_Process::init();
		}

		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-logs-page.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-logs-page.php';
			GF_PrintNode_Logs_Page::init();
		}
	}

	/**
	 * Register GF Add-On via gform_loaded hook.
	 */
	public function register_gf_addon() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		GFForms::include_addon_framework();
		
		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-gf-printnode-addon.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-gf-printnode-addon.php';
			GFAddOn::register( 'GF_PrintNode_AddOn' );
		}
	}

	/**
	 * Plugin activation routine.
	 */
	public function activate() {
		// Include and run DB table creation on activation.
		if ( file_exists( GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
			GF_PrintNode_DB::create_tables();
		}
		
		// Ensure Action Scheduler is active when activating plugin.
		if ( ! did_action( 'action_scheduler_init' ) && file_exists( GF_PRINTNODE_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
			require_once GF_PRINTNODE_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
		}
	}

	/**
	 * Plugin deactivation routine.
	 */
	public function deactivate() {
		// Clear scheduled actions.
		if ( class_exists( 'ActionScheduler' ) ) {
			ActionScheduler_QueueCleaner::clean_clean_actions();
		}
	}
}

/**
 * Returns the main instance of GF_PrintNode_Core to prevent the need to use globals.
 */
function GF_PrintNode() {
	return GF_PrintNode_Core::instance();
}

// Global initialization.
GF_PrintNode();
