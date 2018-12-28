<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

use adeynes\Flamingo\Flamingo;

final class Utils
{

    public static function areVersionsCompatible(string $actual, string $minimum): bool
    {
        $actual = explode('.', $actual);
        $minimum = explode('.', $minimum);
        return $actual[0] === $minimum[0] && $actual[1] >= $minimum[1];
    }

    public static function initArrayWithClosure(\Closure $closure, int $size, array $keys = []): array
    {
        $array = [];
        for ($i = 0; $i < $size; ++$i) {
            $array[$keys[$i] ?? $i] = $closure($i);
        }
        return $array;
    }

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






    /** @var Flamingo */
    private $plugin;

    public function __construct(Flamingo $plugin)
    {
        $this->plugin = $plugin;
    }



}