<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\Game;
use adeynes\Flamingo\utils\TeamConfig;

abstract class TeamsComponent implements ITeamsComponent
{

    /** @var Game */
    protected $game;

    /** @var TeamConfig|null */
    protected $teamConfig;

    /** @var Team[] */
    protected $teams;



    /**
     * @param Game $game
     * @param TeamConfig|null $teamConfig
     */
    public function __construct(Game $game, ?TeamConfig $teamConfig)
    {
        $this->game = $game;
        $this->teamConfig = $teamConfig;
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

}