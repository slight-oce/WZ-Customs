<?php
/**
 * Minimal WordPress stubs.
 *
 * Enough of the API for the render layer to run outside WordPress, so the
 * markup can be exercised in CI without standing up a site. These are not
 * faithful reimplementations — they are the smallest thing that lets the
 * renderer execute and lets an assertion look at real output.
 *
 * @package WZCustoms
 */

define( 'ABSPATH', __DIR__ );
define( 'WZC_VERSION', 'test' );

if ( ! function_exists( 'esc_html' ) ) {
	/**
	 * Escape for HTML output.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	/**
	 * Escape for an attribute.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	/**
	 * Escape a URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url( $url ) {
		return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	/**
	 * Sanitise a URL for storage.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	function esc_url_raw( $url ) {
		return (string) $url;
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * Translate.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function __( $text, $domain = '' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	/**
	 * Translate and escape.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_html__( $text, $domain = '' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	/**
	 * Translate and escape for an attribute.
	 *
	 * @param string $text   Text.
	 * @param string $domain Domain.
	 * @return string
	 */
	function esc_attr__( $text, $domain = '' ) {
		return esc_attr( $text );
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * Pluralise.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $number Count.
	 * @param string $domain Domain.
	 * @return string
	 */
	function _n( $single, $plural, $number, $domain = '' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	/**
	 * Capability check. Tests render as an anonymous visitor.
	 *
	 * @param string $capability Capability.
	 * @return bool
	 */
	function current_user_can( $capability ) {
		return false;
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * Append a query argument.
	 *
	 * @param string $key   Key.
	 * @param string $value Value.
	 * @param string $url   URL.
	 * @return string
	 */
	function add_query_arg( $key, $value, $url ) {
		$glue = false === strpos( $url, '?' ) ? '?' : '&';

		return $url . $glue . rawurlencode( $key ) . '=' . rawurlencode( $value );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Return the payload the test installed.
	 *
	 * @param string $key Transient key.
	 * @return mixed
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['wzc_test_payload'] ) ? $GLOBALS['wzc_test_payload'] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * No-op.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $ttl   Lifetime.
	 * @return bool
	 */
	function set_transient( $key, $value, $ttl ) {
		return true;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Options are not exercised by the render tests.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Default.
	 * @return mixed
	 */
	function get_option( $key, $default = false ) {
		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	/**
	 * No-op.
	 *
	 * @param string $key      Key.
	 * @param mixed  $value    Value.
	 * @param bool   $autoload Autoload.
	 * @return bool
	 */
	function update_option( $key, $value, $autoload = true ) {
		return true;
	}
}
