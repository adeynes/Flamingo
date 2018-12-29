<?php
declare(strict_types=1);

namespace adeynes\Flamingo\utils;

interface ConfigKeys
{

    /** @var string */
    public const VERSION = 'version';

    /** @var string */
    public const DATABASE = 'database';

    /** @var string */
    public const TEAM_SIZE_OPTIMALITY = 'team-size-optimality';

    /** @var string */
    public const FLAMINGO_REVELATION_DELAY = 'flamingo.revelation-delay';

    /** @var string */
    public const FLAMINGO_PROPORTION = 'flamingo.proportion';

    /** @var string */
    public const MAP_SIDE = 'map.side';

    /** @var string */
    public const MINIMUM_SPAWN_DISTANCE = 'map.minimum-spawn-distance';

}