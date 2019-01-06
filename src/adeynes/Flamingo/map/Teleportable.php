<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use pocketmine\level\Position;

interface Teleportable
{

    public function teleport(Position $position): void;

}