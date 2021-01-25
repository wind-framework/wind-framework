<?php

namespace Wind\Base;

use Dotenv\Dotenv;

class Config {

    protected $configDir;
    protected $config = [];

    /**
     * @var Dotenv
     */
    protected $dotenv;

    public function __construct()
    {
        $this->configDir = BASE_DIR.'/config';

        //Initialize .env config
        $this->dotenv = Dotenv::createImmutable(BASE_DIR);
        $this->dotenv->load();

        //Load global config
        $globalConfig = $this->configDir.'/config.php';

        if (is_file($globalConfig)) {
            $config = require $globalConfig;
            if (is_array($config)) {
                $this->config = $config;
            }
        }
    }

    /**
     * Get config from key
     * 
     * @param string $key
     * @param mixed $defaultValue
     * @return mixed
     */
    public function get($key, $defaultValue=null)
    {
        $keys = explode('.', $key);
        $last = [];

        foreach ($keys as $i => $s) {
            if ($i == 0) {
                if (!isset($this->config[$s])) {
                    $last = $this->load($s);
                } else {
                    $last = $this->config[$s];
                }
            } elseif (isset($last[$s])) {
                $last = $last[$s];
            } else {
                return $defaultValue;
            }
        }

        return $last;
    }

    public function load($config)
    {
        $path = $this->configDir.'/'.$config.'.php';

        if (!is_file($path)) {
            throw new \Exception("No found config file {$config}.php in config path.");
        }

        return $this->config[$config] = require $path;
    }

}
