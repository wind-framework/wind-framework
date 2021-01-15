<?php

namespace Wind\Crontab;

use Wind\Base\Config;

class Component implements \Wind\Base\Component
{

    public static function provide($app)
    {
        $config = $app->container->get(Config::class);
        $tabs = $config->get('crontab', []);

        if (!empty($tabs)) {
            return;
        }
    }

    public static function start($worker)
    {}

}
