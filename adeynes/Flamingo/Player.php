<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

class Player
{

    /** @var string */
    private $name;

    /** @var Team */
    private $team;

    public function __construct(string $name, Team $team)
    {
        $this->name = $name;
        $this->team = $team;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTeam(): Team {
        return $this->team;
    }

}