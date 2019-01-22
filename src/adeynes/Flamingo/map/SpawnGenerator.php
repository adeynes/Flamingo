<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

interface SpawnGenerator
{

    /**
     * @param int $needSpawnNum
     * @param float $minDistance
     * @param \Closure $onFinish
     */
    public function generateSpawns(int $needSpawnNum, float $minDistance, \Closure $onFinish): void;

}