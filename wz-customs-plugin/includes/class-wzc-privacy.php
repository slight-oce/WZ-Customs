<?php
/**
 * The privacy guard.
 *
 * The upstream repo publishes two builds from the same CSVs: `docs/data.json`
 * (public) and `admin/data.json` (everything, including demotions and the band
 * residual that orders people worst-to-best). The admin build is gitignored and
 * is not supposed to leave the organiser's machine — but the only thing standing
 * between the two is which flag `build.py` was run with, and this plugin is
 * pointed at a URL that a human types into a settings field.
 *
 * So the plugin does not trust its input. Everything is filtered through an
 * allowlist on ingest, before it is cached and long before it is rendered. A
 * private field cannot leak from a store it was never written to.
 *
 * Allowlist rather than denylist, deliberately: if the upstream build ever grows
 * a new field, the failure mode is that this plugin does not render it yet, not
 * that it publishes it. The dropped keys are reported on the settings screen so
 * "not rendered yet" stays visible instead of silent.
 *
 * This class deliberately uses no WordPress functions, so the rules can be
 * tested standalone — see tests/test-privacy.php.
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

/**
 * Filters a decoded data.json payload down to what may be published.
 */
class WZC_Privacy {

	/**
	 * Fields a player row may carry into the front end.
	 *
	 * `res`, `sig`, `z`, `dpm` and `extn` are absent by design. `res` is the gap
	 * to the band average — the field that sorts a rank band from worst to best.
	 * Publishing it turns a rank review into a shame list even with no demotion
	 * label attached anywhere near it.
	 */
	const PLAYER_FIELDS = array(
		'p',
		'id',
		'rk',
		'team',
		'n',
		'imp',
		'k',
		'kpm',
		'plc',
		'pts',
		'ppm',
		'med',
		'ks',
		'sqrank',
		'sqrank_vs_field',
		'standout',
	);

	/** Fields a decision may carry. */
	const DECISION_FIELDS = array(
		'player_id',
		'direction',
		'from_rank',
		'to_rank',
		'visibility',
		'reason',
		'numbers_said',
	);

	/** Fields a tournament meta block may carry. */
	const META_FIELDS = array(
		'played_on',
		'format',
		'map_count',
		'bracket',
		'rank_cap',
		'movement_floor',
		'match_point',
		'notes',
		'rank_sum_mean',
		'rank_sum_lo',
		'rank_sum_hi',
		'rank_sum_median',
	);

	/** Fields a band may carry. */
	const BAND_FIELDS = array(
		'mean',
		'n',
		'sd',
		'lo',
		'hi',
		'promote_at',
		'standout_at',
		'demote_at',
		'share_gate',
		'small',
		'below_floor',
		'why',
	);

	/** Fields a team row may carry. */
	const TEAM_FIELDS = array( 't', 'pts', 'k', 'plc' );

	/**
	 * Fields that identify an admin build. Their presence is reported as a leak
	 * caught, not as an unrecognised field.
	 */
	const PRIVATE_FIELDS = array( 'res', 'sig', 'z', 'dpm', 'extn', 'discord_id' );

	/**
	 * What the last sanitise pass removed. Surfaced on the settings screen.
	 *
	 * @var array
	 */
	protected $report = array();

	/**
	 * Keys seen in the payload that are not on any allowlist.
	 *
	 * Not a privacy problem — an upstream field this version does not know how
	 * to render. Reported separately so the two never get confused.
	 *
	 * @var array
	 */
	protected $unknown = array();

	/**
	 * Filter a decoded payload.
	 *
	 * @param mixed $raw Decoded JSON, expected to be an array.
	 * @return array Publishable payload, shaped { tournaments: [...] }.
	 */
	public function sanitize( $raw ) {
		$this->report  = array();
		$this->unknown = array();

		if ( ! is_array( $raw ) ) {
			return array( 'tournaments' => array() );
		}

		// The admin build carries a carry_forward block: every player's residual
		// history plus their PROMOTE/DEMOTE flag. There is no public form of it.
		if ( isset( $raw['carry_forward'] ) ) {
			$this->flag( 'carry_forward block (admin build detected)' );
		}

		$tournaments = array();
		$source      = isset( $raw['tournaments'] ) && is_array( $raw['tournaments'] )
			? $raw['tournaments']
			: array();

		foreach ( $source as $tournament ) {
			if ( is_array( $tournament ) ) {
				$tournaments[] = $this->tournament( $tournament );
			}
		}

		return array( 'tournaments' => $tournaments );
	}

