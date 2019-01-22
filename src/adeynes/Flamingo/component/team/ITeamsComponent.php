<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\component\Component;
use adeynes\Flamingo\event\GamePreStartEvent;
use pocketmine\event\Listener;

interface ITeamsComponent extends Component, Listener
{

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