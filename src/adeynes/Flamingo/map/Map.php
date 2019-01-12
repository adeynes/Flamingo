<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use adeynes\Flamingo\event\PlayerAdditionEvent;
use adeynes\Flamingo\Game;
use adeynes\Flamingo\utils\Tickable;
use pocketmine\event\Listener;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector2;

final class Map implements Tickable, Listener
{

    /**
     * The factor by which the minimum spawn distance will be multiplied if a team cannot be fitted
     *
     * @var float
     */
    private const SPAWN_DISTANCE_DEPRECATION_FACTOR = 0.825;



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



    public function doTick(int $curTick): void
    {
        $this->getBorder()->doTick($curTick);
    }


    /**
     * @param int $needSpawnNum
     * @param float $minDistance
     * @return Position[]
     */
    public function generateSpawns(int $needSpawnNum, float $minDistance): array
    {
        $radius = $this->getBorder()->getRadius();
        $limits = [(int)-$radius, (int)$radius];

        /** @var Position[] $spawns */
        $spawns = [];

        $respectsMinDistance = function (Position $pos) use ($minDistance, $spawns): bool {
            foreach ($spawns as $spawn) {
                if ($pos->distance($spawn) < $minDistance) {
                    return false;
                }
            }
            return true;
        };

        var_dump($limits);
        var_dump($needSpawnNum);
        var_dump($minDistance);
        // TODO: async
        for ($i = 0; $i < $needSpawnNum; ++$i) {
            for ($j = 0; $j < 20; ++$j) {
                $x = rand(...$limits);
                $z = rand(...$limits);
                $spawn = new Position($x, $this->getLevel()->getHighestBlockAt($x, $z), $z, $this->getLevel());
                if ($respectsMinDistance($spawn)) {
                    $spawns[] = $spawn;
                    continue 2; // go to the next team, don't skip to the minDistance deprecation
                }
            }
            // We haven't been able to fit the team in minDistance tries, deprecate it
            $minDistance *= self::SPAWN_DISTANCE_DEPRECATION_FACTOR;
        }

        return $spawns;
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