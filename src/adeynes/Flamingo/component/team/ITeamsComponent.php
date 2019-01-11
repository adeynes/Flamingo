<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\component\Component;
use adeynes\Flamingo\event\GamePreStartEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\Game;
use adeynes\Flamingo\utils\TeamConfig;
use pocketmine\event\Listener;

interface ITeamsComponent extends Component, Listener
{

    /**
     * @param Game $game
     * @param TeamConfig|null $teamConfig
     */
    public function __construct(Game $game, ?TeamConfig $teamConfig);

    /**
     * @return Team[]
     */
    public function getTeams(): array;

    /**
     * @param string $name
     * @return Team
     */
    public function getTeam(string $name): ?Team;


    /**
     * Checks if a team has won. null if nobody has won, the Team object that has won otherwise
     *
     * @return Team|null
     */
    public function checkWinCondition(): ?Team;


    /**
     * All teams components have to do something on game pre start (ex. spawn the teams)
     *
     * @param GamePreStartEvent $event
     */
    public function onGamePreStart(GamePreStartEvent $event): void;

}