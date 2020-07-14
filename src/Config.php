<?php

declare(strict_types=1);

namespace winwin\winner;

class Config
{
    private const CONFIG_JSON = '.config/winner/config.json';

    private static $INSTANCE;

    /**
     * @var string
     */
    private $endpoint;

    /**
     * @var string
     */
    private $tarsFileRegistryServantName;

    /**
     * @var string
     */
    private $token;

    public function __construct(string $endpoint = '', string $token = '')
    {
        $this->endpoint = $endpoint;
        $this->token = $token;
        $this->tarsFileRegistryServantName = '';
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function setEndpoint(string $endpoint): void
    {
        $this->endpoint = $endpoint;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): void
    {
        $this->token = $token;
    }

    public function getTarsFileRegistryServantName(): string
    {
        return $this->tarsFileRegistryServantName;
    }

    public function setTarsFileRegistryServantName(string $tarsFileRegistryServantName): void
    {
        $this->tarsFileRegistryServantName = $tarsFileRegistryServantName;
    }

    public static function getInstance(): self
    {
        if (!self::$INSTANCE) {
            $file = self::getConfigFile();
            self::$INSTANCE = is_readable($file) ? self::read($file) : new self();
        }

        return self::$INSTANCE;
    }

    public static function read(string $file): self
    {
        $config = new self();
        if (!is_readable($file)) {
            throw new \InvalidArgumentException("Cannot load config from $file");
        }
        $data = json_decode(file_get_contents($file), true);
        if (!empty($data) && is_array($data)) {
            foreach ($data as $key => $value) {
                $config->{$key} = $value;
            }
        }

        return $config;
    }

    public static function save(Config $config, ?string $file = null): void
    {
        if (!isset($file)) {
            $file = self::getConfigFile();
        }
        $dir = dirname($file);
        if (!is_dir($dir) && !mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException("Cannot create config directory $dir");
        }
        file_put_contents($file, json_encode(get_object_vars($config),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    protected static function getConfigFile(): string
    {
        return self::getHomeDir().'/'.self::CONFIG_JSON;
    }

    private static function getHomeDir(): string
    {
        $home = getenv('HOME');
        if (!empty($home)) {
            return $home;
        }
        if (!empty($_SERVER['HOMEDRIVE']) && !empty($_SERVER['HOMEPATH'])) {
            // home on windows
            $home = $_SERVER['HOMEDRIVE'].$_SERVER['HOMEPATH'];
            // If HOMEPATH is a root directory the path can end with a slash. Make sure
            // that doesn't happen.
            $home = rtrim($home, '\\/');

            return $home;
        }
        throw new \InvalidArgumentException('Cannot detect user home directory');
    }
}
