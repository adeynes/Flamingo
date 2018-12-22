<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

class Team
{

    /** @var int */
    public const TEAM_TYPE_REGULAR = 2;

    /** @var int */
    public const TEAM_TYPE_FLAMINGO = 3;

    /** @var int */
    private $type;

    /** @var Player[] */
    private $players;

    /**
     * @param int $type The type of team (TEAM_TYPE_REGULAR or TEAM_TYPE_FLAMINGO)
     * @param Player[] $players
     */
    public function __construct(int $type, array $players = [])
    {
        $this->type = $type;
        $this->players = $players;
    }

    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    public function addPlayers(Player ...$players): void
    {
        foreach ($players as $player) {
            $this->players[$player->getName()] = $player;
        }
    }

    public function removePlayer(Player $player): void
    {
        unset($this->players[$player->getName()]);
    }

}