	/**
	 * Whether the last pass found anything it had to remove.
	 *
	 * @return bool
	 */
	public function leaked() {
		return ! empty( $this->report );
	}

	/**
	 * Human-readable list of what was removed.
	 *
	 * @return array
	 */
	public function report() {
		return array_values( array_unique( $this->report ) );
	}

	/**
	 * Allowlist misses from the last pass — upstream fields this version does
	 * not render. Informational, not a leak.
	 *
	 * @return array
	 */
	public function unknown() {
		return array_values( array_unique( $this->unknown ) );
	}

	/**
	 * Record a removal.
	 *
	 * @param string $what Description of the removed material.
	 */
	protected function flag( $what ) {
		$this->report[] = $what;
	}

	/**
	 * Filter one tournament.
	 *
	 * @param array $t Raw tournament.
	 * @return array
	 */
	protected function tournament( $t ) {
		$out = array(
			'date'      => isset( $t['date'] ) ? (string) $t['date'] : '',
			'meta'      => $this->pick( isset( $t['meta'] ) ? $t['meta'] : array(), self::META_FIELDS, 'meta' ),
			'players'   => array(),
			'bands'     => array(),
			'teams'     => array(),
			'decisions' => array(),
		);

		if ( isset( $t['players'] ) && is_array( $t['players'] ) ) {
			foreach ( $t['players'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				foreach ( self::PRIVATE_FIELDS as $private ) {
					if ( array_key_exists( $private, $row ) ) {
						$this->flag( sprintf( 'player field "%s"', $private ) );
					}
				}
				$out['players'][] = $this->pick( $row, self::PLAYER_FIELDS, 'player' );
			}
		}

		if ( isset( $t['bands'] ) && is_array( $t['bands'] ) ) {
			foreach ( $t['bands'] as $rank => $band ) {
				if ( is_array( $band ) ) {
					$out['bands'][ (string) $rank ] = $this->pick( $band, self::BAND_FIELDS, 'band' );
				}
			}
		}

		if ( isset( $t['teams'] ) && is_array( $t['teams'] ) ) {
			foreach ( $t['teams'] as $row ) {
				if ( is_array( $row ) ) {
					$out['teams'][] = $this->pick( $row, self::TEAM_FIELDS, 'team' );
				}
			}
		}

		if ( isset( $t['decisions'] ) && is_array( $t['decisions'] ) ) {
			foreach ( $t['decisions'] as $d ) {
				if ( ! is_array( $d ) ) {
					continue;
				}

				$visibility = isset( $d['visibility'] ) ? (string) $d['visibility'] : '';
				$direction  = isset( $d['direction'] ) ? (string) $d['direction'] : '';

				// Two independent tests, both of which must pass. A demotion is
				// withheld even if someone marks it public by hand, because that
				// is far more likely to be a mistake in a spreadsheet column than
				// a considered decision to publish that somebody was moved down.
				if ( 'public' !== $visibility ) {
					$this->flag( 'non-public decision' );
					continue;
				}
				if ( 'demote' === $direction ) {
					$this->flag( 'demotion marked public' );
					continue;
				}

				$out['decisions'][] = $this->pick( $d, self::DECISION_FIELDS, 'decision' );
			}
		}

		return $out;
	}

	/**
	 * Keep only allowlisted keys.
	 *
	 * @param mixed  $row    Raw associative array.
	 * @param array  $fields Allowed keys.
	 * @param string $scope  Label used when reporting unrecognised keys.
	 * @return array
	 */
	protected function pick( $row, $fields, $scope = '' ) {
		if ( ! is_array( $row ) ) {
			return array();
		}

		$out = array();
		foreach ( $fields as $f ) {
			if ( array_key_exists( $f, $row ) ) {
				$out[ $f ] = $row[ $f ];
			}
		}

		if ( '' !== $scope ) {
			foreach ( array_keys( $row ) as $key ) {
				// Keys that were already reported as private belong in the leak
				// report, not in the "we don't render this yet" list.
				if ( ! in_array( $key, $fields, true ) && ! in_array( $key, self::PRIVATE_FIELDS, true ) ) {
					$this->unknown[] = $scope . '.' . $key;
				}
			}
		}

		return $out;
	}
}
