<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

use pocketmine\level\Level;

final class GameConfig
{

    /** @var Level|null null is a Level should be created for the game */
    private $level = null;

    /** @var bool */
    private $hasTeams = false;



    public function __construct()
    {

    }

    /**
     * @return null|Level
     */
    public function getLevel(): ?Level
    {
        return $this->level;
    }

    /**
     * @param null|Level $level
     */
    public function setLevel(?Level $level): void
    {
        $this->level = $level;
    }



    /**
     * @return bool
     */
    public function hasTeams(): bool
    {
        return $this->hasTeams;
    }

    /**
     * @param bool $hasTeams
     * @return $this
     */
    public function setHasTeams(bool $hasTeams = true): GameConfig
    {
        $this->hasTeams = $hasTeams;
        return $this;
    }

}