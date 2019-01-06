<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

final class GameConfig
{

    /** @var bool */
    private $hasTeams = false;

    public function __construct()
    {
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