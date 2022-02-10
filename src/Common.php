<?php
namespace Rupay;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;

class Common
{
    // Possible operation statuses (could be used in processing of callback notifications)
    const ORDER_STATUS_CREATED   = 1; // заказ создан на шлюзе
    const ORDER_STATUS_APPROVED  = 2; // операция удержания (холдирования) суммы (для двухстадийных платежей)
    const ORDER_STATUS_DEPOSITED = 3; // операция завершения
    const ORDER_STATUS_DECLINED  = 4; // заказ отклонен (например, закончилось время жизни заказа на шлюзе)
    const ORDER_STATUS_REVERSED  = 5; // операция отмены
    const ORDER_STATUS_REFUNDED  = 6; // есть успешные операции Полного возврата/Частичного возврата\
    const ORDER_STATUS_ON_PAYMENT= 9; // ожидает подтверждения платежа от СБП

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
     * @throws Exception
     */
    protected function __construct($config)
    {
        if (empty($config['vat_tag'])) {
            if ($vat = Config::get('settings.vat_tag')) {
                $config['vat_tag'] = $vat;
            }
        }
        static::validateConfig($config);
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