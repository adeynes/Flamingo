<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

interface Tickable
{

    public function doTick(int $curTick): void;

}