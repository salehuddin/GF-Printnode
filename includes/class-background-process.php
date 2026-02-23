<?php
/**
 * Action Scheduler Worker.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_PrintNode_Background_Process
 */
class GF_PrintNode_Background_Process {

	/**
	 * Init.
	 */
	public static function init() {
		add_action( 'gform_printnode_process_job', array( __CLASS__, 'process_job' ), 10, 1 );
	}

	/**
	 * Main worker function executed by Action Scheduler.
	 *
	 * @param array $args The arguments array passed from the feed.
	 */
	public static function process_job( $args ) {

		$log_id         = absint( rgar( $args, 'log_id', 0 ) );
		$processed_html = rgar( $args, 'processed_html', '' );
		$pdf_width      = (float) rgar( $args, 'pdf_width', 101.6 );
		$pdf_height     = (float) rgar( $args, 'pdf_height', 50.8 );
		$feed_name      = rgar( $args, 'feed_name', 'Unnamed' );

		if ( ! $log_id || empty( $processed_html ) ) {
			return; // Invalid job payload.
		}

		require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
		require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-pdf-engine.php';
		require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-printnode-api.php';

		$log = GF_PrintNode_DB::get_log( $log_id );

		// Check if already processed to prevent duplicates in case of AS retries
		if ( $log && in_array( $log->status, array( 'sent', 'success' ) ) ) {
			return;
		}

		// Update to processing.
		GF_PrintNode_DB::update_log( $log_id, array( 'status' => 'processing' ) );

		// Generate PDF Binary. We save preview inside the generation logic.
		$base64_pdf = GF_PrintNode_PDF_Engine::generate_base64_pdf( $processed_html, $pdf_width, $pdf_height, $log_id );

		if ( is_wp_error( $base64_pdf ) ) {
			GF_PrintNode_DB::update_log( $log_id, array(
				'status'   => 'error',
				'response' => $base64_pdf->get_error_message(),
			) );
			gf_printnode_log( 'error', "Log #$log_id PDF generation failed: " . $base64_pdf->get_error_message() );
			return;
		}

		// Submit to PrintNode.
		$printer_id = $log->printer_id;
		$title = sprintf( 'GF Print: %s (Entry #%d)', $log->identifier, $log->entry_id );

		if ( $printer_id !== -1 ) {
			$api = new GF_PrintNode_API();
			$job_id_or_error = $api->submit_job( $printer_id, $title, $base64_pdf, 'GF Feed: ' . $feed_name );

			if ( is_wp_error( $job_id_or_error ) ) {
				GF_PrintNode_DB::update_log( $log_id, array(
					'status'   => 'error',
					'response' => $job_id_or_error->get_error_message(),
				) );
				gf_printnode_log( 'error', "Log #$log_id PrintNode submission failed: " . $job_id_or_error->get_error_message() );
				return;
			}

			// Success!
			GF_PrintNode_DB::update_log( $log_id, array(
				'status'   => 'sent',
				'job_id'   => $job_id_or_error,
				'response' => 'Submitted successfully to PrintNode.',
			) );
			
			gf_printnode_log( 'info', "Log #$log_id submitted successfully. PrintNode Job ID: " . $job_id_or_error );
		} else {
			// Test mode, just mark as success.
			GF_PrintNode_DB::update_log( $log_id, array(
				'status'   => 'success',
				'job_id'   => null,
				'response' => 'Test Mode: PDF Generated and saved as preview (PrintNode submission skipped).',
			) );

			gf_printnode_log( 'info', "Log #$log_id processed in Test Mode successfully." );
		}
	}
}

// Global logger helper if AddOn framework isn't loaded globally.
function gf_printnode_log( $type, $message ) {
	if ( class_exists( 'GF_PrintNode_AddOn' ) ) {
		$addon = GF_PrintNode_AddOn::get_instance();
		if ( 'error' === $type ) {
			$addon->log_error( 'GF_PrintNode: ' . $message );
		} else {
			$addon->log_debug( 'GF_PrintNode: ' . $message );
		}
	}
}
