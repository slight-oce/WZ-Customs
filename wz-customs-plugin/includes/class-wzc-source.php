<?php
/**
 * Fetching and caching the published payload.
 *
 * Only the sanitised payload is ever written to the options table. If the URL is
 * pointed at an admin build by mistake, the private fields are dropped before
 * anything is stored, so the site's database never holds them and clearing the
 * cache is not a cleanup step.
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Retrieves data.json and keeps a cached copy.
 */
class WZC_Source {

	const OPTION_URL   = 'wzc_data_url';
	const OPTION_TTL   = 'wzc_cache_ttl';
	const TRANSIENT    = 'wzc_payload';
	const STATUS       = 'wzc_status';
	const DEFAULT_URL  = 'https://slight-oce.github.io/wz-customs/data.json';
	const DEFAULT_TTL  = 900;
	const MAX_BYTES    = 4194304;

	/**
	 * Configured source URL.
	 *
	 * @return string
	 */
	public static function url() {
		$url = get_option( self::OPTION_URL, self::DEFAULT_URL );

		return is_string( $url ) && '' !== trim( $url ) ? trim( $url ) : self::DEFAULT_URL;
	}

	/**
	 * Configured cache lifetime in seconds.
	 *
	 * @return int
	 */
	public static function ttl() {
		$ttl = (int) get_option( self::OPTION_TTL, self::DEFAULT_TTL );

		return $ttl > 0 ? $ttl : self::DEFAULT_TTL;
	}

	/**
	 * The publishable payload, from cache when possible.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array Shaped { tournaments: [...] }. Empty on failure.
	 */
	public static function get( $force = false ) {
		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$payload = self::fetch();

		if ( null === $payload ) {
			// Serve the last good copy rather than blanking the page because
			// GitHub Pages had a bad minute. The settings screen shows the error.
			$stale = get_option( self::TRANSIENT . '_last_good' );

			return is_array( $stale ) ? $stale : array( 'tournaments' => array() );
		}

		set_transient( self::TRANSIENT, $payload, self::ttl() );
		update_option( self::TRANSIENT . '_last_good', $payload, false );

		return $payload;
	}

	/**
	 * Perform the HTTP request and sanitise the result.
	 *
	 * @return array|null Null on any failure.
	 */
	protected static function fetch() {
		$url = self::url();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'user-agent' => 'wz-customs-wp/' . WZC_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			self::status( false, $response->get_error_message() );

			return null;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			self::status(
				false,
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'Source returned HTTP %d', 'wz-customs' ),
					$code
				)
			);

			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( strlen( $body ) > self::MAX_BYTES ) {
			self::status( false, __( 'Source is larger than the 4 MB ceiling.', 'wz-customs' ) );

			return null;
		}

		$raw = json_decode( $body, true );
		if ( null === $raw || ! is_array( $raw ) ) {
			self::status( false, __( 'Source is not valid JSON.', 'wz-customs' ) );

			return null;
		}

		$privacy = new WZC_Privacy();
		$payload = $privacy->sanitize( $raw );

		self::status(
			true,
			'',
			array(
				'tournaments' => count( $payload['tournaments'] ),
				'withheld'    => $privacy->report(),
				'unknown'     => $privacy->unknown(),
			)
		);

		return $payload;
	}

	/**
	 * Record the outcome of the last fetch for the settings screen.
	 *
	 * @param bool   $ok      Whether the fetch succeeded.
	 * @param string $error   Error message when it did not.
	 * @param array  $extra   Additional detail.
	 */
	protected static function status( $ok, $error = '', $extra = array() ) {
		update_option(
			self::STATUS,
			array_merge(
				array(
					'ok'      => (bool) $ok,
					'error'   => $error,
					'checked' => time(),
					'url'     => self::url(),
				),
				$extra
			),
			false
		);
	}

	/**
	 * Last recorded fetch status.
	 *
	 * @return array
	 */
	public static function last_status() {
		$status = get_option( self::STATUS, array() );

		return is_array( $status ) ? $status : array();
	}

	/**
	 * Drop the cache. The next front-end request refetches.
	 */
	public static function flush() {
		delete_transient( self::TRANSIENT );
	}
}
