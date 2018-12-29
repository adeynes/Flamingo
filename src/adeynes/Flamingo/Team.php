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

    /**
     * @return string
     */
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

    /**
     * Are there still players in this team who are still playing (alive)?
     *
     * @return bool
     */
    public function isPlaying(): bool
    {
        foreach ($this->getPlayers() as $player) {
            if ($player->isPlaying()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Have all the players in this team been eliminated (died)?
     *
     * @return bool
     */
    public function isEliminated(): bool
    {
        return !$this->isPlaying();
    }

}