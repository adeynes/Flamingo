<?php
declare(strict_types=1);
/**
 * This picks a random point outside of a square of a given radius (900) and finds the point of
 * intersection of the line going from the center of the square to the point and the square.
 * Points on the axes have also been manually tested.
 */

namespace adeynes\Flamingo\test;

require_once 'Vector2.php';

error_reporting(E_ALL);
set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (error_reporting() & $severity) {
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }
    return true;
});

const NORTH = 1;
const SOUTH = 2;
const EAST = 3;
const WEST = 4;

$radius = 900;
$side = rand(1, 4);

switch ($side) {
    case NORTH:
        $v = new Vector2(rand(-$radius, $radius), $radius + rand(5, 40));
        break;
    case SOUTH:
        $v = new Vector2(rand(-$radius, $radius), -$radius - rand(5, 40));
        break;
    case EAST:
        $v = new Vector2($radius + rand(5, 40), rand(-$radius, $radius));
        break;
    case WEST:
        $v = new Vector2(-$radius - rand(5, 40), rand(-$radius, $radius));
        break;
    // We should never get here
    default:
        $v = new Vector2(0, 0);
}

$x = $v->getX();
$y = $v->getY();

$distToVector = sqrt($x**2 + $y**2);
// Now we use Thales to calculate the distance to the border
// {     distToVector / distToBorder = |x| / radius
// { <=> distToBorder = (distToVector * radius) / |x|    if on east or west   (|x| >= |y|)
// {     distToVector / distToBorder = |y| / radius
// { <=> distToBorder = (distToVector * radius) / |y|    if on north or south (|y| >= |x|)
$distToBorder = ($distToVector * $radius) / abs(abs($x) > abs($y) ? $x : $y);

// Get the angle to the vector so we have its polar coordinate
// https://math.stackexchange.com/questions/1183357/when-do-you-add-180-to-the-directional-angle/3003263#3003263
try {
    $angle = 2 * atan($y / ($x + sqrt($x**2 + $y**2)));
} catch (\ErrorException $error) {
    // We are on the negative x axis
    $angle = pi();
}

// Now convert the polar coordinate to cartesian
// x = r * cos(a)
// y = r * sin(a)
$borderVector = new Vector2($distToBorder * cos($angle), $distToBorder * sin($angle));
var_dump($v);
var_dump($borderVector);