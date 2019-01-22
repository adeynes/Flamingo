<?php
declare(strict_types=1);

namespace adeynes\Flamingo\event;

use adeynes\Flamingo\Game;
use adeynes\Flamingo\map\Border;
use pocketmine\event\Event;

class BorderStartReductionEvent extends Event
{

    /** @var Game */
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

    public function getBorder(): Border
    {
        return $this->getGame()->getMap()->getBorder();
    }

}