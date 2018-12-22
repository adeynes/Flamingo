<?php
declare(strict_types=1);

namespace adeynes\Flamingo\struct;

class TeamOrganization
{

    /** @var int */
    private $teamSize;

    /** @var int */
    private $numTeams;

    /** @var int */
    private $numTeamsWithNumericalSup;

    public function __construct(int $teamSize, int $numTeams, int $numTeamsWithNumericalSup)
    {
        $this->teamSize = $teamSize;
        $this->numTeams = $numTeams;
        $this->numTeamsWithNumericalSup = $numTeamsWithNumericalSup;
    }

    public static function calculate(int $size, int $numPlayers): TeamOrganization
    {
        return new TeamOrganization($size, intdiv($numPlayers, $size), $numPlayers % $size);
    }

    public function getTeamSize(): int
    {
        return $this->teamSize;
    }

    public function getNumTeams(): int
    {
        return $this->numTeams;
    }

    public function getNumTeamsWithNumericalSup(): int
    {
        return $this->numTeamsWithNumericalSup;
    }

}