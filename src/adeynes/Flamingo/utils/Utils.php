<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

use adeynes\Flamingo\Flamingo;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;
use pocketmine\utils\TextFormat;

/**
 * Utility class
 *
 * Static methods at the beginning of the class are independent from the Flamingo instance.
 * Non-static ones are dependent on the plugin instance (ex. for config stuff).
 * This class is singleton for easy access.
 */
final class Utils
{

    ////////////    MISC    ////////////

    /**
     * Checks if a version (major.minor) is compatible with a minimum version (major.minor)
     *
     * They are compatible if: (1) major matches; (2) given version's minor is >= minimum version's minor.
     *
     * @param string $actual
     * @param string $minimum
     * @return bool
     */
    public static function areVersionsCompatible(string $actual, string $minimum): bool
    {
        $actual = explode('.', $actual);
        $minimum = explode('.', $minimum);
        return $actual[0] === $minimum[0] && $actual[1] >= $minimum[1];
    }



    ////////////    ARRAY    ////////////

    /**
     * Initializes an array with a given closure that generates values. Useful for init with objects
     *
     * @param \Closure $closure
     * @param int $size
     * @param array $keys
     * @return array
     */
    public static function initArrayWithClosure(\Closure $closure, int $size, array $keys = []): array
    {
        $array = [];
        for ($i = 0; $i < $size; ++$i) {
            $array[$keys[$i] ?? $i] = $closure($i);
        }
        return $array;
    }

    /**
     * Get a specified number of random elements from an array and removes them from a given array if specified
     *
     * @param array $array
     * @param int $num
     * @param array|null $removeFrom Passed by reference
     * @return array
     */
    public static function getRandomElems(array $array, int $num, array &$removeFrom = null): array
    {
        if ($num === 1) {
            return [self::getRandomElem($array, $removeFrom)];
        }

        $rand_keys = array_flip(array_rand($array, $num));
        if ($removeFrom !== null) {
            $removeFrom = array_diff_key($removeFrom, $rand_keys);
        }
        return array_intersect_key($array, $rand_keys);
    }

    /**
     * Gets a random element from an array and removes it from a given array if specified
     *
     * @param array $array
     * @param array|null $removeFrom
     * @return mixed
     */
    public static function getRandomElem(array $array, array &$removeFrom = null)
    {
        $key = array_rand($array);
        if ($removeFrom !== null) {
            unset($removeFrom[$key]);
        }
        return $array[$key];
    }



    ////////////    STRING    ////////////

    /**
     * Replaces all tags (enclosed between 2 %, ex. %tag%) with the corresponding value in a given template
     *
     * Passing ['tag' => 'my cool tag'] to 'this is %tag%' will return 'this is my cool tag'.
     *
     * @param string $template
     * @param array $data [tag name => replacement]
     * @return string
     */
    public static function replaceTags(string $template, array $data): string
    {
        $tags = [];

        // Find everything between two %
        preg_match_all('/%(.*?)%/', $template,$tags);
        // Make sure we only have unique values; str_replace replaces everything anyways
        $tags = array_unique($tags, SORT_REGULAR);
        // No tags found
        if ($tags === [[]]) {
            return $template;
        }

        // Given %tag%, $tags[1] will be "tag" while $tags[0] will be "%tag%"
        foreach ($tags[1] as $i => $tag) {
            $template = str_replace($tags[0][$i], $data[$tag], $template);
        }
        return $template;
    }



    ////////////    MATH    ////////////

    /**
     * Converts a Vector3 to a Vector2 by using the Vector3's x & z as the Vector2's x & y
     *
     * @param Vector3 $vector3
     * @return Vector2
     */
    public static function vec3ToVec2(Vector3 $vector3): Vector2
    {
        return new Vector2($vector3->getX(), $vector3->getZ());
    }

    /**
     * Converts a Vector2 to a Vector3 by using the Vector2's x & y as the Vector3's x & z and a specified value as y
     *
     * @param Vector2 $vector2
     * @param float $y
     * @return Vector3
     */
    public static function vec2ToVec3(Vector2 $vector2, float $y): Vector3
    {
        return new Vector3($vector2->getX(), $y, $vector2->getY());
    }

    /**
     * Calculates the point where a vector intersects a square (if extended until the square's edge)
     *
     * Given a two-dimensional vector and the radius of a square (said square's center is the origin (0, 0)),
     * this draws a vector from the origin extending to (at least) the edge of the square and calculates
     * the point at which they intersect. This is used to calculate the direction from which a player crossed a border.
     *
     * @param Vector2 $vector
     * @param float $radius The square's radius (half-side)
     * @return Vector2
     *
     * @see https://math.stackexchange.com/questions/1183357/when-do-you-add-180-to-the-directional-angle/3003263#3003263
     */
    public static function calculateVectorSquareIntersection(Vector2 $vector, float $radius): Vector2
    {
        // We want to calculate the distance to the border, so we have the polar coordinate of the point
        // (the angle is the same as that to the given vector)
        // We can then convert polar -> cartesian

        $x = $vector->getX();
        $y = $vector->getY();

        $distToVector = sqrt($x**2 + $y**2);
        // Now we use Thales to calculate the distance to the border:
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
            // We are on the negative x axis (division by zero)
            $angle = pi();
        }

        // Now convert the polar coordinate to cartesian
        // { x = r * cos(a)
        // { y = r * sin(a)
        return new Vector2($distToBorder * cos($angle), $distToBorder * sin($angle));
    }



    ////////////    POCKETMINE    ////////////

    /**
     * Returns a resistance 4 EffectInstance (invincible)
     *
     * @param float|int $duration Default is 600 ticks (30 seconds)
     * @return EffectInstance
     */
    public static function getInvincibilityResistance($duration = 30*20): EffectInstance
    {
        return new EffectInstance(Effect::getEffect(Effect::RESISTANCE), $duration, 4);
    }






    /** @var Utils */
    private static $instance;

    /** @var Flamingo */
    private $plugin;

    /**
     * This is a singleton, so private constructor
     *
     * @param Flamingo $plugin
     */
    private function __construct(Flamingo $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Create the Utils instance
     *
     * @param Flamingo $plugin
     * @return Utils
     */
    public static function new(Flamingo $plugin): Utils
    {
        return self::$instance = new Utils($plugin);
    }

    /**
     * @return Utils
     */
    public static function getInstance(): Utils
    {
        return self::$instance;
    }

    /**
     * Gets the desired message from the lang file and formats it (tag replacement & colorization)
     *
     * @param string $path
     * @param string[] $data
     * @return string
     */
    public function formatMessage(string $path, array $data = []): ?string
    {
        /** @var String $message */
        $message = $this->plugin->getLang()->getNested($path);
        return $message !== null ? TextFormat::colorize(self::replaceTags($message, $data)): null;
    }

}