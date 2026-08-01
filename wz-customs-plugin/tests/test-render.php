<?php
/**
 * Render tests.
 *
 * Two jobs. First, prove that every render path executes — a fatal in a rarely
 * hit branch (an unranked player, a band with no spread) would otherwise only
 * show up on a live site. Second, and more important, take the admin fixture
 * through the full stack — sanitise, then render — and grep the finished HTML.
 * That is the end-to-end version of the privacy guarantee: not "the array was
 * filtered" but "the bytes a visitor receives contain none of it".
 *
 * @package WZCustoms
 */

require_once __DIR__ . '/stubs.php';
require_once __DIR__ . '/../includes/class-wzc-privacy.php';
require_once __DIR__ . '/../includes/class-wzc-source.php';
require_once __DIR__ . '/../includes/class-wzc-data.php';
require_once __DIR__ . '/../includes/class-wzc-render.php';

$failures = 0;
$checks   = 0;

/**
 * Assert a condition.
 *
 * @param bool   $condition Condition.
 * @param string $label     Description.
 */
function check( $condition, $label ) {
	global $failures, $checks;
	$checks++;

	if ( $condition ) {
		echo "  ok    $label\n";
	} else {
		echo "  FAIL  $label\n";
		$failures++;
	}
}

/**
 * Sanitise a fixture and install it as the cached payload.
 *
 * @param string $name Fixture file name.
 * @return array
 */
function install( $name ) {
	$raw     = json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name ), true );
	$privacy = new WZC_Privacy();

	$GLOBALS['wzc_test_payload'] = $privacy->sanitize( $raw );

	return $GLOBALS['wzc_test_payload'];
}

/**
 * Render every public block for a tournament.
 *
 * @param array $t Tournament.
 * @return array Block name => HTML.
 */
function render_all( $t ) {
	$blocks = array(
		'changes' => WZC_Render::rank_changes( $t ),
		'bands'   => WZC_Render::bands( $t ),
		'players' => WZC_Render::players( $t, 'https://example.test/players/' ),
		'teams'   => WZC_Render::teams( $t, 0 ),
		'rules'   => WZC_Render::rules( $t ),
	);

	foreach ( $t['players'] as $row ) {
		$blocks[ 'player:' . $row['id'] ] = WZC_Render::player( $t, $row['id'], 'https://example.test/players/' );
	}

	return $blocks;
}

echo "\nPublic fixture renders\n";

install( 'public.json' );
$t      = WZC_Data::tournament();
$blocks = render_all( $t );

check( null !== $t, 'latest tournament resolves' );
check( '2026-07-19' === $t['date'], 'it is the expected event' );

foreach ( $blocks as $name => $html ) {
	check( is_string( $html ) && '' !== $html, "block '$name' produced output" );
}

check( false !== strpos( $blocks['changes'], 'iDrxp' ), 'the promoted player is named' );
check( false !== strpos( $blocks['changes'], 'Moved up' ), 'the promotion is labelled' );
check( false !== strpos( $blocks['players'], 'Dzire' ), 'the player list includes everyone' );
check( false !== strpos( $blocks['players'], 'player=dzire' ), 'names link to the player page' );
check( false !== strpos( $blocks['bands'], 'below the movement floor' ), 'a capped band explains itself' );
check( false !== strpos( $blocks['teams'], 'Zag x Gooey x iDrxp' ), 'squad standings render' );

// A recorded decision outranks anything inferred from the numbers. iDrxp is a
// standout AND has a published promotion; the page must show the promotion,
// because that is the decision a person actually made.
check(
	false !== strpos( $blocks['player:idrxp'], 'Promoted to rank 9' ),
	'a recorded decision takes precedence over the inferred verdict'
);
check(
	false === strpos( $blocks['player:dzire'], 'Promoted' ),
	'a player with no decision is not shown as promoted'
);
check(
	false !== strpos( $blocks['player:dzire'], 'No change this tournament' ),
	'and gets the no-change verdict instead'
);

echo "\nMarkup is balanced and escaped\n";

