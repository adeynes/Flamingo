<?php
declare(strict_types=1);

namespace adeynes\Flamingo\event;

use adeynes\Flamingo\Game;
use pocketmine\event\Event;

/**
 * Triggered when flamingos are revealed
 */
class FlamingoRevelationEvent extends Event
{

    /** @var Game[] */
    private $game;

    /**
     * @param Game $game
     */
    public function __construct(Game $game)
    {
        $this->game = $game;
    }

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return $this->game;
    }

}