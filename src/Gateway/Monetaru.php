<?php
namespace Rupay\Gateway;

use Rupay\Exception;
use Rupay\Gateway;
use Rupay\Helper\Arr;
use Rupay\Helper\ISO4217;
use Rupay\Order;

/**
 * @link https://www.moneta.ru/doc/MONETA.Assistant.en.pdf
 * @link https://www.moneta.ru/doc/MONETA.Assistant.ru.pdf
 *
 * - В запросах на оплату можно не указывать сумму заказа (MNT_AMOUNT), если в личном кабинете задан Check URL.
 *   На него Moneta.ru будет отправлять запросы, чтобы убедиться в корректности данных для оплаты.
 * - Если в запросе передается MNT_SIGNATURE, то также должен быть передан MNT_TRANSACTION_ID
 *
 * @package Rupay\Provider
 */
class Monetaru extends Gateway
{
    protected $requiredConfigParams = [
        'MNT_ID',
        'currency',
        'DATA_INTEGRITY_CODE'
    ];

    const PAYMENT_URL      = 'https://www.payanyway.ru/assistant.htm';
    const PAYMENT_URL_TEST = 'https://demo.moneta.ru/assistant.htm';

    /**
     * Method to passing parameters within requests to Pay URL/Check URL
     * Updates from yml config file ("method" parameter).
     * Value must be the same as in account settings on Moneta.ru
     *
     * @var string
     */
    protected $method = 'GET';

    protected function validateConfig($config)
    {
        parent::validateConfig($config);
        if (!in_array($config['currency'], ISO4217::$currencyCodes)) {
            throw new \InvalidArgumentException("Incorrect currency code \"{$config['currency']}\"");
        }
        if (!empty($config['method'])) {
            $method = strtoupper($config['method']);
            if (!in_array($method, ['GET', 'POST'])) {
                throw new Exception("Unsupported HTTP method $method");
            }
            $this->method = $method;
        }
    }


    /**
     * Moneta.ru doesn't need orders to be preregistered:
     * order parameters are passing with payment form as hidden values
     *
     * {@inheritdoc}
     */
    public function registerOrder($order, $lifetime = null, $orderNumber = null, $description = null)
    {
        return true;
    }


    /**
     * {@inheritdoc}
     */
    public function getPaymentUrl($order)
    {
        return $this->testMode ? self::PAYMENT_URL_TEST : self::PAYMENT_URL;
    }


    /**
     * @param  Order $order
     * @param  array $data  Additional parameters that can be used to get correct signature
     * @return string
     */
    public function sign($order, $data = [])
    {
        $testMode = $this->testMode ? '1' : '0';
        $sum = number_format($order->getSum(), 2, '.', '');

        // Внутренний идентификатор пользователя, однозначно определяющий получателя в учетной системе магазина
        $subscriberId = $order->client_id ?: '';

        return md5(
            Arr::get($data, 'MNT_COMMAND', '') .
            $this->config['MNT_ID'] .
            $order->transaction_id .
            Arr::get($data, 'MNT_OPERATION_ID', '') .
            $sum .
            $this->config['currency'] .
            $subscriberId .
            $testMode .
            $this->config['DATA_INTEGRITY_CODE']
        );
    }


    /**
     * {@inheritdoc}
     */
    public function checkSignature($order, $data = [])
    {
        if (empty($data)) {
            $data = $this->method === 'GET' ? $_GET : $_POST;
        }
        return Arr::get($data, 'MNT_SIGNATURE') === $this->sign($order, $data);
    }


    /**
     * {@inheritdoc}
     */
    public function getCallbackOperationStatus($data = [])
    {
        if (empty($data)) {
            $data = $_GET;
        }

        if (empty($data['MNT_OPERATION_ID'])) {
            return false;
        }

        return [
            'operation' => self::PAYMENT_STATUS_DEPOSITED,
            'status'    => true
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function findOrderByRequestData($data = [])
    {
        if (empty($data)) {
            $data = $this->method === 'GET' ? $_GET : $_POST;
        }
        return Order::findOrder(['transaction_id' => Arr::get($data, 'MNT_TRANSACTION_ID')]);
    }


    /**
     * {@inheritdoc}
     */
    public function setResponse($body = null, $statusCode = 200, $customHeaders = [])
    {
        if (empty($customHeaders['content-type'])) {
            $customHeaders['content-type'] = 'text/plain; charset=utf-8';
        }
        return parent::setResponse($body, $statusCode, $customHeaders);
    }


    /**
     * {@inheritdoc}
     */
    public function setResponseSuccess($data = null)
    {
        return $this->setResponse('SUCCESS');
    }


    /**
     * {@inheritdoc}
     */
    public function setResponseFail($statusCode = 402)
    {
        return $this->setResponse('FAIL', $statusCode);
    }
}