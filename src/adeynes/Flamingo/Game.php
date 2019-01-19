<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\component\Component;
use adeynes\Flamingo\component\team\ITeamsComponent;
use adeynes\Flamingo\component\team\MultiTeamsComponent;
use adeynes\Flamingo\component\team\SoloTeamsComponent;
use adeynes\Flamingo\component\team\Team;
use adeynes\Flamingo\component\team\TeamsComponent;
use adeynes\Flamingo\component\TickableComponent;
use adeynes\Flamingo\event\GamePreStartEvent;
use adeynes\Flamingo\event\GameStartEvent;
use adeynes\Flamingo\event\PlayerAdditionEvent;
use adeynes\Flamingo\event\PlayerEliminationEvent;
use adeynes\Flamingo\map\Map;
use adeynes\Flamingo\utils\GameConfig;
use adeynes\Flamingo\utils\LangKeys;
use adeynes\Flamingo\utils\Utils;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\level\Level;
use pocketmine\Player as PMPlayer;
use pocketmine\scheduler\ClosureTask;

final class Game implements Listener
{

    /** @var string */
    public const ERROR_GAME_IS_ALREADY_STARTED = 'Attempted to start a game that is already started';

    /** @var string */
    public const NOTICE_GAME_START_CANCELLED = 'Game starting has been cancelled';

    /** @var string */
    public const ERROR_ADD_NONEXISTENT_PLAYER = 'Attempted to add a nonexistent player';

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
     * @param string $name
     * @return Player
     */
    public function addPlayer(string $name): Player
    {
        $pmPlayer = $this->getPlugin()->getServer()->getPlayer($name);
        if (!$pmPlayer instanceof PMPlayer || !$pmPlayer->isOnline()) {
            throw new \InvalidArgumentException(self::ERROR_ADD_NONEXISTENT_PLAYER);
        }

        $player = new Player($pmPlayer, $this);
        $event = new PlayerAdditionEvent($player, $this);
        $event->call();
        if (!$event->isCancelled()) {
            $this->players[$player->getName()] = $player;
        }

        return $player;
    }

    /**
     * @param Player $player
     */
    public function addSpectator(Player $player): void
    {
        $name = $player->getName();
        if ($player->isPlaying()) {
            unset($this->players[$name]);
        }
        $this->spectators[$name] = $player;
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
        var_dump($event->getPlayer()->getName());
        var_dump($this->getPlayers());
        if ($dead instanceof Player) {
            var_dump($dead->getName());
            $dead->eliminate();
        }
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