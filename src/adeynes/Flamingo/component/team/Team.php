<?php
declare(strict_types=1);

namespace adeynes\Flamingo\component\team;

use adeynes\Flamingo\map\Teleportable;
use adeynes\Flamingo\Player;
use pocketmine\entity\Effect;
use pocketmine\entity\EffectInstance;
use pocketmine\level\Position;
use pocketmine\math\Vector3;

class Team implements Teleportable
{

    protected const SIZE_OVERFLOW = 'Passed too many players Team::addPlayers(), team is full';

    /**
     * How far away from the given vector players will spawn
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
     * @param Player ...$players
     */
    public function addPlayers(Player ...$players): void
    {
        if ($this->getMaxSize() > 0 && count($this->getPlayers()) + count($players) > $this->getMaxSize()) {
            throw new \InvalidArgumentCountException(
                self::SIZE_OVERFLOW . PHP_EOL .
                'cur. size: ' . count($this->getPlayers()) . PHP_EOL .
                'max size: ' . $this->getMaxSize() . PHP_EOL .
                'players passed: ' . var_export($players)
            );
        }

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
            $randPos = Position::fromObject($position->add($randX, 0, $randZ));
            $y = $position->getLevel()->getHighestBlockAt($randPos->getFloorX(), $randPos->getFloorY());
            $randPos->setComponents($randPos->getX(), $y, $randPos->getZ());

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