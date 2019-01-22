<?php
declare(strict_types=1);

namespace adeynes\Flamingo\event;

use adeynes\Flamingo\component\team\Team;
use adeynes\Flamingo\Game;
use pocketmine\event\Event;

class GameWinEvent extends Event
{

    /** @var Team */
    private $winnerTeam;

    /** @var Game */
    private $game;



    /**
     * @param Team $winnerTeam
     * @param Game $game
     */
    public function __construct(Team $winnerTeam, Game $game)
    {
        $this->winnerTeam = $winnerTeam;
        $this->game = $game;
    }

    /**
     * @return Team
     */
    public function getWinnerTeam(): Team
    {
        return $this->winnerTeam;
    }

    /**
     * @return Game
     */
    public function getGame(): Game
    {
        return $this->game;
    }

}