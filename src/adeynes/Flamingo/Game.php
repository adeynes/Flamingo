<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\component\Component;
use adeynes\Flamingo\component\team\ITeamsComponent;
use adeynes\Flamingo\component\team\MultiTeamsComponent;
use adeynes\Flamingo\component\team\SoloTeamsComponent;
use adeynes\Flamingo\component\team\Team;
use adeynes\Flamingo\component\TickableComponent;
use adeynes\Flamingo\event\BorderStartReductionEvent;
use adeynes\Flamingo\event\GamePreStartEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\event\GameWinEvent;
use adeynes\Flamingo\event\PlayerAdditionEvent;
use adeynes\Flamingo\map\Map;
use adeynes\Flamingo\utils\GameConfig;
use adeynes\Flamingo\utils\LangKeys;
use adeynes\Flamingo\utils\Utils;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\Player as PMPlayer;
use pocketmine\scheduler\ClosureTask;

final class Game implements Listener
{

    /** @var string */
    public const ERROR_GAME_IS_ALREADY_STARTED = 'Attempted to start a game that is already started';

    /** @var string */
    public const NOTICE_GAME_START_CANCELLED = 'Game starting has been cancelled';

    /** @var string */
    public const ERROR_ADD_PLAYER_TO_WRONG_GAME = 'Attempted to add a player to the wrong game ($game property does not match)';

    /** @var string */
    public const ERROR_REMOVE_NONEXISTENT_PLAYER = 'Attempted to remove a player from a game in which they are not';

    /** @var string */
    public const ERROR_ELIMINATE_NON_PLAYING_PLAYER = 'Attempted to eliminate a non-playing player';


    /** @var Flamingo */
    private $plugin;

    /** @var GameConfig */
    private $gameConfig;

    /** @var bool */
    private $isStarted = false;

    /**
     * The number of seconds since the start of the game (1 Flamingo tick = 20 PM ticks)
     *
     * @var ?int Null if the game hasn't started yet
     */
    private $curTick = null;

    /** @var Player[] */
    private $players = [];

    /** @var Player[] Players that have gone offline */
    private $offlinePlayers = [];

    /** @var Player[] */
    private $spectators = [];

    /** @var Map */
    private $map;

    /** @var ITeamsComponent */
    private $teamsComponent;

    /** @var Component[] */
    private $components = [];

    /** @var TickableComponent[] */
    private $tickableComponents = [];



    /**
     * @param Flamingo $plugin
     * @param GameConfig $gameConfig
     */
    public function __construct(Flamingo $plugin, GameConfig $gameConfig)
    {
        $this->plugin = $plugin;
        $this->gameConfig = $gameConfig;
        $this->map = new Map($this, $gameConfig->getLevel());

        if ($gameConfig->hasTeams()) {
            $this->teamsComponent = new MultiTeamsComponent($this, null);
        } else {
            $this->teamsComponent = new SoloTeamsComponent($this);
        }

        $this->getPlugin()->getServer()->getPluginManager()->registerEvents($this, $this->getPlugin());
    }

    /**
     * @return Flamingo
     */
    public function getPlugin(): Flamingo
    {
        return $this->plugin;
    }

    /**
     * @return GameConfig
     */
    public function getGameConfig(): GameConfig
    {
        return $this->gameConfig;
    }

    /**
     * @return bool
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * @return int
     */
    public function getCurTick(): ?int
    {
        return $this->curTick;
    }


    /**
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return $this->players;
    }

    /**
     * @param string $name
     * @return Player|null
     */
    public function getPlayer(string $name): ?Player
    {
        return $this->getPlayers()[$name] ?? null;
    }

    /**
     * @param Player $player
     * @return Player
     */
    public function addPlayer(Player $player): Player
    {
        if ($player->getGame() !== $this) {
            throw new \InvalidArgumentException(self::ERROR_ADD_PLAYER_TO_WRONG_GAME);
        }

        $event = new PlayerAdditionEvent($player, $this);
        $event->call();
        if (!$event->isCancelled()) {
            $this->players[$player->getName()] = $player;
        }

        if (!$player->getPmPlayer()->isOnline()) {
            $this->offlinePlayers[$player->getName()] = $player;
        }

        return $player;
    }

    public function removePlayer(Player $player): void
    {
        if (!$this->getPlayer($player->getName()) instanceof Player) {
            throw new \InvalidArgumentException(self::ERROR_REMOVE_NONEXISTENT_PLAYER);
        }

        unset($this->players[$player->getName()]);
    }