foreach ( $blocks as $name => $html ) {
	check(
		substr_count( $html, '<div' ) === substr_count( $html, '</div>' ),
		"block '$name' closes every div"
	);
}

$hostile = array(
	'tournaments' => array(
		array(
			'date'      => '2026-07-19',
			'meta'      => array( 'map_count' => '6' ),
			'players'   => array(
				array(
					'p'   => '<script>alert(1)</script>',
					'id'  => 'xss',
					'rk'  => null,
					'n'   => 6,
					'med' => 1.0,
				),
				array(
					'p'   => 'Nobody',
					'id'  => 'nobody',
					'rk'  => null,
					'n'   => 6,
					'med' => 1.0,
				),
			),
			'bands'     => array(),
			'teams'     => array(),
			'decisions' => array(
				array(
					'player_id'  => 'xss',
					'direction'  => 'promote',
					'from_rank'  => '8',
					'to_rank'    => '9',
					'visibility' => 'public',
					'reason'     => 'Quote " and <b>bold</b> and & ampersand.',
				),
			),
		),
	),
);

$privacy                     = new WZC_Privacy();
$GLOBALS['wzc_test_payload'] = $privacy->sanitize( $hostile );
$t                           = WZC_Data::tournament();
$blocks                      = render_all( $t );

foreach ( $blocks as $name => $html ) {
	check( false === strpos( $html, '<script>' ), "block '$name' escapes a script tag in a name" );
	check( false === strpos( $html, '<b>bold</b>' ), "block '$name' escapes markup in a reason" );
}

echo "\nAn unranked player renders without a band\n";

check( false !== strpos( $blocks['player:nobody'], 'Unranked' ), 'unranked players are labelled' );
check( false !== strpos( $blocks['player:nobody'], 'No rank on record' ), 'and given the no-band verdict' );

echo "\nA standout with no recorded decision is reported from the flag\n";

$GLOBALS['wzc_test_payload'] = ( new WZC_Privacy() )->sanitize(
	array(
		'tournaments' => array(
			array(
				'date'    => '2026-07-19',
				'meta'    => array( 'map_count' => '6' ),
				'players' => array(
					array(
						'p'        => 'Flyer',
						'id'       => 'flyer',
						'rk'       => 8,
						'n'        => 6,
						'med'      => 13.0,
						'ks'       => 45.3,
						'standout' => true,
					),
				),
				'bands'   => array(
					'8' => array(
						'mean'       => 5.42,
						'n'          => 22,
						'sd'         => 3.61,
						'lo'         => 1.43,
						'hi'         => 14.1,
						'promote_at' => 9.93,
						'small'      => false,
					),
				),
			),
		),
	)
);

$standout = WZC_Render::player( WZC_Data::tournament(), 'flyer' );
check( false !== strpos( $standout, 'Standout event' ), 'the standout flag drives the verdict' );

echo "\nAdmin fixture leaks nothing into the finished HTML\n";

install( 'admin.json' );
$t      = WZC_Data::tournament();
$blocks = render_all( $t );
$all    = implode( "\n", $blocks );

$forbidden = array(
	'-3.82'               => 'the residual value',
	'123456789012345678'  => 'the discord id',
	'consistent-down'     => 'the demotion rationale code',
	'DEMOTE'              => 'the carry-forward flag',
	'Mismarked'           => 'the mismarked demotion reason',
	'Discussed but not'   => 'the internal hold reason',
	'weigh before acting' => 'the carry-forward note',
	'1840'                => 'damage per map',
	'demote'              => 'the word demote',
);

foreach ( $forbidden as $needle => $label ) {
	check( false === strpos( $all, (string) $needle ), 'rendered HTML contains no trace of ' . $label );
}

check( false !== strpos( $blocks['changes'], 'Nobody moved up' ), 'with every decision withheld, the page says so plainly' );
check( false !== strpos( $blocks['player:dzire'], 'Dzire' ), 'the player page still renders their public figures' );

echo "\n" . ( $failures ? "FAILED: $failures of $checks\n\n" : "All $checks checks passed\n\n" );

exit( $failures ? 1 : 0 );
