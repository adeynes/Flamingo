<?php
declare(strict_types=1);

namespace adeynes\Flamingo\map;

use adeynes\Flamingo\utils\Utils;
use pocketmine\block\Block;
use pocketmine\level\ChunkLoader;
use pocketmine\level\format\Chunk;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector2;
use pocketmine\math\Vector3;

class SpawnGeneratorImpl implements SpawnGenerator, ChunkLoader
{

    /**
     * The factor by which the minimum spawn distance will be multiplied if a team cannot be fitted
     *
     * @var float
     */
    private const SPAWN_DISTANCE_DEPRECATION_FACTOR = 0.825;



    /** @var int */
    private $loaderId = 0;

    /** @var Map */
    private $map;

    /** @var Vector2[][] [chunk index => [vector2]] */
    private $spawnsQueue = [];

    /** @var Position[] */
    private $generatedSpawns = [];

    /** @var \Closure */
    private $onFinish;



    public function __construct(Map $map)
    {
        $this->loaderId = Level::generateChunkLoaderId($this);
        $this->map = $map;
    }



    /**
     * @param int $needSpawnNum
     * @param float $minDistance
     * @param \Closure $onFinish
     *
     * @internal
     */
    public function generateSpawns(int $needSpawnNum, float $minDistance, \Closure $onFinish): void
    {
        $this->onFinish = $onFinish;

        $radius = $this->map->getBorder()->getRadius();
        $limits = [(int)-$radius, (int)$radius];

        /** @var Vector2[] $spawns */
        $spawns = [];

        $respectsMinDistance = function (Vector2 $vector) use ($minDistance, $spawns): bool {
            foreach ($spawns as $spawn) {
                if ($vector->distance($spawn) < $minDistance) {
                    return false;
                }
            }
            return true;
        };

        // TODO: async
        for ($i = 0; $i < $needSpawnNum; ++$i) {
            while (true) {
                for ($j = 0; $j < 20; ++$j) {
                    $x = rand(...$limits);
                    $z = rand(...$limits);
                    $spawn = new Vector2($x, $z);
                    if (!$respectsMinDistance($spawn)) {
                        continue;
                    }

                    $level = $this->map->getLevel();
                    $spawns[] = $spawn;

                    $chunk = $level->getChunkAtPosition(new Vector3($x, 0, $z), true);
                    $level->populateChunk($chunk->getX(), $chunk->getZ());

                    $chunkHash = Level::chunkHash($chunk->getX(), $chunk->getZ());
                    if (!isset($this->spawnsQueue[$chunkHash])) {
                        $this->spawnsQueue[$chunkHash] = [];
                        $this->map->getLevel()->registerChunkLoader($this, $chunk->getX(), $chunk->getZ());
                    }
                    $this->spawnsQueue[$chunkHash][] = $spawn;

                    continue 3; // go to the next team, don't go to the minDistance deprecation
                }
                // We haven't been able to fit the team in 20 tries, deprecate the min distance
                $minDistance *= self::SPAWN_DISTANCE_DEPRECATION_FACTOR;
            }
        }
    }



    /**
     * Called when a registered Chunk is populated. Usually sent with another call to onChunkChanged()
     *
     * @param Chunk $chunk
     */
    public function onChunkPopulated(Chunk $chunk): void
    {
        $chunkHash = Level::chunkHash($chunk->getX(), $chunk->getZ());
        if (!isset($this->spawnsQueue[$chunkHash])) {
            return;
        }

        foreach ($this->spawnsQueue[$chunkHash] as $spawn) {
            // Bitwise AND with 0x0f to only keep the last 4 bites (coords within the Chunk)
            $y = $chunk->getHighestBlockAt($spawn->getX() & 0x0f, $spawn->getY() & 0x0f);
            $this->generatedSpawns[] = new Position($spawn->getX(), $y, $spawn->getY(), $this->map->getLevel());
        }

        unset($this->spawnsQueue[$chunkHash]);

        var_dump($this->spawnsQueue);

        if (empty($this->spawnsQueue)) {
            ($this->onFinish)($this->generatedSpawns);
        }
    }



    /**
     * Level::generateChunkLoaderId() to generate the id
     *
     * @return int
     */
    public function getLoaderId(): int
    {
        return $this->loaderId;
    }

    /**
     * @return bool
     */
    public function isLoaderActive(): bool
    {
        return true;
    }

    /**
     * This is useless except for chunk ticking which we don't care about
     *
     * @return Position
     */
    public function getPosition(): Position
    {
        return new Position;
    }

    /**
     * This is useless except for chunk ticking which we don't care about
     *
     * @return float
     */
    public function getX(): float
    {
        return $this->getPosition()->getX();
    }

    /**
     * This is useless except for chunk ticking which we don't care about
     *
     * @return float
     */
    public function getZ(): float
    {
        return $this->getPosition()->getZ();
    }

    /**
     * This is useless except for chunk ticking which we don't care about
     *
     * @return Level
     */
    public function getLevel(): Level
    {
        return $this->getPosition()->getLevel();
    }

    /**
     * Called when a Chunk is replaced by a new one
     *
     * @param Chunk $chunk
     */
    public function onChunkChanged(Chunk $chunk): void
    {

    }

    /**
     * Called when a registered Chunk is loaded
     *
     * @param Chunk $chunk
     */
    public function onChunkLoaded(Chunk $chunk): void
    {

    }

    /**
     * Called when a registered Chunk is unloaded
     *
     * @param Chunk $chunk
     */
    public function onChunkUnloaded(Chunk $chunk): void
    {

    }

    /**
     * Called when a block changes in a registered chunk
     *
     * @param Vector3 $block
     */
    public function onBlockChanged(Vector3 $block): void
    {

    }

}