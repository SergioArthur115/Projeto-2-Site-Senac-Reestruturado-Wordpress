<?php
/**
 * Remote template-library HTTP client.
 *
 * Owns the single remote endpoint used by the MetForm template library and
 * turns every transport, HTTP, and JSON failure into a `WP_Error` so callers
 * never have to inspect raw `wp_remote_*` responses.
 *
 * @package MetForm\Core\Forms\Template_Library
 * @since 4.1.6
 */

namespace MetForm\Core\Forms\Template_Library;

defined( 'ABSPATH' ) || exit;

/**
 * Fetches template data from the wpmet layout API.
 *
 * @since 4.1.6
 */
class Remote_Api {

	/**
	 * Remote layout-manager endpoint.
	 *
	 * Speaks the ElementsKit envelope: `?action=get_layout_list` and
	 * `?action=get_layout_data&layout_id={id}`.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const API_URL = 'https://api.wpmet.com/metform/wp-json/mf-layout/v1/layout-manager-api';

	/**
	 * Prefix applied to every transient written by this class.
	 *
	 * @since 4.1.6
	 * @var string
	 */
	const CACHE_PREFIX = 'metform_tpl_';

	/**
	 * Lifetime, in seconds, of a cached remote response.
	 *
	 * @since 4.1.6
	 * @var int
	 */
	const CACHE_TTL = 3 * HOUR_IN_SECONDS;

	/**
	 * Seconds to wait before a remote request is abandoned.
	 *
	 * @since 4.1.6
	 * @var int
	 */
	const TIMEOUT = 20;

	/**
	 * Resolve the endpoint URL, allowing local API sites during development.
	 *
	 * @since 4.1.6
	 *
	 * @return string Endpoint URL.
	 */
	public static function get_api_url() {
		/**
		 * Filters the template-library endpoint URL.
		 *
		 * @since 4.1.6
		 *
		 * @param string $url Endpoint URL.
		 */
		return (string) apply_filters( 'metform_template_library_api_url', self::API_URL );
	}

	/**
	 * Build a request URL for one endpoint action.
	 *
	 * The active package is always sent, plus `key` and `checksum` when an
	 * internal API key is configured. The customer licence itself goes in
	 * headers; see get_licence_headers().
	 *
	 * @since 4.1.6
	 *
	 * @param string $action    Endpoint action.
	 * @param array  $args      Optional extra query arguments.
	 * @param int    $layout_id Layout ID the checksum is bound to, or 0.
	 * @return string Absolute request URL.
	 */
	public static function build_url( $action, array $args = [], $layout_id = 0 ) {
		$args = array_merge(
			$args,
			[
				'action'         => $action,
				'plugin_package' => Access::get_package(),
			]
		);

		$key = self::get_api_key();

		if ( '' !== $key ) {
			$args['key']      = $key;
			$args['checksum'] = md5( $key . '|' . (int) $layout_id );
		}

		return add_query_arg( array_map( 'rawurlencode', $args ), self::get_api_url() );
	}

	/**
	 * Resolve the API key presented to the template library.
	 *
	 * @since 4.1.6
	 *
	 * @return string API key, or an empty string when none is configured.
	 */
	private static function get_api_key() {
		$key = defined( 'MF_LAYOUT_CLIENT_KEY' ) ? \MF_LAYOUT_CLIENT_KEY : get_option( 'mf_layout_client_key', '' );

		/**
		 * Filters the API key sent to the template library.
		 *
		 * @since 4.1.6
		 *
		 * @param string $key API key.
		 */
		return (string) apply_filters( 'metform_template_library_key', (string) $key );
	}

