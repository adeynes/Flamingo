<?php
declare(strict_types=1);

namespace adeynes\Flamingo\event;

use adeynes\Flamingo\Game;
use adeynes\Flamingo\Player;
use pocketmine\event\Event;

class PlayerEliminationEvent extends Event
{

    /** @var Player */
    private $player;

    /** @var Game
     */
    private $game;

    public function __construct(Player $player, Game $game)
    {
        $this->player = $player;
        $this->game = $game;
    }

    /**
     * @return Player
     */
    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return $this->game;
    }

}