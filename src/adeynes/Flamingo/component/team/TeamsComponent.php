<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\Game;

abstract class TeamsComponent implements ITeamsComponent
{

    /** @var Game */
    protected $game;

    /** @var Team[] */
    protected $teams;

    /** @var Team[] Teams that have not yet been eliminated */
    protected $playingTeams;



    /**
     * @param Game $game
     */
    public function __construct(Game $game)
    {
        $this->game = $game;
        $game->getPlugin()->getServer()->getPluginManager()->registerEvents($this, $game->getPlugin());
    }

    /**
     * @return Team[]
     */
    public function getTeams(): array
    {
        return $this->teams;
    }

    /**
     * @param string $name
     * @return Team|null
     */
    public function getTeam(string $name): ?Team
    {
        return $this->getTeams()[$name] ?? null;
    }

    /**
     * @return Team[]
     */
    public function getPlayingTeams(): array
    {
        return $this->playingTeams;
    }



    /**
     * Checks if a team has won. If so, execute the necessary code (ie. trigger Game::onWin()
     *
     * @return Team|null
     */
    public function checkWinCondition(): ?Team
    {
        if (count($this->getPlayingTeams()) !== 1) {
            return null;
        }

        /** @var Team $winnerTeam */
        $winnerTeam = array_values($this->getPlayingTeams())[0];
        $this->game->onWin($winnerTeam);

        return $winnerTeam;
    }

}