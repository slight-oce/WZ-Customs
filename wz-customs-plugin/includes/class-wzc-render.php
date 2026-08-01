<?php
/**
 * HTML for each block of the rank review.
 *
 * Everything returned here is escaped at the point of output. The `reason` text
 * on a decision is written by a human in a CSV and is the most likely place for
 * a stray angle bracket, so it goes through esc_html like everything else.
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds the front-end markup.
 */
class WZC_Render {

	/**
	 * Format a number, or an em dash when it is missing.
	 *
	 * A missing figure is shown as a dash rather than as zero. Zero is a real
	 * result upstream — it means wiped early — and the two must not look alike.
	 *
	 * @param mixed $value    Number or null.
	 * @param int   $decimals Decimal places.
	 * @param string $suffix  Appended when a value is present.
	 * @return string
	 */
	public static function num( $value, $decimals = 2, $suffix = '' ) {
		if ( null === $value || '' === $value || ! is_numeric( $value ) ) {
			return '&mdash;';
		}

		return esc_html( number_format( (float) $value, $decimals ) . $suffix );
	}

	/**
	 * Format an ISO date for display.
	 *
	 * @param string $iso ISO date.
	 * @return string
	 */
	public static function date( $iso ) {
		$time = strtotime( (string) $iso );

		return $time ? esc_html( gmdate( 'j F Y', $time ) ) : esc_html( (string) $iso );
	}

