<?php
namespace Rupay;

use Illuminate\Database\Capsule\Manager as Capsule;


class Database
{
    /**
     * @var Database
     */
    protected static $instance;

    /**
     * @var Capsule
     */
    protected $capsule;

    protected function __construct($connectionParams)
    {
        if (empty($this->capsule)) {
            $this->capsule = new Capsule();
            $this->capsule->addConnection($connectionParams);
            $this->capsule->bootEloquent();
        }
    }


    public static function instance()
    {
        if (empty(self::$instance)) {
            $connectionParams = Config::get('database');
            self::$instance = new self($connectionParams);
        }
        return self::$instance;
    }
}