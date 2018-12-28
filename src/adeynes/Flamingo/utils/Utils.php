<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

use adeynes\Flamingo\Flamingo;
use pocketmine\utils\TextFormat;

final class Utils
{

    /**
     * Checks if a version (major.minor) is compatible with a minimum version (major.minor)
     *
     * They are compatible if: (1) major matches; (2) given version's minor is >= minimum version's minor
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


    /**
     * Replaces all tags (enclosed between 2 %, ex. %tag%) with the corresponding value in a given template
     *
     * Passing ['tag' => 'my cool tag'] to 'this is %tag%' will return 'this is my cool tag'
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






    /** @var Flamingo */
    private $plugin;

    public function __construct(Flamingo $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Gets the desired message from the lang file and formats it (tag replacement & colorization)
     *
     * @param string $path
     * @param string[] $data
     * @return string
     */
    public function formatMessage(string $path, array $data): ?string
    {
        /** @var String $message */
        $message = $this->plugin->getLang()->getNested($path);
        return $message !== null ? TextFormat::colorize(self::replaceTags($message, $data)): null;
    }

}