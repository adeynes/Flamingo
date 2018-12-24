<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

final class Utils
{

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
        $rand_keys = array_flip(array_rand($array, $num));
        if ($removeFrom !== null) {
            $removeFrom = array_diff_key($removeFrom, $rand_keys);
        }
        return array_intersect_key($array, $rand_keys);
    }

}