<?php
declare(strict_types=1);

namespace adeynes\Flamingo\struct;

class TeamOrganization
{

    /** @var int */
    private $teamSize;

    /** @var int */
    private $numTeams;

    /**
     * The number of teams that have a numerical superiority (have more players than a "regular" team)
     *
     * @var int
     */
    private $numTeamsWithNumericalSup;

    public function __construct(int $teamSize, int $numTeams, int $numTeamsWithNumericalSup)
    {
        $this->teamSize = $teamSize;
        $this->numTeams = $numTeams;
        $this->numTeamsWithNumericalSup = $numTeamsWithNumericalSup;
    }

    /**
     * Generates a TeamOrganization instance based on a given size and number of players
     *
     * @param int $size
     * @param int $numPlayers
     * @return TeamOrganization
     */
    public static function calculate(int $size, int $numPlayers): TeamOrganization
    {
        return new TeamOrganization($size, intdiv($numPlayers, $size), $numPlayers % $size);
    }

    /**
     * @return int
     */
    public function getTeamSize(): int
    {
        return $this->teamSize;
    }

    /**
     * @return int
     */
    public function getNumTeams(): int
    {
        return $this->numTeams;
    }

    /**
     * @return int
     */
    public function getNumTeamsWithNumericalSup(): int
    {
        return $this->numTeamsWithNumericalSup;
    }

}