<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use adeynes\Flamingo\event\PlayerAdditionEvent;
use adeynes\Flamingo\Game;
use adeynes\Flamingo\utils\Tickable;
use adeynes\Flamingo\utils\Utils;
use pocketmine\block\Block;
use pocketmine\event\Listener;
use pocketmine\level\ChunkLoader;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;

final class Map implements Tickable, Listener
{

    /** @var Game */
    private $game;

    /** @var Level */
    private $level;

    /** @var Border */
    private $border;



    public function __construct(Game $game, Level $level)
    {
        $this->game = $game;
        $this->level = $level;
        $this->border = new BorderImpl($this->game);

        $pluginManager = $game->getPlugin()->getServer()->getPluginManager();
        $pluginManager->registerEvents($this, $game->getPlugin());
        $pluginManager->registerEvents($this->getBorder(), $game->getPlugin());
    }

    /**
     * @return Level
     */
    public function getLevel(): Level
    {
        return $this->level;
    }

    /**
     * @return Border
     */
    public function getBorder(): Border
    {
        return $this->border;
    }

    /**
     * @return SpawnGenerator
     *
     * @internal
     */
    public function getSpawnGenerator(): SpawnGenerator
    {
        return new SpawnGeneratorImpl($this);
    }


    /**
     * @param int $curTick
     *
     * @internal
     */
    public function doTick(int $curTick): void
    {
        $this->getBorder()->doTick($curTick);
    }






    /**
     * @param PlayerAdditionEvent $event
     *
     * @priority MONITOR
     */
    public function onPlayerAddition(PlayerAdditionEvent $event): void
    {
        $event->getPlayer()->teleport($this->getLevel()->getSafeSpawn());
    }

}