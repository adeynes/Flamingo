<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\map\Teleportable;
use adeynes\Flamingo\Player;
use pocketmine\level\Position;

class Team implements Teleportable
{

    /** @var string */
    protected const ERROR_SIZE_OVERFLOW = 'Team is full';

    /**
     * How far away from the given position players will spawn
     *
     * @var int
     *
     * @see Team::teleport()
     */
    protected const PLAYER_SPAWN_RADIUS = 15;


    /** @var string */
    protected $name;

    /** @var int 0 if the size is unlimited/undefined */
    protected $maxSize;

    /** @var Player[] */
    protected $players;



    /**
     * @param string $name
     * @param int $maxSize 0 for unlimited/undefined
     * @param Player[] $players
     */
    public function __construct(string $name, int $maxSize, array $players = [])
    {
        $this->name = $name;
        $this->maxSize = $maxSize;
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
     * @return int
     */
    public function getMaxSize(): int
    {
        return $this->maxSize;
    }

    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @param string|null $name Can be null if the team size is 1, will return the sole player
     * @return Player|null
     */
    public function getPlayer(string $name = null): ?Player
    {
        if ($this->getMaxSize() === 1) {
            return array_values($this->getPlayers())[0];
        }
        return $this->getPlayers()[$name] ?? null;
    }

    /**
     * @param Player $player
     */
    public function addPlayer(Player $player): void
    {
        if ($this->getMaxSize() > 0 && count($this->getPlayers()) >= $this->getMaxSize()) {
            throw new \InvalidArgumentCountException(
                self::ERROR_SIZE_OVERFLOW . PHP_EOL .
                'team name: ' . $this->getName() . PHP_EOL .
                'max size: ' . $this->getMaxSize() . PHP_EOL .
                'player passed: ' . $player->getName()
            );
        }

        $this->players[$player->getName()] = $player;
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
        // TODO: cache?
        return !$this->isPlaying();
    }



    /**
     * Players are spawned randomly 15 blocks away from the spawn and 30 blocks from the ground
     *
     * Players will be given a resistance effect for 30 seconds to negate all damage taken from the fall.
     * The Y value of the Position will be disregarded.
     *
     * @param Position $position
     */
    public function teleport(Position $position): void
    {
        $this->doToAllPlayers(function (Player $player) use ($position): void {
            $randX = rand(-15, 15);
            // We calculated x first, so we used Pythagorean to get z at a 15 block radius
            $randZ = sqrt(15 ** 2 - $randX ** 2);
            // Randomize randZ sign (else it would always be positive)
            if (rand(0, 1)) {
                $randZ *= -1;
            }

            // TODO: they may go in unloaded or ungenerated chunks
            $randPos = $position->getLevel()->getSafeSpawn(Position::fromObject($position->add($randX, 1, $randZ)));
            $player->teleport($randPos);
        });
    }






    /**
     * Passes each player to a specified closure
     *
     * @param \Closure $closure
     */
    public function doToAllPlayers(\Closure $closure): void
    {
        foreach ($this->getPlayers() as $player) {
            $closure($player);
        }
    }

}