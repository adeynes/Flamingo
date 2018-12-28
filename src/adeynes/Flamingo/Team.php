<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

class Team
{

    /** @var string */
    private $name;

    /** @var Player[] */
    private $players;

    /**
     * @param string $name
     * @param Player[] $players
     */
    public function __construct(string $name, array $players = [])
    {
        $this->name = $name;
        $this->players = $players;
    }

    public function getName(): string
    {
        return $this->name;
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