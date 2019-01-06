<?php
declare(strict_types=1);
/**
 * This computes 10,000 iterations of the number of teams that can be fit in an n*n map at least d blocks apart.
 * Each team is allotted b tries to find a suitable spot.
 * These numbers have been chosen as they are the default values in the plugin's configuration.
 */

namespace adeynes\Flamingo\test;

require_once 'Vector2.php';

if ($argc < 3) {
    echo 'Usage: php team_fitting_test.php <map_side> <min spawn distance>' . PHP_EOL;
    return;
}

$side = $argv[1];
$range = [-$side/2, $side/2];
$minDistance = $argv[2];
$spawns = [];

$respectsMinDistance = function (Vector2 $vector) use ($minDistance, &$spawns): bool {
	foreach ($spawns as $spawn) {
            if ($vector->distance($spawn) < $minDistance) {
                return false;
            }
    }
    return true;
};

$spawnsFitted = [];
for ($i = 0; $i < 10000; ++$i) {
	$cnt = 0;
	while (true) {
		for ($j = 0; $j < $minDistance; ++$j) {
			$vector = new Vector2(rand(...$range), rand(...$range));
			if ($respectsMinDistance($vector)) {
				$spawns[] = $vector;
				++$cnt;
				continue 2;
			}
		}
		break;
	}

	if (isset($spawnsFitted[$cnt])) {
		++$spawnsFitted[$cnt];
	} else {
		$spawnsFitted[$cnt] = 1;
	}

	$spawns = [];
}

ksort($spawnsFitted);
var_dump($spawnsFitted);