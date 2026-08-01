<?php
/**
 * Tests for the privacy guard.
 *
 * Deliberately plain PHP with no PHPUnit and no WordPress. The guard is the one
 * piece that must keep working, so the thing that checks it should run anywhere
 * with `php tests/test-privacy.php` and no install step.
 *
 * The strongest test here is the last one: it takes the admin fixture — which
 * carries every private field the upstream build can emit — serialises the
 * sanitised output back to JSON, and greps the whole string. That catches a
 * private value surviving in a nested structure nobody thought to assert on.
 *
 * @package WZCustoms
 */

define( 'ABSPATH', __DIR__ );

require_once __DIR__ . '/../includes/class-wzc-privacy.php';

$failures = 0;
$checks   = 0;

/**
 * Assert a condition.
 *
 * @param bool   $condition Condition.
 * @param string $label     What is being asserted.
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
 * Load a fixture.
 *
 * @param string $name File name.
 * @return array
 */
function fixture( $name ) {
	return json_decode( file_get_contents( __DIR__ . '/fixtures/' . $name ), true );
}

echo "\nPublic build passes through intact\n";

$privacy = new WZC_Privacy();
$out     = $privacy->sanitize( fixture( 'public.json' ) );
$t       = $out['tournaments'][0];

check( 1 === count( $out['tournaments'] ), 'one tournament' );
check( 2 === count( $t['players'] ), 'both players kept' );
check( 1 === count( $t['decisions'] ), 'the public promotion is kept' );
check( 'idrxp' === $t['decisions'][0]['player_id'], 'decision keeps its player_id' );
check( 13.0 === $t['players'][0]['med'], 'median map survives' );
check( 45.3 === $t['players'][0]['ks'], 'kill share survives' );
check( isset( $t['bands']['8']['promote_at'] ), 'band thresholds survive' );
check( 1 === count( $t['teams'] ), 'team row survives' );
check( '8-' === $t['meta']['bracket'], 'meta survives' );
check( ! $privacy->leaked(), 'nothing reported as withheld' );
check( array() === $privacy->unknown(), 'no unrecognised fields' );

echo "\nAdmin build is stripped\n";

$privacy = new WZC_Privacy();
$out     = $privacy->sanitize( fixture( 'admin.json' ) );
$t       = $out['tournaments'][0];
$player  = $t['players'][0];

check( ! array_key_exists( 'res', $player ), 'band residual removed' );
check( ! array_key_exists( 'sig', $player ), 'movement signal removed' );
check( ! array_key_exists( 'z', $player ), 'z-score removed' );
check( ! array_key_exists( 'dpm', $player ), 'damage per map removed' );
check( ! array_key_exists( 'extn', $player ), 'extraction count removed' );
check( ! array_key_exists( 'discord_id', $player ), 'discord id removed' );
check( 1.6 === $player['med'], 'public figures still present' );

check( array() === $t['decisions'], 'every decision withheld' );
check( ! array_key_exists( 'carry_forward', $out ), 'carry-forward block dropped' );
check( $privacy->leaked(), 'the guard reports that it fired' );

$report = implode( ' | ', $privacy->report() );
check( false !== strpos( $report, 'carry_forward' ), 'report names the carry-forward block' );
check( false !== strpos( $report, 'res' ), 'report names the residual' );
check( false !== strpos( $report, 'demotion marked public' ), 'report names the mismarked demotion' );
check( false !== strpos( $report, 'non-public decision' ), 'report names the private decision' );

echo "\nA demotion marked public is still withheld\n";

$privacy = new WZC_Privacy();
$out     = $privacy->sanitize(
	array(
		'tournaments' => array(
			array(
				'date'      => '2026-07-19',
				'decisions' => array(
					array(
						'player_id'  => 'someone',
						'direction'  => 'demote',
						'visibility' => 'public',
						'reason'     => 'Wrong column ticked.',
					),
				),
			),
		),
	)
);

check( array() === $out['tournaments'][0]['decisions'], 'visibility=public does not override direction=demote' );

echo "\nUnrecognised fields are reported separately from leaks\n";

$privacy = new WZC_Privacy();
$out     = $privacy->sanitize(
	array(
		'tournaments' => array(
			array(
				'date'    => '2026-07-19',
				'players' => array(
					array(
						'p'          => 'Someone',
						'id'         => 'someone',
						'new_metric' => 42,
					),
				),
			),
		),
	)
);

check( ! array_key_exists( 'new_metric', $out['tournaments'][0]['players'][0] ), 'unknown field not rendered' );
check( ! $privacy->leaked(), 'an unknown field is not reported as a leak' );
check( in_array( 'player.new_metric', $privacy->unknown(), true ), 'unknown field is listed' );

echo "\nMalformed input degrades to empty\n";

foreach ( array( null, 'a string', 42, array(), array( 'tournaments' => 'nope' ) ) as $i => $bad ) {
	$privacy = new WZC_Privacy();
	$out     = $privacy->sanitize( $bad );
	check(
		isset( $out['tournaments'] ) && array() === $out['tournaments'],
		'malformed input ' . $i . ' yields no tournaments'
	);
}

echo "\nNo private value survives anywhere in the serialised output\n";

$privacy = new WZC_Privacy();
$out     = $privacy->sanitize( fixture( 'admin.json' ) );
$json    = wp_json_encode_shim( $out );

// Values, not just keys — a private number reappearing under a different name
// would pass every key-based assertion above.
$forbidden = array(
	'-3.82'                => 'the residual value',
	'123456789012345678'   => 'the discord id',
	'consistent-down'      => 'the demotion rationale code',
	'DEMOTE'               => 'the carry-forward flag',
	'Mismarked'            => 'the mismarked demotion reason',
	'Discussed but not'    => 'the internal hold reason',
	'weigh before acting'  => 'the carry-forward note',
	'1840'                 => 'damage per map',
);

foreach ( $forbidden as $needle => $label ) {
	check( false === strpos( $json, (string) $needle ), 'output contains no trace of ' . $label );
}

/**
 * json_encode without depending on WordPress.
 *
 * @param mixed $data Data.
 * @return string
 */
function wp_json_encode_shim( $data ) {
	return json_encode( $data );
}

echo "\n" . ( $failures ? "FAILED: $failures of $checks\n\n" : "All $checks checks passed\n\n" );

exit( $failures ? 1 : 0 );
