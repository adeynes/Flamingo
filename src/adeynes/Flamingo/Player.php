<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\component\team\Team;
use adeynes\Flamingo\map\Teleportable;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player as PMPlayer;

class Player implements Teleportable
{

    /**
     * The player is still playing (ie. alive)
     *
     * @var int
     */
    public const PLAYING = 1;

    /**
     * The player has been eliminated (ie. died)
     *
     * @var int
     */
    public const ELIMINATED = 0;

    /** @var string */
    private $name;

    /** @var int */
    private $status = self::PLAYING;

    /** @var Team */
    private $team;

    /** @var PMPlayer */
    private $pmPlayer;

    /**
     * There is separation between this Player instance and the PocketMine Player instance.
     * For instance, the names do not have to match.
     *
     * @param string $name
     * @param Team $team
     * @param PMPlayer $pmPlayer
     */
    public function __construct(string $name, Team $team, PMPlayer $pmPlayer)
    {
        $this->name = $name;
        $this->team = $team;
        $this->pmPlayer = $pmPlayer;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return Team
     */
    public function getTeam(): Team
    {
        return $this->team;
    }

    /**
     * Gets the player's status (PLAYING or ELIMINATED)
     *
     * @return int
     */
    public function getStatus(): int
    {
        return $this->status;
    }

    /**
     * Is the player still playing (alive)?
     *
     * @return bool
     */
    public function isPlaying(): bool
    {
        return $this->getStatus() === self::PLAYING;
    }

    /**
     * Has the player been eliminated (killed)?
     *
     * @return bool
     */
    public function isEliminated(): bool
    {
        return $this->getStatus() === self::ELIMINATED;
    }

    /**
     * Sets the player's status to either PLAYING or ELIMINATED
     *
     * @param int $status
     */
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

    /**
     * Eliminates the player
     *
     * This sets their status to ELIMINATED and changes their gamemode to spectator
     */
    public function eliminate(): void
    {
        $this->setStatus(self::ELIMINATED);
        $this->pmPlayer->setGamemode(PMPlayer::SPECTATOR);
    }



    /**
     * @param Position $position
     */
    public function teleport(Position $position): void
    {
        $this->getPmPlayer()->teleport($position);
    }

}