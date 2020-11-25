<?php

namespace Framework\Crontab;

use Framework\Base\Config;

class Component implements \Framework\Base\Component
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
