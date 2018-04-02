<?php
namespace Rupay\Gateway;

use GuzzleHttp\Exception\ClientException;
use Rupay\Config;
use Rupay\Exception;
use Rupay\Gateway;
use Rupay\Helper\Arr;
use Rupay\Helper\FZ54;
use Rupay\Helper\ISO4217;
use Rupay\Item;
use Rupay\Order;
use Rupay\Payment;

/**
 * @link https://securepayments.sberbank.ru/wiki/doku.php/integration:api:start#%D0%B8%D0%BD%D1%82%D0%B5%D1%80%D1%84%D0%B5%D0%B9%D1%81_rest
 */
class Sberbank extends Gateway
{
    protected $requiredConfigParams = [
        'userName',
        'password'
    ];

    protected $preregisterOrder = true;

    protected $baseURI;

    const BASE_URI_TEST = 'https://3dsec.sberbank.ru/payment/rest';
    const BASE_URI_PROD = 'https://securepayments.sberbank.ru/payment/rest';


    protected function __construct($config)
    {
        parent::__construct($config);
        $this->baseURI = $this->testMode ? self::BASE_URI_TEST : self::BASE_URI_PROD;
    }


    /**
     * @var string
     */
    protected $method = 'POST';


    /**
     * {@inheritdoc}
     */
    protected function validateConfig($config)
    {
        parent::validateConfig($config);
        if (!empty($config['currency']) && !in_array($config['currency'], ISO4217::$currencyCodes)) {
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
     * При регистрации заказа для него генерируется уникальный URL для оплаты.
     * Регистрацию заказа на шлюзе можно выполнить, например, при его импорте в БД,
     * а можно и непосредственно при заходе на страницу оплаты на сайте.
     *
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:rest:requests:register
     *
     * В документации указано, что orderNumber должен состоять только из цифр и латинских букв,
     * но по факту регистрируются заказы и с другими символами, в т.ч. кириллическими.
     * Тем не менее, в случае с кириллицей возникают сложности при получении уведомлений с контрольной суммой:
     * не удается сопоставить checksum переданным данным ($this->checkSignature()). В остальных случаях все корректно.
     * Опытным путем выяснено, что orderNumber с пробелами, слэшами и тире регистрируется и подтверждается корректно.
     * Обещали добавить разъяснения в документацию.
     *
     * {@inheritdoc}
     */
    public function registerOrder($order, $orderNumber = null, $description = null)
    {
        $payment = $this->getPayment($order);
        if ($payment->is_outdated) {
            $this->processOutdatedPaymentData($payment);
        }

        if ($stored = $payment->payment_url) {
            return $stored;
        }

        $options = [
            'userName'    => $this->config['userName'],
            'password'    => $this->config['password'],
            'amount'      => $order->getSum() * 100 // копейки/центы
        ];

        if (empty($orderNumber)) {
            $customParam = Arr::get($this->config, 'orderNumber');
            if (!empty($customParam)) {
                $orderNumber = $order->$customParam;
            }
        }
        $options['orderNumber'] = !empty($orderNumber) ? $orderNumber : $order->order_number;

        if (preg_match('|[а-я]|ui', $options['orderNumber'])) {
            throw new Exception(
                "Sberbank API: wrong orderNumber \"{$options['orderNumber']}\" - must not contain cyrillic letters"
            );
        }

        if ($orderNumber !== $order->order_number) {
            $options['jsonParams']  = json_encode(['merchantOrderId' => $order->order_number]);
        }

        if (!empty($this->config['currency'])) {
            $options['currency'] = $this->config['currency'];
        }
        if (!empty($this->config['success_url'])) {
            $options['returnUrl'] = $this->config['success_url'];
        }
        if (!empty($this->config['fail_url'])) {
            $options['failUrl'] = $this->config['fail_url'];
        }

        if (!empty($description)) {
            $options['description'] = $description;
        }

        if ($validThrough = $order->validThrough()) {
            $options['expirationDate'] = date('c', $validThrough);
        } elseif (!empty($this->config['link_lifetime'])) {
            $options['sessionTimeoutSecs'] = strtotime($this->config['link_lifetime']);
        }

        if (!empty($this->config['send_items'])) {

            $items = $order->getItems();
            if (empty($items)) {
                throw new Exception('Order must contain at least one item to be registered');
            }

            if (!empty($this->config['auto_fiscalization'])) {

                $taxSystem = Config::get('settings.tax_system');
                if (!in_array($taxSystem, FZ54::$taxSystems)) {
                    throw new Exception("Invalid tax system index ($taxSystem)");
                }
                $options['taxSystem'] = $taxSystem;

                $vat = Config::get('settings.vat_tag');
                switch ($vat) {
                    case FZ54::VAT_NONE:   $tax = 0; break;
                    case FZ54::VAT_0:      $tax = 1; break;
                    case FZ54::VAT_10:     $tax = 2; break;
                    case FZ54::VAT_18:     $tax = 3; break;
                    case FZ54::VAT_10_110: $tax = 4; break;
                    case FZ54::VAT_18_118: $tax = 5; break;
                    default:
                        throw new Exception("Invalid vat tag value ($vat)");
                }
            }

            $orderBundle = [
                'cartItems' => [
                    'items' => []
                ]
            ];

            $customerDetails = [];
            if ($order->email) {
                $customerDetails['email'] = $order->email;
            }
            if ($order->phone) {
                $phone = preg_replace('|[^\d]|', '', $order->phone);
                if (!empty($phone)) {
                    $customerDetails['phone'] = $phone;
                }
            }

            if (!empty($customerDetails)) {
                $orderBundle['customerDetails'] = $customerDetails;
            }

            /**
             * @var Item $item
             */
            foreach ($items as $i => $item) {
                $cartItem = [
                    'positionId' => $i + 1,
                    'name'       => $item->product,
                    'quantity'   => [
                        'value'   => $item->quantity,
                        'measure' => $item->units
                    ],
                    'itemAmount' => $item->getCost() * 100,
                    'itemCode'   => $options['orderNumber'] . '-' . $i
                ];

                if (isset($tax)) {
                    $cartItem['tax'] = [
                        'taxType' => $tax
                    ];
                    $cartItem['itemPrice']  = $item->price * 100;
                }

                array_push($orderBundle['cartItems']['items'], $cartItem);
            }

            $options['orderBundle'] = json_encode($orderBundle);
        }

        $data = $this->sendRegisterRequest($options);
        $this->updatePaymentData($payment, $data, $data['formUrl'], $data['orderId']);
        return $data['formUrl'];
    }


    /**
     * @param $params
     * @return mixed
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function sendRegisterRequest($params)
    {
        try {
            $response = self::$client->request(
                $this->method,
                $this->baseURI . '/register.do',
                ['form_params' => $params]
            );

            $data = \GuzzleHttp\json_decode($response->getBody(), true);
            if (!empty($data['formUrl'])) {
                return $data;
            } else {
                throw new Exception("Error #{$data['errorCode']} registering order: {$data['errorMessage']}");
            }
        } catch (ClientException $e) {

            $response = $e->getResponse()->getBody()->getContents();
            throw new Exception($response, $e->getCode());
        }
    }


    /**
     * {@inheritdoc}
     */
    public function getPaymentUrl($order = null)
    {
        return $this->registerOrder($order);
    }


    /**
     * {@inheritdoc}
     */
    protected function processOutdatedPaymentData($payment)
    {
        // Declining orders via API is not available so far, although it could be done from the operator panel

        parent::processOutdatedPaymentData($payment);
    }


    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus($object)
    {
        $options = [
            'userName'    => $this->config['userName'],
            'password'    => $this->config['password']
        ];

        if ($object instanceof Order) {
            $order = $object;
            $payment = $object->payment;
        } elseif ($object instanceof Payment) {
            $payment = $object;
            $order = $payment->order;
        } else {
            throw new \InvalidArgumentException("getPaymentStatus() expects argument of type \Rupay\Order or \Rupay\Payment");
        }

        if (!empty($payment->gateway_order_id)) {
            $options['orderId'] = $payment->gateway_order_id;
        } else {

            $customParam = Arr::get($this->config, 'orderNumber');
            if (!empty($customParam)) {
                $orderNumber = $order->$customParam;
            } else {
                $orderNumber = $order->order_number;
            }

            if (empty($orderNumber)) {
                throw new Exception("Parameter $customParam is empty");
            }
            $options['orderNumber'] = $orderNumber;
        }

        try {
            $response = self::$client->request(
                $this->method,
                $this->baseURI . '/getOrderStatusExtended.do',
                ['form_params' => $options]
            );

            return \GuzzleHttp\json_decode($response->getBody(), true);

        } catch (ClientException $e) {

            $response = $e->getResponse()->getBody()->getContents();
            throw new Exception($response, $e->getCode());
        }
    }


    /**
     * Для подключений callback-уведомлений нужно обращаться в техподдержку Сбербанка, указав URL,
     * на котором планируется обрабатывать запросы, а также тип уведомлений (с контрольной суммой или без нее).
     * Если нужны уведомления с контрольной суммой, то в ответ также будет выслан секретный ключ,
     * который нужно будет добавить в настройки в поле secret_key (и установить callback_use_checksum = 1).
     * @see https://securepayments.sberbank.ru/wiki/doku.php/integration:api:callback:start
     *
     * {@inheritdoc}
     */
    public function checkSignature($order, $data = [])
    {
        $useChecksum = (bool) Arr::get($this->config, 'callback_use_checksum');
        if (!$useChecksum) {
            return true;
        }

        $secretKey = Arr::get($this->config, 'secret_key');
        if (empty($secretKey)) {
            throw new Exception('Could not check validity of gateway callback request: secret key not set');
        }

        if (empty($data)) {
            $data = $_GET;
        }

        $checksum = Arr::get($data, 'checksum');
        if (!empty($data['checksum'])) {
            unset($data['checksum']);
        }

        ksort($data);
        $str = '';
        foreach ($data as $key => $value) {
            $str .= $key . ';' . $value . ';';
        }
        $hash = hash_hmac('sha256', $str, $secretKey);
        return strtoupper($hash) === $checksum;
    }


    /**
     * {@inheritdoc}
     */
    public function getCallbackOperationStatus($data = [])
    {
        if (empty($data)) {
            $data = $_GET;
        }

        if (empty($data['operation']) || !isset($data['status'])) {
            return false;
        }

        switch ($data['operation']) {
            case 'deposited'        : $operation = self::ORDER_STATUS_DEPOSITED; break;
            case 'reversed'         : $operation = self::ORDER_STATUS_REVERSED; break;
            case 'refunded'         : $operation = self::ORDER_STATUS_REFUNDED; break;
            case 'approved'         : $operation = self::ORDER_STATUS_APPROVED; break;
            case 'declinedByTimeout': $operation = self::ORDER_STATUS_DECLINED; break;
            default: return false;
        }

        return [
            'operation' => $operation,
            'status'    => (bool) $data['status']
        ];
    }


    /**
     * {@inheritdoc}
     */
    public function findOrderByRequestData($data = [])
    {
        if (empty($data)) {
            $data = $_GET;
        }

        // There is also "mdOrder" parameter (order number at the gateway) which also can be used to find local order

        $customField = Arr::get($this->config, 'orderNumber');
        $field = $customField ?: 'order_number';

        return Order::findOrder([
            $field => urldecode(Arr::get($data, 'orderNumber'))
        ]);
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