	/**
	 * A note explaining that no data could be loaded.
	 *
	 * @return string
	 */
	public static function empty_notice() {
		$html = '<div class="wzc wzc-empty"><p>' .
			esc_html__( 'No tournament data is available yet.', 'wz-customs' ) . '</p>';

		if ( current_user_can( 'manage_options' ) ) {
			$status = WZC_Source::last_status();
			$detail = isset( $status['error'] ) && '' !== $status['error']
				? $status['error']
				: __( 'Check the source URL on the WZ Customs settings screen.', 'wz-customs' );
			$html  .= '<p class="wzc-admin-only">' . esc_html( $detail ) . '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * Promotions and holds.
	 *
	 * Demotions are absent by design and there is no attribute that turns them
	 * on — see README.md.
	 *
	 * @param array $t Tournament.
	 * @return string
	 */
	public static function rank_changes( $t ) {
		$promotions = WZC_Data::decisions( $t, 'promote' );
		$holds      = WZC_Data::decisions( $t, 'hold' );

		$html = '<div class="wzc wzc-changes">';

		$html .= '<h2 class="wzc-h">' . esc_html__( 'Rank changes', 'wz-customs' ) . '</h2>';
		$html .= '<p class="wzc-sub">' . esc_html(
			sprintf(
				/* translators: %d: number of players promoted. */
				_n( '%d moved up this tournament', '%d moved up this tournament', count( $promotions ), 'wz-customs' ),
				count( $promotions )
			)
		) . '</p>';

		if ( empty( $promotions ) ) {
			$html .= '<p class="wzc-lede">' . esc_html__( 'Nobody moved up at this event.', 'wz-customs' ) . '</p>';
		} else {
			$html .= '<div class="wzc-cards">';
			foreach ( $promotions as $d ) {
				$html .= self::card(
					$t,
					$d,
					__( 'Moved up', 'wz-customs' ),
					esc_html( (string) $d['from_rank'] ) . ' &rarr; ' . esc_html( (string) $d['to_rank'] ),
					''
				);
			}
			$html .= '</div>';
		}

		if ( ! empty( $holds ) ) {
			$html .= '<h2 class="wzc-h">' . esc_html__( 'Reviewed, no change', 'wz-customs' ) . '</h2>';
			$html .= '<p class="wzc-sub">' .
				esc_html__( 'Cleared one line but not moved — and why', 'wz-customs' ) . '</p>';
			$html .= '<div class="wzc-cards">';
			foreach ( $holds as $d ) {
				$html .= self::card(
					$t,
					$d,
					sprintf(
						/* translators: %s: rank the player was held at. */
						__( 'Held at %s', 'wz-customs' ),
						(string) $d['from_rank']
					),
					esc_html__( 'no change', 'wz-customs' ),
					' wzc-hold'
				);
			}
			$html .= '</div>';
		}

		return $html . '</div>';
	}

	/**
	 * One decision card.
	 *
	 * @param array  $t     Tournament.
	 * @param array  $d     Decision.
	 * @param string $tag   Small label.
	 * @param string $move  Pre-escaped movement line.
	 * @param string $extra Extra class.
	 * @return string
	 */
	protected static function card( $t, $d, $tag, $move, $extra ) {
		$player_id = isset( $d['player_id'] ) ? (string) $d['player_id'] : '';
		$reason    = isset( $d['reason'] ) ? (string) $d['reason'] : '';

		return '<div class="wzc-card' . esc_attr( $extra ) . '">' .
			'<div class="wzc-tag">' . esc_html( $tag ) . '</div>' .
			'<div class="wzc-name">' . esc_html( WZC_Data::name( $t, $player_id ) ) . '</div>' .
			'<div class="wzc-move">' . $move . '</div>' .
			'<p>' . esc_html( $reason ) . '</p>' .
			'</div>';
	}

	/**
	 * Rank bands as horizontal ranges.
	 *
	 * @param array $t Tournament.
	 * @return string
	 */
	public static function bands( $t ) {
		$ranks = WZC_Data::ranks( $t );

		if ( empty( $ranks ) ) {
			return '';
		}

		$scale = 0.0;
		foreach ( $ranks as $rank ) {
			$band  = WZC_Data::band( $t, $rank );
			$scale = max( $scale, isset( $band['hi'] ) ? (float) $band['hi'] : 0.0 );
		}
		$scale = $scale > 0 ? $scale * 1.05 : 1.0;

		$html = '<div class="wzc wzc-bands">';

		foreach ( $ranks as $rank ) {
			$band = WZC_Data::band( $t, $rank );
			if ( ! $band ) {
				continue;
			}

			$lo    = isset( $band['lo'] ) ? (float) $band['lo'] : 0.0;
			$hi    = isset( $band['hi'] ) ? (float) $band['hi'] : 0.0;
			$mean  = isset( $band['mean'] ) ? (float) $band['mean'] : 0.0;
			$count = isset( $band['n'] ) ? (int) $band['n'] : 0;

			$html .= '<div class="wzc-bandbar">' .
				'<div class="wzc-lb">' .
				'<span>' . esc_html(
					sprintf(
						/* translators: 1: rank number, 2: number of players. */
						_n( 'Rank %1$s · %2$d player', 'Rank %1$s · %2$d players', $count, 'wz-customs' ),
						$rank,
						$count
					)
				) . '</span>' .
				'<span>' . esc_html__( 'band average', 'wz-customs' ) . ' <b>' .
				self::num( $mean, 2 ) . '</b></span>' .
				'</div>' .
				'<div class="wzc-track">' .
				'<div class="wzc-rng" style="left:' . esc_attr( self::pct( $lo, $scale ) ) .
				'%;width:' . esc_attr( self::pct( $hi - $lo, $scale ) ) . '%"></div>' .
				'<div class="wzc-avg" style="left:' . esc_attr( self::pct( $mean, $scale ) ) . '%"></div>' .
				'</div>';

			if ( ! empty( $band['small'] ) ) {
				$why = isset( $band['why'] ) && $band['why']
					? (string) $band['why']
					: __( 'too few players for a usable spread', 'wz-customs' );
				$html .= '<div class="wzc-bandnote">' . esc_html(
					sprintf(
						/* translators: %s: reason the band does not move players. */
						__( 'No automatic movement — %s.', 'wz-customs' ),
						$why
					)
				) . '</div>';
			}

			$html .= '</div>';
		}

		return $html . '</div>';
	}

	/**
	 * Clamp a value to a 0-100 percentage of the scale.
	 *
	 * @param float $value Value.
	 * @param float $scale Scale maximum.
	 * @return string
	 */
	protected static function pct( $value, $scale ) {
		if ( $scale <= 0 ) {
			return '0';
		}

		$pct = ( (float) $value / (float) $scale ) * 100;

		return (string) round( max( 0, min( 100, $pct ) ), 2 );
	}

	/**
	 * Every player, grouped by rank.
	 *
	 * @param array  $t         Tournament.
	 * @param string $page_url  Permalink of the page holding [wz_customs_player].
	 * @return string
	 */
	public static function players( $t, $page_url = '' ) {
		$html = '<div class="wzc wzc-players">';

		$html .= '<p class="wzc-lede">' . esc_html__(
			'Grouped by rank, alphabetical inside each. Every player is measured against the average of their own rank, not against the field.',
			'wz-customs'
		) . '</p>';

		$html .= '<input type="search" class="wzc-search" data-wzc-search="1" placeholder="' .
			esc_attr__( 'Filter by name…', 'wz-customs' ) . '" autocomplete="off">';

		$html .= '<div class="wzc-plist">';

		foreach ( WZC_Data::ranks( $t ) as $rank ) {
			$html .= self::group( $t, $rank, WZC_Data::players_at( $t, $rank ), $page_url );
		}

		$unranked = WZC_Data::players_at( $t, null );
		if ( ! empty( $unranked ) ) {
			$html .= self::group( $t, null, $unranked, $page_url );
		}

		return $html . '</div></div>';
	}

	/**
	 * One rank group table.
	 *
	 * @param array  $t        Tournament.
	 * @param mixed  $rank     Rank or null.
	 * @param array  $rows     Player rows.
	 * @param string $page_url Player page permalink.
	 * @return string
	 */
	protected static function group( $t, $rank, $rows, $page_url ) {
		if ( empty( $rows ) ) {
			return '';
		}

		$band = WZC_Data::band( $t, $rank );

		$heading = null === $rank
			? esc_html__( 'No rank on record', 'wz-customs' )
			: esc_html( sprintf( /* translators: %s: rank number. */ __( 'Rank %s', 'wz-customs' ), $rank ) );

		$meta = esc_html(
			sprintf(
				/* translators: %d: number of players. */
				_n( '%d player', '%d players', count( $rows ), 'wz-customs' ),
				count( $rows )
			)
		);
		if ( $band && isset( $band['mean'] ) ) {
			$meta .= ' &middot; ' . esc_html__( 'band average', 'wz-customs' ) . ' ' .
				self::num( $band['mean'], 2 ) . ' ' . esc_html__( 'pts/map', 'wz-customs' );
		}

		$html = '<div class="wzc-grp">' .
			'<div class="wzc-bhead">' . $heading . '</div>' .
			'<div class="wzc-bmeta">' . $meta . '</div>' .
			'<div class="wzc-scroll"><table class="wzc-table"><thead><tr>' .
			'<th>' . esc_html__( 'Player', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Maps', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Kills/map', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Avg place', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Kill share', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Pts/map', 'wz-customs' ) . '</th>' .
			'</tr></thead><tbody>';

		foreach ( $rows as $p ) {
			$name = isset( $p['p'] ) ? (string) $p['p'] : '';
			$id   = isset( $p['id'] ) ? (string) $p['id'] : '';

			$label = esc_html( $name );
			if ( '' !== $page_url && '' !== $id ) {
				$label = '<a href="' . esc_url( add_query_arg( 'player', $id, $page_url ) ) . '">' .
					esc_html( $name ) . '</a>';
			}

			$html .= '<tr data-wzc-name="' . esc_attr( strtolower( $name ) ) . '">' .
				'<td>' . $label . '</td>' .
				'<td>' . esc_html( (string) ( isset( $p['n'] ) ? $p['n'] : '' ) ) . '</td>' .
				'<td>' . self::num( isset( $p['kpm'] ) ? $p['kpm'] : null, 2 ) . '</td>' .
				'<td>' . self::num( isset( $p['plc'] ) ? $p['plc'] : null, 2 ) . '</td>' .
				'<td>' . self::num( isset( $p['ks'] ) ? $p['ks'] : null, 1, '%' ) . '</td>' .
				'<td>' . self::num( isset( $p['ppm'] ) ? $p['ppm'] : null, 2 ) . '</td>' .
				'</tr>';
		}

		return $html . '</tbody></table></div></div>';
	}

	/**
	 * One player's breakdown.
	 *
	 * @param array  $t         Tournament.
	 * @param string $player_id Player id.
	 * @param string $back_url  Permalink of the player list.
	 * @return string
	 */
	public static function player( $t, $player_id, $back_url = '' ) {
		$p = WZC_Data::player( $t, $player_id );

		if ( ! $p ) {
			return '<div class="wzc wzc-empty"><p>' .
				esc_html__( 'No record for that player at this tournament.', 'wz-customs' ) .
				'</p></div>';
		}

		$band = WZC_Data::band( $t, isset( $p['rk'] ) ? $p['rk'] : null );
		$meta = isset( $t['meta'] ) ? $t['meta'] : array();

		$html = '<div class="wzc wzc-player">';

		if ( '' !== $back_url ) {
			$html .= '<p class="wzc-back"><a href="' . esc_url( $back_url ) . '">&larr; ' .
				esc_html__( 'All players', 'wz-customs' ) . '</a></p>';
		}

		$html .= '<h2 class="wzc-h wzc-playername">' . esc_html( (string) $p['p'] ) . '</h2>';
		$html .= '<p class="wzc-lede">' .
			( isset( $p['rk'] ) && $p['rk']
				? esc_html__( 'Rank', 'wz-customs' ) . ' <b>' . esc_html( (string) $p['rk'] ) . '</b>'
				: esc_html__( 'Unranked', 'wz-customs' ) ) .
			' &middot; ' . esc_html( isset( $p['team'] ) ? (string) $p['team'] : '' ) .
			' &middot; ' . self::date( isset( $t['date'] ) ? $t['date'] : '' ) .
			'</p>';

		$html .= '<div class="wzc-stats">' .
			self::stat( __( 'Points / map', 'wz-customs' ), self::num( isset( $p['ppm'] ) ? $p['ppm'] : null, 2 ) ) .
			self::stat( __( 'Median map', 'wz-customs' ), self::num( isset( $p['med'] ) ? $p['med'] : null, 2 ) ) .
			self::stat( __( 'Kills / map', 'wz-customs' ), self::num( isset( $p['kpm'] ) ? $p['kpm'] : null, 2 ) ) .
			self::stat( __( 'Avg placement', 'wz-customs' ), self::num( isset( $p['plc'] ) ? $p['plc'] : null, 1 ) ) .
			self::stat( __( 'Kill share', 'wz-customs' ), self::num( isset( $p['ks'] ) ? $p['ks'] : null, 1, '%' ) ) .
			self::stat( __( 'Maps', 'wz-customs' ), esc_html( (string) ( isset( $p['n'] ) ? $p['n'] : '' ) ) );

		if ( ! empty( $p['sqrank'] ) ) {
			$html .= self::stat( __( 'Squad rank sum', 'wz-customs' ), esc_html( (string) $p['sqrank'] ) );
		}

		$html .= '</div>';

		$html .= self::verdict( $t, $p, $band );
		$html .= self::band_position( $t, $p, $band );
		$html .= self::flags( $p, $meta );

		return $html . '</div>';
	}

	/**
	 * One stat tile.
	 *
	 * @param string $key   Label.
	 * @param string $value Pre-escaped value.
	 * @return string
	 */
	protected static function stat( $key, $value ) {
		return '<div class="wzc-st"><div class="wzc-k">' . esc_html( $key ) .
			'</div><div class="wzc-v">' . $value . '</div></div>';
	}

	/**
	 * What happened to this player at this event.
	 *
	 * The comparison is the player's median map against their band average,
	 * because the median is what the upstream model scores on. The threshold is
	 * the band's own published promote_at, recomputed every event upstream —
	 * a fixed number would mean something different in every band.
	 *
	 * @param array      $t    Tournament.
	 * @param array      $p    Player row.
	 * @param array|null $band Band.
	 * @return string
	 */
	protected static function verdict( $t, $p, $band ) {
		$id       = isset( $p['id'] ) ? (string) $p['id'] : '';
		$decision = null;

		foreach ( WZC_Data::decisions( $t, 'promote' ) as $d ) {
			if ( isset( $d['player_id'] ) && (string) $d['player_id'] === $id ) {
				$decision = $d;
			}
		}
		if ( ! $decision ) {
			foreach ( WZC_Data::decisions( $t, 'hold' ) as $d ) {
				if ( isset( $d['player_id'] ) && (string) $d['player_id'] === $id ) {
					$decision = $d;
				}
			}
		}

		if ( $decision && 'promote' === $decision['direction'] ) {
			return self::verdict_box(
				sprintf(
					/* translators: %s: new rank. */
					__( 'Promoted to rank %s', 'wz-customs' ),
					(string) $decision['to_rank']
				),
				esc_html( (string) $decision['reason'] ),
				true
			);
		}

		if ( $decision && 'hold' === $decision['direction'] ) {
			return self::verdict_box(
				sprintf(
					/* translators: %s: rank held at. */
					__( 'Considered, held at %s', 'wz-customs' ),
					(string) $decision['from_rank']
				),
				esc_html( (string) $decision['reason'] ),
				false
			);
		}

		if ( ! $band || ! isset( $p['med'] ) || ! is_numeric( $p['med'] ) ) {
			return self::verdict_box(
				__( 'No rank on record', 'wz-customs' ),
				esc_html__(
					'There is no band to compare against for this event, so these maps do not count toward a rank change.',
					'wz-customs'
				),
				false
			);
		}

		if ( ! empty( $band['small'] ) ) {
			$why = isset( $band['why'] ) && $band['why'] ? (string) $band['why'] : '';

			return self::verdict_box(
				__( 'This band does not move automatically', 'wz-customs' ),
				esc_html(
					'' !== $why
						? sprintf(
							/* translators: %s: reason movement is disabled for the band. */
							__( 'No automatic movement here — %s. That stays a conversation rather than a calculation.', 'wz-customs' ),
							$why
						)
						: __( 'Too few players at this rank for a spread to mean anything.', 'wz-customs' )
				),
				false
			);
		}

		$median = (float) $p['med'];

		if ( ! empty( $p['standout'] ) ) {
			return self::verdict_box(
				__( 'Standout event', 'wz-customs' ),
				esc_html__(
					'Clear of the standout line and above the kill-share gate, which is an immediate promotion under the rule.',
					'wz-customs'
				),
				true
			);
		}

		if ( isset( $band['promote_at'] ) && is_numeric( $band['promote_at'] ) && $median >= (float) $band['promote_at'] ) {
			return self::verdict_box(
				__( 'Above the review line this event', 'wz-customs' ),
				esc_html(
					sprintf(
						/* translators: 1: median map score, 2: promotion line for the band. */
						__( 'A median map of %1$s against a review line of %2$s. Clearing that line in two of any three tournaments moves your rank — this event counts as one.', 'wz-customs' ),
						number_format( $median, 2 ),
						number_format( (float) $band['promote_at'], 2 )
					)
				),
				true
			);
		}

		return self::verdict_box(
			__( 'No change this tournament', 'wz-customs' ),
			esc_html(
				sprintf(
					/* translators: 1: band average, 2: player median map. */
					__( 'Your band averaged %1$s and your median map was %2$s. The review line was not crossed.', 'wz-customs' ),
					number_format( isset( $band['mean'] ) ? (float) $band['mean'] : 0, 2 ),
					number_format( $median, 2 )
				)
			),
			false
		);
	}

	/**
	 * Verdict box wrapper.
	 *
	 * @param string $heading Heading text.
	 * @param string $body    Pre-escaped body.
	 * @param bool   $good    Whether to use the positive treatment.
	 * @return string
	 */
	protected static function verdict_box( $heading, $body, $good ) {
		return '<div class="wzc-verdict' . ( $good ? ' wzc-near' : '' ) . '">' .
			'<h3>' . esc_html( $heading ) . '</h3><p>' . $body . '</p></div>';
	}

	/**
	 * Where the player sits inside their own band.
	 *
	 * @param array      $t    Tournament.
	 * @param array      $p    Player row.
	 * @param array|null $band Band.
	 * @return string
	 */
	protected static function band_position( $t, $p, $band ) {
		if ( ! $band || ! isset( $p['med'] ) || ! is_numeric( $p['med'] ) ) {
			return '';
		}

		$hi    = isset( $band['hi'] ) ? (float) $band['hi'] : 0.0;
		$scale = $hi > 0 ? $hi * 1.05 : 1.0;
		$lo    = isset( $band['lo'] ) ? (float) $band['lo'] : 0.0;
		$mean  = isset( $band['mean'] ) ? (float) $band['mean'] : 0.0;

		return '<h3 class="wzc-h3">' . esc_html(
			sprintf(
				/* translators: %s: rank number. */
				__( 'You against rank %s', 'wz-customs' ),
				(string) $p['rk']
			)
		) . '</h3>' .
			'<p class="wzc-sub">' .
			esc_html__( 'Median map, against the spread of your own band', 'wz-customs' ) . '</p>' .
			'<div class="wzc-bandbar"><div class="wzc-track">' .
			'<div class="wzc-rng" style="left:' . esc_attr( self::pct( $lo, $scale ) ) .
			'%;width:' . esc_attr( self::pct( $hi - $lo, $scale ) ) . '%"></div>' .
			'<div class="wzc-avg" style="left:' . esc_attr( self::pct( $mean, $scale ) ) . '%"></div>' .
			'<div class="wzc-you" style="left:' . esc_attr( self::pct( (float) $p['med'], $scale ) ) . '%"></div>' .
			'</div></div>' .
			'<p class="wzc-fine">' . esc_html__(
				'Kill share is how much of your squad\'s kills were yours — it is there so that carrying a weak squad and riding a strong one do not read the same.',
				'wz-customs'
			) . '</p>';
	}

	/**
	 * Caveats attached to this player's data.
	 *
	 * @param array $p    Player row.
	 * @param array $meta Tournament meta.
	 * @return string
	 */
	protected static function flags( $p, $meta ) {
		$flags = array();

		if ( ! empty( $p['imp'] ) ) {
			$flags[] = sprintf(
				/* translators: 1: number of estimated maps, 2: total maps played. */
				__( '%1$d of your %2$d maps had a squad total posted but no scoreboard, so your share of those kills is estimated from your other maps. Post a squad scoreboard and it becomes exact.', 'wz-customs' ),
				(int) $p['imp'],
				(int) $p['n']
			);
		}

		if ( isset( $p['sqrank_vs_field'] ) && is_numeric( $p['sqrank_vs_field'] ) && (float) $p['sqrank_vs_field'] <= -2 ) {
			$flags[] = sprintf(
				/* translators: 1: squad rank sum, 2: field median, 3: how far below the median. */
				__( 'Your squad\'s ranks totalled %1$s against a field median of %2$s — %3$s light. That is recorded against this result and weighed in any review, but it is not an automatic exemption.', 'wz-customs' ),
				(string) $p['sqrank'],
				(string) ( isset( $meta['rank_sum_median'] ) ? $meta['rank_sum_median'] : '' ),
				(string) abs( (float) $p['sqrank_vs_field'] )
			);
		}

		if ( isset( $meta['map_count'] ) && isset( $p['n'] ) && (int) $p['n'] < (int) $meta['map_count'] ) {
			$flags[] = sprintf(
				/* translators: 1: maps played, 2: maps in the tournament. */
				__( 'You are recorded on %1$d of %2$d maps — the rest you did not play.', 'wz-customs' ),
				(int) $p['n'],
				(int) $meta['map_count']
			);
		}

		if ( empty( $flags ) ) {
			return '';
		}

		$html = '<div class="wzc-flagbox"><b>' . esc_html__( 'About your data', 'wz-customs' ) . '</b>';
		foreach ( $flags as $flag ) {
			$html .= '<p>' . esc_html( $flag ) . '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * Squad standings.
	 *
	 * @param array $t     Tournament.
	 * @param int   $limit Rows to show, 0 for all.
	 * @return string
	 */
	public static function teams( $t, $limit = 0 ) {
		if ( ! isset( $t['teams'] ) || ! is_array( $t['teams'] ) || empty( $t['teams'] ) ) {
			return '';
		}

		$rows = $limit > 0 ? array_slice( $t['teams'], 0, $limit ) : $t['teams'];

		$html = '<div class="wzc wzc-teams"><div class="wzc-scroll"><table class="wzc-table"><thead><tr>' .
			'<th>#</th>' .
			'<th>' . esc_html__( 'Squad', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Points', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Kills', 'wz-customs' ) . '</th>' .
			'<th>' . esc_html__( 'Avg place', 'wz-customs' ) . '</th>' .
			'</tr></thead><tbody>';

		$position = 0;
		foreach ( $rows as $row ) {
			$position++;
			$html .= '<tr>' .
				'<td>' . esc_html( (string) $position ) . '</td>' .
				'<td>' . esc_html( isset( $row['t'] ) ? (string) $row['t'] : '' ) . '</td>' .
				'<td>' . self::num( isset( $row['pts'] ) ? $row['pts'] : null, 1 ) . '</td>' .
				'<td>' . esc_html( (string) ( isset( $row['k'] ) ? $row['k'] : '' ) ) . '</td>' .
				'<td>' . self::num( isset( $row['plc'] ) ? $row['plc'] : null, 2 ) . '</td>' .
				'</tr>';
		}

		return $html . '</tbody></table></div></div>';
	}

	/**
	 * The ranking rule, with this event's figures filled in.
	 *
	 * @param array $t Tournament.
	 * @return string
	 */
	public static function rules( $t ) {
		$meta   = isset( $t['meta'] ) ? $t['meta'] : array();
		$scale  = isset( $meta['bracket'] ) ? (string) $meta['bracket'] : '';
		$floor  = isset( $meta['movement_floor'] ) ? (int) $meta['movement_floor'] : 0;
		$cap    = isset( $meta['rank_cap'] ) ? (string) $meta['rank_cap'] : '';

		$html = '<div class="wzc wzc-rules">';

		$html .= '<p class="wzc-lede">' . esc_html__(
			'Published so it can be checked. The rule is fixed before the tournament and applied to whatever the scoreboards say.',
			'wz-customs'
		) . '</p>';

		$html .= '<h3 class="wzc-h3">' . esc_html__( 'Scoring', 'wz-customs' ) . '</h3>';
		$html .= '<p class="wzc-lede">' . esc_html__(
			'Your points for a map are your own kills multiplied by your squad\'s placement multiplier. Your kills, not the squad\'s — so a good game on a squad that goes out early still counts, and a quiet game on a winning squad does not hide.',
			'wz-customs'
		) . '</p>';

		$html .= '<h3 class="wzc-h3">' . esc_html__( 'Your score for the night', 'wz-customs' ) . '</h3>';
		$html .= '<p class="wzc-lede">' . esc_html__(
			'Your median map, not your average. Line your maps up and take the middle one. Averaging them would mean that giving up once you are out of contention drags your score down hard, and that should not be a route to an easier rank.',
			'wz-customs'
		) . '</p>';

		$html .= '<h3 class="wzc-h3">' . esc_html__( 'Two ways up, one way down', 'wz-customs' ) . '</h3>';
		$html .= '<p class="wzc-lede">' . esc_html__(
			'A single tournament far enough clear of your band promotes you on the spot, but only if you took enough of your squad\'s kills for the score to be yours. Otherwise it takes two tournaments above the line out of any three. Going down takes two consecutive events, and there is no single-event demotion at all — one bad night moves nobody.',
			'wz-customs'
		) . '</p>';
		$html .= '<p class="wzc-lede">' . esc_html__(
			'The lines are recomputed after every event rather than fixed, because bands are not the same width. A flat threshold would make promotion out of a tight rank nearly impossible and out of a wide one nearly automatic.',
			'wz-customs'
		) . '</p>';

		$html .= self::bands( $t );

		if ( '' !== $scale && $floor > 0 ) {
			$html .= '<h3 class="wzc-h3">' . esc_html__( 'Why some ranks do not move', 'wz-customs' ) . '</h3>';
			$html .= '<p class="wzc-lede">' . esc_html(
				sprintf(
					/* translators: 1: bracket label, 2: rank below which nobody moves automatically. */
					__( 'This is a %1$s bracket, so in practice it fills with the strongest players eligible. The few lower-ranked players who enter have chosen to play a field above themselves, which makes them a self-selected group rather than a fair sample of their rank. Rank %2$d and below therefore do not move automatically here, however many turn up.', 'wz-customs' ),
					$scale,
					$floor - 1
				)
			) . '</p>';
		}

		if ( '' !== $cap ) {
			$html .= '<h3 class="wzc-h3">' . esc_html__( 'Moving up out of this bracket', 'wz-customs' ) . '</h3>';
			$html .= '<p class="wzc-lede">' . esc_html(
				sprintf(
					/* translators: %s: highest rank eligible for the bracket. */
					__( 'Being promoted past rank %s means you have outgrown this series and stop being eligible. That is the point of it, but it does mean going up is a real move and not a badge — which is why it takes two tournaments and not one.', 'wz-customs' ),
					$cap
				)
			) . '</p>';
		}

		$html .= '<h3 class="wzc-h3">' . esc_html__( 'The numbers shortlist, a person decides', 'wz-customs' ) . '</h3>';
		$html .= '<p class="wzc-lede">' . esc_html__(
			'Clearing the line puts you on the list. It is not automatic, because the measure has known blind spots — the clearest being that it cannot tell whether your points came from you or from the squad around you. Where a decision goes against what the numbers said, the reasoning is published with it.',
			'wz-customs'
		) . '</p>';

		$html .= '<h3 class="wzc-h3">' . esc_html__( 'What is not published here', 'wz-customs' ) . '</h3>';
		$html .= '<p class="wzc-lede">' . esc_html__(
			'Promotions and near-misses are public. Demotions are not — being moved down is between you and the organisers. If you want the reasoning behind your own rank, ask and you will get the full breakdown.',
			'wz-customs'
		) . '</p>';

		return $html . '</div>';
	}
}
