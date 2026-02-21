<?php
/**
 * PrintNode API wrapper.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GF_PrintNode_API
 */
class GF_PrintNode_API {

	/**
	 * PrintNode API base URL.
	 */
	const API_URL = 'https://api.printnode.com/';

	/**
	 * API Key from settings.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// We'll get this from GF Add-on settings later.
		// Fallback simple option for now or direct passing.
	}

	/**
	 * Get the API Key from GF settings.
	 *
	 * @return string|false
	 */
	private function get_api_key() {
		if ( $this->api_key ) {
			return $this->api_key;
		}

		if ( class_exists( 'GF_PrintNode_AddOn' ) ) {
			$addon = GF_PrintNode_AddOn::get_instance();
			$this->api_key = $addon->get_plugin_setting( 'printnode_api_key' );
		}

		return ! empty( $this->api_key ) ? $this->api_key : false;
	}

	/**
	 * Execute an API request.
	 *
	 * @param string $endpoint API endpoint to hit.
	 * @param array  $args     WP Remote args.
	 * @return array|WP_Error Response from API or WP_Error.
	 */
	private function request( $endpoint, $args = array() ) {
		$api_key = $this->get_api_key();

		if ( ! $api_key ) {
			return new WP_Error( 'missing_api_key', __( 'PrintNode API key is not configured.', 'gf-printnode' ) );
		}

		$url = self::API_URL . ltrim( $endpoint, '/' );

		$default_args = array(
			'timeout' => 30, // seconds
			'headers' => array(
				'Authorization' => 'Basic ' . base64_encode( $api_key . ':' ), // PrintNode uses API Key as username, empty password.
				'Content-Type'  => 'application/json',
				'Accept'        => 'application/json',
			),
		);

		$parsed_args = wp_parse_args( $args, $default_args );

		$response = wp_remote_request( $url, $parsed_args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( $body, true );

		if ( $code >= 400 ) {
			$message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown PrintNode API error.', 'gf-printnode' );
			return new WP_Error( 'api_error_' . $code, $message, $data );
		}

		return $data;
	}

	/**
	 * Get list of printers on the account.
	 *
	 * @return array|WP_Error Array of printers or error.
	 */
	public function get_printers() {
		return $this->request( 'printers' );
	}

	/**
	 * Submit a print job.
	 *
	 * @param int    $printer_id The ID of the printer.
	 * @param string $title      The title of the document.
	 * @param string $base64_pdf The Base64 encoded PDF binary string.
	 * @param string $source     Source identifier.
	 * @return int|WP_Error Return Job ID on success or WP_Error.
	 */
	public function submit_job( $printer_id, $title, $base64_pdf, $source = 'Gravity Forms' ) {
		
		$payload = array(
			'printerId'   => absint( $printer_id ),
			'title'       => substr( sanitize_text_field( $title ), 0, 255 ),
			'contentType' => 'pdf_base64',
			'content'     => $base64_pdf,
			'source'      => sanitize_text_field( $source ),
		);

		$args = array(
			'method' => 'POST',
			'body'   => wp_json_encode( $payload ),
		);

		$response = $this->request( 'printjobs', $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// PrintNode returns just the Job ID integer on success POST to /printjobs
		if ( is_numeric( $response ) ) {
			return (int) $response;
		}

		return new WP_Error( 'unknown_response', __( 'PrintNode returned an unexpected response format.', 'gf-printnode' ), $response );
	}

	/**
	 * Get status of a specific job.
	 *
	 * @param int $job_id The PrintNode Job ID.
	 * @return array|WP_Error
	 */
	public function get_job_status( $job_id ) {
		return $this->request( 'printjobs/' . absint( $job_id ) );
	}
}
