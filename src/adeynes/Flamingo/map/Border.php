<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use adeynes\Flamingo\utils\Tickable;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\math\Vector2;

interface Border extends Tickable, Listener
{

    /**
     * @return float
     */
    public function getRadius(): float;

    /**
     * @param Vector2 $vector
     * @return bool
     */
    public function isWithinLimits(Vector2 $vector): bool;

    /**
     * Listens to PlayerMoveEvent for border collision/penetration
     *
     * @param PlayerMoveEvent $event
     */
    public function onMove(PlayerMoveEvent $event): void;

}