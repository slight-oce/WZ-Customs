<?php
/**
 * Read-only accessors over the sanitised payload.
 *
 * Nothing here computes a ranking figure. Every number rendered by this plugin
 * was calculated by `build.py` upstream and travels through as-is — the same
 * rule the upstream repo applies to itself, for the same reason: a figure
 * recomputed downstream is a figure that can disagree with the source without
 * anyone noticing. The one exception is the band gap on a player page, which is
 * a subtraction of two published numbers and is documented in README.md.
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Navigates tournaments, players and bands.
 */
class WZC_Data {

	/**
	 * All tournaments, oldest first.
	 *
	 * @return array
	 */
	public static function tournaments() {
		$payload = WZC_Source::get();

		return isset( $payload['tournaments'] ) && is_array( $payload['tournaments'] )
			? $payload['tournaments']
			: array();
	}

	/**
	 * One tournament by date, or the most recent one.
	 *
	 * @param string $date ISO date, or empty for the latest.
	 * @return array|null
	 */
	public static function tournament( $date = '' ) {
		$all = self::tournaments();

		if ( empty( $all ) ) {
			return null;
		}

		if ( '' === $date ) {
			return $all[ count( $all ) - 1 ];
		}

		foreach ( $all as $t ) {
			if ( isset( $t['date'] ) && $t['date'] === $date ) {
				return $t;
			}
		}

		return null;
	}

	/**
	 * One player row from a tournament.
	 *
	 * @param array  $tournament Tournament array.
	 * @param string $player_id  Player id, never a gamertag.
	 * @return array|null
	 */
	public static function player( $tournament, $player_id ) {
		if ( ! isset( $tournament['players'] ) || ! is_array( $tournament['players'] ) ) {
			return null;
		}

		foreach ( $tournament['players'] as $row ) {
			if ( isset( $row['id'] ) && (string) $row['id'] === (string) $player_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Display name for a player id, falling back to the id itself.
	 *
	 * A gamertag is never the key upstream — players change name mid-tournament —
	 * so a decision only carries the id and the name is looked up here.
	 *
	 * @param array  $tournament Tournament array.
	 * @param string $player_id  Player id.
	 * @return string
	 */
	public static function name( $tournament, $player_id ) {
		$row = self::player( $tournament, $player_id );

		return $row && isset( $row['p'] ) ? (string) $row['p'] : (string) $player_id;
	}

	/**
	 * The band a player's rank sits in.
	 *
	 * @param array $tournament Tournament array.
	 * @param mixed $rank       Rank number, possibly null.
	 * @return array|null
	 */
	public static function band( $tournament, $rank ) {
		if ( null === $rank || '' === $rank ) {
			return null;
		}

		$key = (string) $rank;

		return isset( $tournament['bands'][ $key ] ) ? $tournament['bands'][ $key ] : null;
	}

	/**
	 * Decisions in one direction.
	 *
	 * @param array  $tournament Tournament array.
	 * @param string $direction  'promote' or 'hold'.
	 * @return array
	 */
	public static function decisions( $tournament, $direction ) {
		$out = array();

		if ( ! isset( $tournament['decisions'] ) || ! is_array( $tournament['decisions'] ) ) {
			return $out;
		}

		foreach ( $tournament['decisions'] as $d ) {
			if ( isset( $d['direction'] ) && $d['direction'] === $direction ) {
				$out[] = $d;
			}
		}

		return $out;
	}

	/**
	 * Ranks present, highest first.
	 *
	 * @param array $tournament Tournament array.
	 * @return array Array of int.
	 */
	public static function ranks( $tournament ) {
		if ( ! isset( $tournament['bands'] ) || ! is_array( $tournament['bands'] ) ) {
			return array();
		}

		$ranks = array_map( 'intval', array_keys( $tournament['bands'] ) );
		rsort( $ranks );

		return $ranks;
	}

	/**
	 * Players holding a given rank, alphabetical.
	 *
	 * @param array $tournament Tournament array.
	 * @param mixed $rank       Rank, or null for unranked players.
	 * @return array
	 */
	public static function players_at( $tournament, $rank ) {
		$out = array();

		if ( ! isset( $tournament['players'] ) || ! is_array( $tournament['players'] ) ) {
			return $out;
		}

		foreach ( $tournament['players'] as $row ) {
			$rk = isset( $row['rk'] ) ? $row['rk'] : null;
			if ( null === $rank ? ( null === $rk || '' === $rk ) : ( (int) $rk === (int) $rank ) ) {
				$out[] = $row;
			}
		}

		usort(
			$out,
			static function ( $a, $b ) {
				$an = isset( $a['p'] ) ? strtolower( (string) $a['p'] ) : '';
				$bn = isset( $b['p'] ) ? strtolower( (string) $b['p'] ) : '';

				return strcmp( $an, $bn );
			}
		);

		return $out;
	}
}
