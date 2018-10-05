<?php
namespace Rupay;

use Symfony\Component\Yaml\Yaml;
use Rupay\Helper\Arr;

class Config
{
    protected static $fileName = 'rupay.yml';

    protected static $_parsed;

    protected function __construct($configPath = null)
    {
        if (empty($configPath)) {
            if (!$cwd = getcwd()) {
                throw new Exception("Could not define current working directory");
            }
            $file = $cwd . DIRECTORY_SEPARATOR . self::$fileName;
        } elseif ($file = realpath($configPath)) {
            if (is_dir($file)) {
                $file .= DIRECTORY_SEPARATOR . self::$fileName;
            }
        } else {
            throw new Exception("Could not load config file");
        }

        if (!is_readable($file)) {
            throw new Exception("Could not read config file $file");
        }

        self::$_parsed = Yaml::parse(file_get_contents($file));
    }


    public static function load($configPath = null)
    {
        if (!empty(self::$_parsed)) {
            return;
        }
        new self($configPath);
    }

    /**
     * @param  string $path
     * @param  mixed  $default
     * @return mixed
     * @throws Exception
     */
    public static function get($path = null, $default = null)
    {
        if (empty(self::$_parsed)) {
            new self();
        }

        if (empty($path) || $path === '*') {
            return self::$_parsed;
        }
        return Arr::path(self::$_parsed, $path, $default);
    }
}