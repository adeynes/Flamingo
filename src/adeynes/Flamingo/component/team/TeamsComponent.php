<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\event\PlayerEliminationEvent;
use adeynes\Flamingo\Game;
use adeynes\Flamingo\utils\TeamConfig;

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



    public function checkWinCondition(): ?Team
    {
        return count($this->getPlayingTeams()) === 1 ? array_values($this->getPlayingTeams())[0] : null;
    }

}