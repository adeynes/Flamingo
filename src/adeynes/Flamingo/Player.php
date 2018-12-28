<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use pocketmine\Player as PMPlayer;

class Player
{

    /** @var int */
    public const PLAYING = 1;

    /** @var int */
    public const ELIMINATED = 0;

    /** @var string */
    private $name;

    /** @var Team */
    private $team;

    /** @var bool */
    private $isFlamingo = false;

    /** @var int */
    private $status = self::PLAYING;

    /** @var PMPlayer */
    private $pmPlayer;

    /**
     * @param string $name
     * @param Team $team
     * @param PMPlayer $pmPlayer
     * @throws \InvalidArgumentException If the team is not regular
     */
    public function __construct(string $name, Team $team, PMPlayer $pmPlayer)
    {
        $this->name = $name;
        $this->team = $team;
        $this->pmPlayer = $pmPlayer;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTeam(): Team
    {
        return $this->team;
    }

    public function isFlamingo(): bool
    {
        return $this->isFlamingo;
    }

    public function setFlamingo(bool $v = true): void
    {
        $this->isFlamingo = $v;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isPlaying(): bool
    {
        return $this->getStatus() === self::PLAYING;
    }

    public function isEliminated(): bool
    {
        return $this->getStatus() === self::ELIMINATED;
    }

    public function setStatus(int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return PMPlayer
     */
    public function getPmPlayer(): PMPlayer
    {
        return $this->pmPlayer;
    }

    public function eliminate(): void
    {
        $this->setStatus(self::ELIMINATED);
        $this->pmPlayer->setGamemode(PMPlayer::SPECTATOR);
    }

}