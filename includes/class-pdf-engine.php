<?php
/**
 * PDF Engine using Dompdf.
 *
 * @package GravityFormsPrintNode
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Class GF_PrintNode_PDF_Engine
 */
class GF_PrintNode_PDF_Engine {

	/**
	 * Convert HTML to Base64 PDF string.
	 *
	 * @param string $html   The fully processed HTML to convert.
	 * @param float  $width  Width in millimeters.
	 * @param float  $height Height in millimeters.
	 * @param int    $log_id Optional log ID to save preview of PDF.
	 * @return string|WP_Error Returns Base64 encoded PDF string, or WP_Error.
	 */
	public static function generate_base64_pdf( $html, $width, $height, $log_id = 0 ) {
		// 1. Process HTML to embed remote images as base64.
		$processed_html = self::embed_images( $html );

		// 2. Inject forced thermal styling.
		$thermal_html = self::inject_thermal_css( $processed_html );

		// 3. Convert mm to points (Dompdf uses points 72 points per inch).
		// 1 mm = 2.83465 points
		$w_pt = floatval( $width ) * 2.83465;
		$h_pt = floatval( $height ) * 2.83465;

		// 4. Setup Dompdf.
		$options = new Options();
		$options->set( 'isHtml5ParserEnabled', true );
		$options->set( 'isPhpEnabled', false ); // Security
		$options->set( 'isRemoteEnabled', false ); // We handled remote images already manually.

		$dompdf = new Dompdf( $options );
		$dompdf->setPaper( array( 0, 0, $w_pt, $h_pt ) );

		// Load HTML and render.
		$dompdf->loadHtml( $thermal_html );
		
		try {
			$dompdf->render();
		} catch ( Exception $e ) {
			return new WP_Error( 'pdf_generation_error', $e->getMessage() );
		}

		$output = $dompdf->output();

		if ( empty( $output ) ) {
			return new WP_Error( 'pdf_empty', __( 'Generated PDF is empty.', 'gf-printnode' ) );
		}

		// 5. Save preview if enabled and log_id is provided.
		if ( $log_id > 0 ) {
			self::maybe_save_preview( $output, $log_id );
		}

		// 6. Return Base64 encoded PDF.
		return base64_encode( $output );
	}

	/**
	 * Scrapes HTML for <img> tags and converts remote URLs to inline Base64.
	 *
	 * @param string $html The raw HTML string.
	 * @return string Processed HTML.
	 */
	private static function embed_images( $html ) {
		// This regex finds <img src="..."> tags.
		$pattern = '/<img[^>]+src=(?:""|\'\')?(?:["\'])?([^"\'\s>]+)(?:["\'])?[^>]*>/i';

		return preg_replace_callback( $pattern, function( $matches ) {
			$img_tag = $matches[0];
			$src_url = $matches[1];

			// Only process http/https URLs. Ignore existing data URIs.
			if ( strpos( $src_url, 'http' ) === 0 ) {
				$image_data = self::fetch_image_as_base64( $src_url );
				if ( $image_data ) {
					// Replace the old src with the new base64 data URI keeping the rest of the tag intact.
					$new_img_tag = str_replace( $src_url, $image_data, $img_tag );
					return $new_img_tag;
				}
			}

			// If it fails or is already local/base64, return original tag.
			return $img_tag;
		}, $html );
	}

	/**
	 * Fetch an image from URL and convert to base64 data URI.
	 *
	 * @param string $url The image URL.
	 * @return string|false Base64 Data URI on success, false on failure.
	 */
	private static function fetch_image_as_base64( $url ) {
		$response = wp_remote_get( $url, array( 'timeout' => 15 ) );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );

		if ( empty( $body ) || empty( $content_type ) ) {
			return false;
		}

		$base64 = base64_encode( $body );
		return 'data:' . $content_type . ';base64,' . $base64;
	}

	/**
	 * Injects CSS to force thermal-friendly 1-bit rendering (Black/White).
	 *
	 * @param string $html The HTML string.
	 * @return string
	 */
	private static function inject_thermal_css( $html ) {
		$css = '<style>
			/* Forced Thermal Styles */
			body, html, * {
				color: #000000 !important;
				background-color: transparent !important;
				background: none !important;
				text-shadow: none !important;
				box-shadow: none !important;
				-moz-osx-font-smoothing: grayscale !important;
				-webkit-font-smoothing: antialiased !important;
			}
			img {
				filter: grayscale(100%) contrast(1000%) !important;
			}
		</style>';

		// If <head> exists, append to head, otherwise prepend to string.
		if ( stripos( $html, '</head>' ) !== false ) {
			return str_ireplace( '</head>', $css . '</head>', $html );
		}

		return $css . $html;
	}

	/**
	 * Manages saving the PDF locally if previews are enabled.
	 *
	 * @param string $pdf_binary The raw PDF file contents.
	 * @param int    $log_id     The ID of the print log.
	 */
	private static function maybe_save_preview( $pdf_binary, $log_id ) {
		
		if ( class_exists( 'GF_PrintNode_AddOn' ) ) {
			$addon = GF_PrintNode_AddOn::get_instance();
			$enable_previews = $addon->get_plugin_setting( 'enable_pdf_previews' );
			
			if ( ! $enable_previews ) {
				return;
			}
		} else {
			return;
		}

		$upload_dir = wp_upload_dir();
		$preview_dir = $upload_dir['basedir'] . '/gf_printnode_previews';

		// Create dir if doesn't exist.
		if ( ! file_exists( $preview_dir ) ) {
			wp_mkdir_p( $preview_dir );
			// Add an index.php and .htaccess for basic directory protection.
			file_put_contents( $preview_dir . '/index.php', '<?php // Silence is golden.' );
			file_put_contents( $preview_dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.pdf$\">\n    Order allow,deny\n    Allow from all\n</FilesMatch>" );
		}

		$file_name = 'preview_log_' . $log_id . '_' . wp_generate_password( 8, false ) . '.pdf';
		$file_path = trailingslashit( $preview_dir ) . $file_name;

		$saved = file_put_contents( $file_path, $pdf_binary );

		if ( $saved ) {
			// Update the database log to store the file path.
			require_once GF_PRINTNODE_PLUGIN_DIR . 'includes/class-db.php';
			GF_PrintNode_DB::update_log( $log_id, array(
				'pdf_path' => $file_path,
			) );
		}
	}
}
