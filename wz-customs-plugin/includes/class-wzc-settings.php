<?php
/**
 * Settings screen.
 *
 * Two settings and a refresh button. The screen's real job is the status panel:
 * it says when the data was last fetched, and — importantly — whether anything
 * had to be withheld. A guard that silently does its job is a guard nobody
 * knows has fired.
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the options page.
 */
class WZC_Settings {

	const PAGE  = 'wz-customs';
	const GROUP = 'wzc_settings';

	/**
	 * Hook everything up.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'settings' ) );
		add_action( 'admin_post_wzc_refresh', array( __CLASS__, 'handle_refresh' ) );
		add_action( 'admin_notices', array( __CLASS__, 'leak_notice' ) );
	}

	/**
	 * Add the menu entry.
	 */
	public static function menu() {
		add_options_page(
			__( 'WZ Customs', 'wz-customs' ),
			__( 'WZ Customs', 'wz-customs' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'screen' )
		);
	}

	/**
	 * Register the two settings.
	 */
	public static function settings() {
		register_setting(
			self::GROUP,
			WZC_Source::OPTION_URL,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( __CLASS__, 'sanitize_url' ),
				'default'           => WZC_Source::DEFAULT_URL,
			)
		);

		register_setting(
			self::GROUP,
			WZC_Source::OPTION_TTL,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( __CLASS__, 'sanitize_ttl' ),
				'default'           => WZC_Source::DEFAULT_TTL,
			)
		);
	}

	/**
	 * Validate the source URL and drop the cache when it changes.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize_url( $value ) {
		$url = esc_url_raw( trim( (string) $value ) );

		if ( '' === $url ) {
			$url = WZC_Source::DEFAULT_URL;
		}

		WZC_Source::flush();

		return $url;
	}

	/**
	 * Clamp the cache lifetime to something sane.
	 *
	 * @param mixed $value Submitted value.
	 * @return int
	 */
	public static function sanitize_ttl( $value ) {
		$ttl = (int) $value;

		if ( $ttl < 60 ) {
			$ttl = 60;
		}
		if ( $ttl > DAY_IN_SECONDS ) {
			$ttl = DAY_IN_SECONDS;
		}

		WZC_Source::flush();

		return $ttl;
	}

	/**
	 * Handle the "refresh now" button.
	 */
	public static function handle_refresh() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'wz-customs' ) );
		}

		check_admin_referer( 'wzc_refresh' );

		WZC_Source::flush();
		WZC_Source::get( true );

		wp_safe_redirect( add_query_arg( 'wzc_refreshed', '1', admin_url( 'options-general.php?page=' . self::PAGE ) ) );
		exit;
	}

	/**
	 * Warn on every admin screen if the last fetch contained private material.
	 *
	 * This is deliberately not confined to the plugin's own settings page. If the
	 * source URL is pointing at an admin build, that is worth interrupting
	 * whatever the administrator happened to be doing.
	 */
	public static function leak_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = WZC_Source::last_status();
		if ( empty( $status['withheld'] ) || ! is_array( $status['withheld'] ) ) {
			return;
		}

		$items = array();
		foreach ( (array) $status['withheld'] as $item ) {
			$items[] = '<code>' . esc_html( (string) $item ) . '</code>';
		}

		echo '<div class="notice notice-error"><p><strong>' .
			esc_html__( 'WZ Customs: the configured source contains private material.', 'wz-customs' ) .
			'</strong></p><p>' .
			esc_html__(
				'It was withheld and never stored, so nothing has been published. This normally means the URL points at an admin build rather than the public one.',
				'wz-customs'
			) .
			'</p><p>' . implode( ', ', $items ) . '</p></div>';
	}

	/**
	 * Render the settings screen.
	 */
	public static function screen() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$status = WZC_Source::last_status();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'WZ Customs', 'wz-customs' ); ?></h1>

			<?php if ( isset( $_GET['wzc_refreshed'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Data refreshed.', 'wz-customs' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="wzc_url"><?php esc_html_e( 'Data URL', 'wz-customs' ); ?></label>
						</th>
						<td>
							<input name="<?php echo esc_attr( WZC_Source::OPTION_URL ); ?>"
								id="wzc_url" type="url" class="regular-text"
								value="<?php echo esc_attr( WZC_Source::url() ); ?>">
							<p class="description">
								<?php esc_html_e( 'The published data.json. Use the public build — the plugin will refuse to render anything private it finds here.', 'wz-customs' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="wzc_ttl"><?php esc_html_e( 'Cache lifetime', 'wz-customs' ); ?></label>
						</th>
						<td>
							<input name="<?php echo esc_attr( WZC_Source::OPTION_TTL ); ?>"
								id="wzc_ttl" type="number" min="60" max="86400" step="60"
								value="<?php echo esc_attr( (string) WZC_Source::ttl() ); ?>">
							<p class="description"><?php esc_html_e( 'Seconds between refetches. 60 to 86400.', 'wz-customs' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Status', 'wz-customs' ); ?></h2>
			<table class="widefat striped" style="max-width:60em">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last checked', 'wz-customs' ); ?></th>
						<td>
							<?php
							echo empty( $status['checked'] )
								? esc_html__( 'never', 'wz-customs' )
								: esc_html(
									sprintf(
										/* translators: %s: human-readable time difference. */
										__( '%s ago', 'wz-customs' ),
										human_time_diff( (int) $status['checked'] )
									)
								);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Result', 'wz-customs' ); ?></th>
						<td>
							<?php if ( ! empty( $status['ok'] ) ) : ?>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of tournaments loaded. */
										_n( 'OK — %d tournament loaded', 'OK — %d tournaments loaded', (int) ( isset( $status['tournaments'] ) ? $status['tournaments'] : 0 ), 'wz-customs' ),
										(int) ( isset( $status['tournaments'] ) ? $status['tournaments'] : 0 )
									)
								);
								?>
							<?php else : ?>
								<span style="color:#b32d2e">
									<?php echo esc_html( isset( $status['error'] ) ? $status['error'] : __( 'Failed', 'wz-customs' ) ); ?>
								</span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Withheld', 'wz-customs' ); ?></th>
						<td>
							<?php if ( empty( $status['withheld'] ) ) : ?>
								<?php esc_html_e( 'Nothing — the source was already a clean public build.', 'wz-customs' ); ?>
							<?php else : ?>
								<strong style="color:#b32d2e">
									<?php echo esc_html( implode( ', ', (array) $status['withheld'] ) ); ?>
								</strong>
							<?php endif; ?>
						</td>
					</tr>
					<?php if ( ! empty( $status['unknown'] ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Not rendered', 'wz-customs' ); ?></th>
							<td>
								<?php echo esc_html( implode( ', ', (array) $status['unknown'] ) ); ?>
								<p class="description">
									<?php esc_html_e( 'Fields present upstream that this version of the plugin does not know about. Not a privacy problem.', 'wz-customs' ); ?>
								</p>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
				<input type="hidden" name="action" value="wzc_refresh">
				<?php wp_nonce_field( 'wzc_refresh' ); ?>
				<?php submit_button( __( 'Refresh now', 'wz-customs' ), 'secondary', 'submit', false ); ?>
			</form>

			<h2><?php esc_html_e( 'Shortcodes', 'wz-customs' ); ?></h2>
			<table class="widefat striped" style="max-width:60em">
				<tbody>
					<tr><td><code>[wz_customs_changes]</code></td><td><?php esc_html_e( 'Promotions and holds. Never demotions.', 'wz-customs' ); ?></td></tr>
					<tr><td><code>[wz_customs_bands]</code></td><td><?php esc_html_e( 'Where the rank bands sit.', 'wz-customs' ); ?></td></tr>
					<tr><td><code>[wz_customs_players player_page="/players/"]</code></td><td><?php esc_html_e( 'Everyone, grouped by rank, with a filter box.', 'wz-customs' ); ?></td></tr>
					<tr><td><code>[wz_customs_player]</code></td><td><?php esc_html_e( 'One player. Reads ?player= from the URL unless given id="".', 'wz-customs' ); ?></td></tr>
					<tr><td><code>[wz_customs_teams limit="10"]</code></td><td><?php esc_html_e( 'Squad standings.', 'wz-customs' ); ?></td></tr>
					<tr><td><code>[wz_customs_rules]</code></td><td><?php esc_html_e( 'The ranking rule, with this event\'s numbers.', 'wz-customs' ); ?></td></tr>
				</tbody>
			</table>
			<p class="description">
				<?php esc_html_e( 'All of them take date="YYYY-MM-DD" to pin an older tournament. Left off, they show the most recent.', 'wz-customs' ); ?>
			</p>
		</div>
		<?php
	}
}
