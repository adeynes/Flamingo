<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use adeynes\Flamingo\utils\ConfigKeys;
use adeynes\Flamingo\utils\GameConfig;
use adeynes\Flamingo\utils\LangKeys;
use adeynes\Flamingo\utils\Utils;
use pocketmine\level\Level;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

final class Flamingo extends PluginBase
{

    /** @var string */
    private const ERROR_INCOMPATIBLE_CONFIG_VERSION = 'Incompatible config.yml version! Try deleting Flamingo/resources/config.yml and reboot the server.';

    /** @var string */
    private const ERROR_INCOMPATIBLE_LANG_VERSION = 'Incompatible lang.yml version! Try deleting Flamingo/resources/lang.yml and reboot the server.';

    /** @var string */
    private const ERROR_GAME_IS_ALREADY_CREATED = 'Attempted to create game even though one already exists';

    /** @var string */
    private const CONFIG_VERSION = '0.5';

    /** @var string */
    private const LANG_VERSION = '0.5';

    /** @var string */
    private const LANG_FILE = 'lang.yml';



    /** @var Flamingo */
    private static $instance;

    /** @var Config */
    private $lang;

    /** @var Game|null */
    private $game = null;



    /**
     * @return Flamingo
     */
    public static function getInstance(): Flamingo
    {
        return self::$instance;
    }

    public function onLoad(): void
    {
        self::$instance = $this;
    }

    public function onEnable(): void
    {
        if (!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }
        $this->saveDefaultConfig();
        $this->saveResource(self::LANG_FILE);
        $this->lang = new Config($this->getDataFolder() . self::LANG_FILE);

        /** @var string $config_version */
        $config_version = $this->getConfig()->get(ConfigKeys::VERSION);
        if (!Utils::areVersionsCompatible($config_version, self::CONFIG_VERSION)) {
            /** @var string $error */
            $error = self::ERROR_INCOMPATIBLE_CONFIG_VERSION . PHP_EOL .
                'current: ' . $config_version . PHP_EOL .
                'required: ' . self::CONFIG_VERSION;
            $this->fail($error);
        }

        /** @var string $lang_version */
        $lang_version = $this->getLang()->get(LangKeys::VERSION);
        if (!Utils::areVersionsCompatible($lang_version, self::LANG_VERSION)) {
            /** @var string $error */
            $error = self::ERROR_INCOMPATIBLE_LANG_VERSION . PHP_EOL .
                'current: ' . $lang_version . PHP_EOL .
                'required: ' . self::LANG_VERSION;
            $this->fail($error);
        }

        Utils::new($this);
    }



    /**
     * @return Config
     */
    public function getLang(): Config
    {
        return $this->lang;
    }



    /**
     * @param GameConfig $gameConfig
     * @return Game
     *
     * @throws \InvalidStateException If there is already a game created
     */
    public function newGame(GameConfig $gameConfig): Game
    {
        if ($this->game instanceof Game) {
            throw new \InvalidStateException(self::ERROR_GAME_IS_ALREADY_CREATED);
        }

        if ($gameConfig->getLevel() === null) {
            do {
                $name = (string)rand();
            } while (!$this->getServer()->generateLevel($name));

            $gameConfig->setLevel($this->getServer()->getLevelByName($name));
        }

        return $this->game = new Game($this, $gameConfig);
    }

    /**
     * @return Game|null
     */
    public function getGame(): ?Game
    {
        return $this->game;
    }






    /**
     * Call when a non-recoverable error occurs
     *
     * This logs the specified message at critical level to the logger and shuts down the plugin.
     *
     * @param string $reason
     *
     * @see \Logger
     *
     * @internal
     */
    private function fail(string $reason): void
    {
        $this->getServer()->getLogger()->critical($reason);
        $this->getServer()->getPluginManager()->disablePlugin($this);
    }

}