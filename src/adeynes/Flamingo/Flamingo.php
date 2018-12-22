<?php
declare(strict_types=1);

namespace adeynes\Flamingo;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;

final class Flamingo extends PluginBase
{

    /** @var string */
    private const CONFIG_VERSION = '0.1';

    /** @var string */
    private const DEFAULT_INCOMPATIBLE_CONFIG_VERSION_ERROR = 'Incompatible config.yml version! Try deleting Flamingo/resources/config.yml and reboot the server.';

    /** @var string */
    private const LANG_VERSION = '0.1';

    /** @var string */
    private const DEFAULT_INCOMPATIBLE_LANG_VERSION_ERROR = 'Incompatible lang.yml version! Try deleting Flamingo/resources/lang.yml and reboot the server.';

    /** @var string */
    private const LANG_FILE = 'lang.yml';

    /** @var string */
    private const MYSQL_FILE = 'mysql.sql';

    /** @var Flamingo */
    private static $instance;

    /** @var Config */
    private $lang;

    /** @var DataConnector */
    private $connector;

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
        $config_version = $this->getConfig()->get('version');
        if (!$this->areVersionsCompatible($config_version, self::CONFIG_VERSION)) {
            /** @var string $error */
            $error = $this->getLang()->get('error.incompatible-config-version', null) ?? self::DEFAULT_INCOMPATIBLE_CONFIG_VERSION_ERROR;
            $this->fail($error);
        }

        /** @var string $lang_version */
        $lang_version = $this->getLang()->get('version');
        if (!$this->areVersionsCompatible($lang_version, self::LANG_VERSION)) {
            /** @var string $error */
            $error = $this->getLang()->get('error.incompatible-lang-version', null) ?? self::DEFAULT_INCOMPATIBLE_LANG_VERSION_ERROR;
            $this->fail($error);
        }

        $this->connector = libasynql::create($this, $this->getConfig()->get('database'), ['mysql' => self::MYSQL_FILE]);
    }

    public function onDisable(): void
    {
        if ($this->connector) {
            $this->getConnector()->close();
        }
    }

    public function getLang(): Config
    {
        return $this->lang;
    }

    public function getConnector(): DataConnector
    {
        return $this->connector;
    }

    private function fail(string $reason): void
    {
        $this->getServer()->getLogger()->critical($reason);
        $this->getServer()->getPluginManager()->disablePlugin($this);
    }

    public function areVersionsCompatible(string $actual, string $minimum): bool
    {
        $actual = explode('.', $actual);
        $minimum = explode('.', $minimum);
        return $actual[0] === $minimum[0] && $actual[1] >= $minimum[1];
    }

}