	/**
	 * Build the headers that prove this site's MetForm Pro licence.
	 *
	 * Sent only when the site resolves to the Pro package. They travel as
	 * headers rather than query arguments so the licence key stays out of URLs
	 * and server access logs. The site URL matches what MetForm Pro reports to
	 * the licence server, so the library can check the key against the same
	 * activation.
	 *
	 * @since 4.1.6
	 *
	 * @return array Request headers, empty when no licence is active.
	 */
	private static function get_licence_headers() {
		if ( Access::PACKAGE_PRO !== Access::get_package() || ! class_exists( '\MetForm_Pro\Libs\License' ) ) {
			return [];
		}

		$licence_key = (string) \MetForm_Pro\Libs\License::instance()->get_license();

		if ( '' === $licence_key ) {
			return [];
		}

		return [
			'X-MF-License-Key' => $licence_key,
			'X-MF-Site-Url'    => get_bloginfo( 'url' ),
		];
	}

	/**
	 * Retrieve a single remote layout by ID.
	 *
	 * @since 4.1.6
	 *
	 * @param int $template_id Remote layout ID.
	 * @return array|\WP_Error Envelope `{content, settings, metadata, metform_content}`,
	 *                         or an error on failure.
	 */
	public static function get_template( $template_id ) {
		$template_id = absint( $template_id );

		if ( $template_id < 1 ) {
			return new \WP_Error(
				'metform_invalid_template',
				esc_html__( 'A valid template ID is required.', 'metform' ),
				[ 'status' => 400 ]
			);
		}

		return self::get_json(
			self::build_url( 'get_layout_data', [ 'layout_id' => $template_id ], $template_id )
		);
	}

	/**
	 * Retrieve the full remote layout listing.
	 *
	 * The endpoint returns the whole library in one response, so there is no
	 * pagination.
	 *
	 * @since 4.1.6
	 *
	 * @param array $args Optional `type`, `category`, `search`.
	 * @return array|\WP_Error Envelope `{has_pro, license_status, layout_count,
	 *                         taxonomies, layouts}`, or an error on failure.
	 */
	public static function get_templates( array $args = [] ) {
		$allowed = array_flip( [ 'type', 'category', 'search' ] );
		$args    = array_filter( array_intersect_key( $args, $allowed ), 'strlen' );

		return self::get_json( self::build_url( 'get_layout_list', $args ) );
	}

	/**
	 * Perform a cached GET request and decode the JSON body.
	 *
	 * @since 4.1.6
	 *
	 * @param string $url Absolute request URL.
	 * @return array|\WP_Error Decoded response body, or an error on failure.
	 */
	private static function get_json( $url ) {
		$licence   = self::get_licence_headers();
		// The licence changes what the library returns, so it is part of the key.
		$cache_key = self::CACHE_PREFIX . md5( $url . '|' . implode( '|', $licence ) );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout' => self::TIMEOUT,
				'headers' => array_merge( [ 'Accept' => 'application/json' ], $licence ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'metform_template_request_failed',
				$response->get_error_message(),
				[ 'status' => 502 ]
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			// Pass through the library's own reason and client-error status, so a
			// Pro refusal reaches the modal as a 403 instead of a generic 502.
			$message = ( is_array( $body ) && ! empty( $body['message'] ) )
				? sanitize_text_field( $body['message'] )
				: sprintf(
					/* translators: %d: HTTP status code returned by the template library. */
					esc_html__( 'The template library returned HTTP %d.', 'metform' ),
					$code
				);

			return new \WP_Error(
				'metform_template_request_failed',
				$message,
				[ 'status' => ( $code >= 400 && $code < 500 ) ? $code : 502 ]
			);
		}

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'metform_template_invalid_json',
				esc_html__( 'The template library returned an unreadable response.', 'metform' ),
				[ 'status' => 502 ]
			);
		}

		set_transient( $cache_key, $body, self::CACHE_TTL );

		return $body;
	}

	/**
	 * Delete every cached remote response.
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	public static function flush_cache() {
		global $wpdb;

		foreach ( [ '_transient_', '_transient_timeout_' ] as $prefix ) {
			$like = $wpdb->esc_like( $prefix . self::CACHE_PREFIX ) . '%';

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bulk transient cleanup has no Core API.
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		}
	}
}
