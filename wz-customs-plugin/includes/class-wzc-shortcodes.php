<?php
/**
 * Shortcode registration.
 *
 * Six shortcodes, each rendering one block of the review. They are separate so a
 * site can put the rank changes on the front page and the full player list on
 * another, rather than being handed one monolithic embed.
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and handles the shortcodes.
 */
class WZC_Shortcodes {

	/**
	 * Hook the shortcodes up.
	 */
	public static function register() {
		add_shortcode( 'wz_customs_changes', array( __CLASS__, 'changes' ) );
		add_shortcode( 'wz_customs_bands', array( __CLASS__, 'bands' ) );
		add_shortcode( 'wz_customs_players', array( __CLASS__, 'players' ) );
		add_shortcode( 'wz_customs_player', array( __CLASS__, 'player' ) );
		add_shortcode( 'wz_customs_teams', array( __CLASS__, 'teams' ) );
		add_shortcode( 'wz_customs_rules', array( __CLASS__, 'rules' ) );
	}

	/**
	 * Enqueue the front-end assets. Called only when something renders.
	 */
	protected static function assets() {
		wp_enqueue_style( 'wz-customs' );
		wp_enqueue_script( 'wz-customs' );
	}

	/**
	 * Resolve the tournament a shortcode is talking about.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return array|null
	 */
	protected static function tournament( $atts ) {
		$date = isset( $atts['date'] ) ? trim( (string) $atts['date'] ) : '';

		return WZC_Data::tournament( $date );
	}

	/**
	 * [wz_customs_changes date=""]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function changes( $atts ) {
		$atts = shortcode_atts( array( 'date' => '' ), $atts, 'wz_customs_changes' );
		self::assets();

		$t = self::tournament( $atts );

		return $t ? WZC_Render::rank_changes( $t ) : WZC_Render::empty_notice();
	}

	/**
	 * [wz_customs_bands date=""]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function bands( $atts ) {
		$atts = shortcode_atts( array( 'date' => '' ), $atts, 'wz_customs_bands' );
		self::assets();

		$t = self::tournament( $atts );

		return $t ? WZC_Render::bands( $t ) : WZC_Render::empty_notice();
	}

	/**
	 * [wz_customs_players date="" player_page=""]
	 *
	 * `player_page` is the permalink of the page holding [wz_customs_player];
	 * supply it and every name becomes a link to that player's breakdown.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function players( $atts ) {
		$atts = shortcode_atts(
			array(
				'date'        => '',
				'player_page' => '',
			),
			$atts,
			'wz_customs_players'
		);
		self::assets();

		$t = self::tournament( $atts );
		if ( ! $t ) {
			return WZC_Render::empty_notice();
		}

		$page = '' !== $atts['player_page'] ? esc_url_raw( $atts['player_page'] ) : '';

		return WZC_Render::players( $t, $page );
	}

	/**
	 * [wz_customs_player id="" date="" back=""]
	 *
	 * With no `id` attribute the shortcode reads ?player= from the query string,
	 * so one page serves every player.
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function player( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'   => '',
				'date' => '',
				'back' => '',
			),
			$atts,
			'wz_customs_player'
		);
		self::assets();

		$t = self::tournament( $atts );
		if ( ! $t ) {
			return WZC_Render::empty_notice();
		}

		$id = trim( (string) $atts['id'] );

		if ( '' === $id && isset( $_GET['player'] ) ) {
			// Read-only lookup of a public identifier: no state changes, so there
			// is no nonce to check. It is sanitised and then only ever compared
			// against ids that are already in the payload.
			$id = sanitize_key( wp_unslash( $_GET['player'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}

		if ( '' === $id ) {
			return '<div class="wzc wzc-empty"><p>' .
				esc_html__( 'No player selected.', 'wz-customs' ) . '</p></div>';
		}

		$back = '' !== $atts['back'] ? esc_url_raw( $atts['back'] ) : '';

		return WZC_Render::player( $t, $id, $back );
	}

	/**
	 * [wz_customs_teams date="" limit="0"]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function teams( $atts ) {
		$atts = shortcode_atts(
			array(
				'date'  => '',
				'limit' => 0,
			),
			$atts,
			'wz_customs_teams'
		);
		self::assets();

		$t = self::tournament( $atts );

		return $t ? WZC_Render::teams( $t, max( 0, (int) $atts['limit'] ) ) : WZC_Render::empty_notice();
	}

	/**
	 * [wz_customs_rules date=""]
	 *
	 * @param array $atts Attributes.
	 * @return string
	 */
	public static function rules( $atts ) {
		$atts = shortcode_atts( array( 'date' => '' ), $atts, 'wz_customs_rules' );
		self::assets();

		$t = self::tournament( $atts );

		return $t ? WZC_Render::rules( $t ) : WZC_Render::empty_notice();
	}
}
