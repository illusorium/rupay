<?php
namespace Rupay;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class Common
{
    protected $config = [];
    protected $requiredConfigParams = ['login', 'password'];

    protected $testMode = false;

    /**
     * @var Client
     */
    protected static $client;

    protected static $instances;


    /**
     * @param  string $className
     * @param  string $type
     * @return mixed
     * @throws Exception, \InvalidArgumentException
     */
    protected static function getObject($className, $type)
    {
        if (empty($type)) {
            throw new \InvalidArgumentException('Object type must not be empty');
        }
        if (empty($className)) {
            throw new \InvalidArgumentException('Empty ' . $type . ' class name');
        }
        $class = "Rupay\\" . ucfirst($type) . "\\" . ucfirst($className);

        if (!isset(self::$instances[$type][$class])) {

            if (!class_exists($class)) {
                throw new Exception(ucfirst($type) . " $className not found");
            }

            $config = Config::get("$type.$className");

            self::$instances[$type][$class] = new $class($config);

            if (empty(self::$client)) {
                self::$client = new Client();
            }
        }

        return self::$instances[$type][$class];
    }


    /**
     * @param array $config
     */
    protected function __construct($config)
    {
        $this->validateConfig($config);
        $this->config = $config;
        $this->testMode = !empty($config['test_mode']);
    }


    /**
     * @return bool
     */
    public function isTestMode()
    {
        return $this->testMode;
    }


    /**
     * @return string
     */
    public function getKey()
    {
        $class = last(explode('\\', static::class));
        $suffix = $this->isTestMode() ? 'test' : 'prod';
        return $class . '_' . $suffix;
    }


    /**
     * Validates config array
     *
     * @param  array $config
     * @throws \InvalidArgumentException
     */
    protected function validateConfig($config)
    {
        foreach ($this->requiredConfigParams as $param) {
            if (!isset($config[$param])) {
                throw new \InvalidArgumentException("Config parameter \"$param\" is required for " . static::class);
            }
        }
    }


    /**
     * Может использоваться для формирования HTTP response на входящие запросы от платежной системы/онлайн-кассы:
     * уведомления об оплате/фискализации чека, проверочные запросы и т.д.
     * Корректный ответ от сервера служит для отправителя подтверждением, что информация получена и обработана.
     *
     * Возвращает объект GuzzleHttp\Psr7\Response, который должен быть преобразован в ответ веб-сервера.
     *
     * @param  mixed    $body
     * @param  int      $statusCode
     * @param  array    $headers
     * @return Response
     */
    public function setResponse($body = null, $statusCode = 200, $headers = [])
    {
        return Helper\Response::set($body, $statusCode, $headers);
    }
}