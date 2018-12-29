<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\utils\LangKeys;
use adeynes\Flamingo\utils\Utils;
use pocketmine\Player as PMPlayer;

class Player
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

    /** @var Team */
    private $team;

    /** @var bool */
    private $isFlamingo = false;

    /** @var int */
    private $status = self::PLAYING;

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
     * @return bool
     */
    public function isFlamingo(): bool
    {
        return $this->isFlamingo;
    }

    /**
     * Sets the flamingo flag (where the player is a flamingo)
     *
     * @param bool $v
     */
    public function setFlamingo(bool $v = true): void
    {
        $this->isFlamingo = $v;
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
     * Gets & formats the player's nametag as per the lang file's template
     *
     * @return string
     */
    public function getNameTag(): string
    {
        return Utils::getInstance()->formatMessage(
            LangKeys::PLAYER_NAMETAG,
            ['player' => $this->getName(), 'team' => $this->getTeam()->getName()]
        );
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

}