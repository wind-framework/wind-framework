<?php

namespace Wind\Base;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\RepositoryBuilder;

class Config {

    protected $configDir;
    protected $config = [];

    /**
     * @var \Dotenv\Repository\RepositoryInterface
     */
    private $repository;

    public function __construct()
    {
        $this->configDir = BASE_DIR.'/config';

        //Initialize .env config
        $this->repository = RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(EnvConstAdapter::class)
            ->immutable()
            ->make();

        $dotenv = Dotenv::create($this->repository, BASE_DIR);
        $dotenv->safeLoad();

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

    /**
     * Check is config file exists
     *
     * @param string $config
     * @return bool
     */
    public function exists($config)
    {
        return is_file($this->configDir.'/'.$config.'.php');
    }

    /**
     * Read the value of environment variable
     *
     * @param string $key
     * @return mixed
     */
    public function env($key)
    {
        $value = $this->repository->get($key);

        if (is_string($value)) {
            return match(strtolower($value)) {
                'true', '(true)' => true,
                'false', '(false)' => false,
                'null', '(null)' => null,
                default => $value
            };
        } else {
            return $value;
        }
    }

}