    /**
     * @param Player $player
     */
    public function addSpectator(Player $player): void
    {
        if ($player->isPlaying()) {
            $this->removePlayer($player);
        }
        $this->spectators[$player->getName()] = $player;

        $player->getPmPlayer()->setGamemode(PMPlayer::SPECTATOR);
    }


    /**
     * @return Map
     */
    public function getMap(): Map
    {
        return $this->map;
    }


    public function getTeamsComponent(): ITeamsComponent
    {
        return $this->teamsComponent;
    }

    /**
     * @param ITeamsComponent $teamsComponent
     */
    public function setTeamsComponent(ITeamsComponent $teamsComponent): void
    {
        $this->teamsComponent = $teamsComponent;
    }


    /**
     * @param Component $component
     */
    public function addComponent(Component $component): void
    {
        $this->components[spl_object_hash($component)] = $component;
    }

    /**
     * This also adds the component normally, no need to call addComponent() separately
     *
     * @param TickableComponent $component
     */
    public function addTickableComponent(TickableComponent $component): void
    {
        $this->tickableComponents[spl_object_hash($component)] = $component;
        $this->addComponent($component);
    }



    /**
     * Does all necessary pre-start things & starts the game
     */
    public function start(): void
    {
        if ($this->isStarted()) {
            throw new \InvalidStateException(self::ERROR_GAME_IS_ALREADY_STARTED);
        }

        $event = new GamePreStartEvent($this);
        $event->call();
        if ($event->isCancelled()) {
            $this->getPlugin()->getServer()->getLogger()->notice(self::NOTICE_GAME_START_CANCELLED);
            return;
        }

        // Start ticking
        $this->getPlugin()->getScheduler()->scheduleRepeatingTask(
            new ClosureTask(function (int $currentTick): void {
                $this->doTick();
            }),
            1*20
        );

        $this->isStarted = true;
        (new GameStartEvent($this))->call();
    }

    private function stop(): void
    {

    }



    /**
     * Ticks the game (ran every second)
     *
     * @internal
     */
    private function doTick(): void
    {
        // Increment tick count
        if ($this->getCurTick() === null) {
            $this->curTick = 0;
        } else {
            ++$this->curTick;
        }
        $curTick = $this->getCurTick();

        $this->getMap()->doTick($curTick);

        foreach ($this->tickableComponents as $tickableComponent) {
            $tickableComponent->doTick($curTick);
        }
    }






    /**
     * Called when a team has won the game
     *
     * @param Team $winnerTeam The team that has won
     */
    public function onWin(Team $winnerTeam): void
    {
        $this->plugin->getServer()->broadcastMessage(
            Utils::getInstance()->formatMessage(LangKeys::WIN_MESSAGE, ['team' => $winnerTeam->getName()]),
            array_map(
                function (Player $player): PMPlayer {
                    return $player->getPmPlayer();
                },
                $this->getPlayers()
            )
        );

        foreach ($winnerTeam->getPlayers() as $player) {
            if ($player->isPlaying()) {
                $this->addSpectator($player);
            }
        }

        (new GameWinEvent($winnerTeam, $this))->call();

        $this->stop();
    }



    /**
     * @param PlayerDeathEvent $event
     *
     * @priority LOWEST
     */
    public function onDeath(PlayerDeathEvent $event): void
    {
        $dead = $this->getPlayer($event->getPlayer()->getName());
        if ($dead instanceof Player) {
            $dead->eliminate();
        }
    }



    /**
     * @param PlayerQuitEvent $event
     *
     * @priority MONITOR
     */
    public function onQuit(PlayerQuitEvent $event): void
    {
        $player = $this->getPlayer($event->getPlayer()->getName());
        if (!$this->getPlayer($player->getName()) instanceof Player) {
            return;
        }

        $this->offlinePlayers[$player->getName()] = $player;

        if ($this->getMap()->getBorder()->isMoving()) {
            $player->eliminate();
        }
    }

    /**
     * @param PlayerJoinEvent $event
     *
     * @priority MONITOR
     */
    public function onJoin(PlayerJoinEvent $event): void
    {
        $name = $event->getPlayer()->getName();
        if (!isset($this->offlinePlayers[$name])) {
            return;
        }

        unset($this->offlinePlayers[$name]);
    }

    public function onReductionStart(BorderStartReductionEvent $event): void
    {
        foreach ($this->offlinePlayers as $player) {
            $player->eliminate();
        }
    }


    public function onLevelChange(EntityLevelChangeEvent $event): void
    {
        $entity = $event->getEntity();
        if (!$entity instanceof PMPlayer) {
            return;
        }

        $player = $this->getPlayer($entity->getName());
        if (!$player instanceof Player) {
            return;
        }

        $this->removePlayer($player);
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