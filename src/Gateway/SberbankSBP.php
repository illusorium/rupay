<?php

namespace Rupay\Gateway;

use Carbon\Carbon;
use DateTimeInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Namshi\Cuzzle\Formatter\CurlFormatter;
use Ramsey\Uuid\Uuid;
use Rupay\Common;
use Rupay\Exception;
use Rupay\Gateway;
use Rupay\Helper\Arr;
use Rupay\Order;
use Rupay\Payment;

/**
 * @link https://api.developer.sber.ru/product/PlatiQR
 */
class SberbankSBP extends Gateway
{
    protected $requiredConfigParams = [
        'clientID',
        'clientSecret',
        'memberID',
        'idQR',
        'certPath'
    ];

    protected $preregisterOrder = true;

    protected $baseURI = 'https://api.sberbank.ru:8443/prod/qr/order/v3';

    const URI_TOKEN = 'https://api.sberbank.ru:8443/prod/tokens/v2/oauth';

    protected function __construct($config)
    {
        parent::__construct($config);
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
    }


    /**
     * При регистрации заказа для него генерируется уникальный URL для оплаты.
     * Регистрацию заказа на шлюзе можно выполнить, например, при его импорте в БД,
     * а можно и непосредственно при заходе на страницу оплаты на сайте.
     *
     * {@inheritdoc}
     */
    public function registerOrder($order, $orderNumber = null, $description = null)
    {
        $payment = $this->getPayment($order);
        if ($payment->is_outdated) {
            $this->processOutdatedPaymentData($payment);
            $payment = $this->getPayment($order);
        }

        if ($stored = $payment->payment_url) {
            return $stored;
        }

        $data = [
            'order_number' => $orderNumber ?? $order->order_number,
            'order_create_date' => $order->created_at->format('Y-m-d\TH:i:s\Z'),
            'order_sum' => (int)($order->getSum() * 100),
            'currency' => '643'
        ];
        //Большая вероятность, что заказ будет перерегистрироваться, поэтому делаем номер заказа уникальным
        $data['order_number'] .= '_' . $payment->id;

        if (!empty($description)) {
            $data['description'] = $description;
        }

        $items = $order->getItems();
        if (empty($items)) {
            throw new Exception('Order must contain at least one item to be registered');
        }
        foreach ($items as $i => $item) {
            $item->quantity = round($item->quantity, 3);
            $data['order_params_type'][] = [
                'position_name' => $item->product,
                'position_sum' => (int)($item->getCost() * 100),
            ];

        }
        $data = $this->sendRegisterRequest($data);
        $this->updatePaymentData($payment, $data, $data['order_form_url'], $data['order_id']);
        return $data['order_form_url'];
    }


    /**
     * @param $params
     * @return mixed
     * @throws Exception
     * @throws GuzzleException
     */
    protected function sendRegisterRequest($params)
    {
        try {
            $response = self::$client->request(
                'POST',
                $this->baseURI . '/creation',
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => "Bearer {$this->getToken('https://api.sberbank.ru/qr/order.create')}",
                        'rquid' => $this->generateRQUID(),
                    ],
                    RequestOptions::CERT => [$this->config['certPath'], $this->config['certPassword']],
                    RequestOptions::JSON => array_merge(
                        $this->generateRQParams(),
                        [
                            'member_id' => $this->config['memberID'],
                            'id_qr' => $this->config['idQR'],
                            'sbp_member_id' => '100000000111',
                        ],
                        $params
                    )
                ]
            );
            $data = \GuzzleHttp\json_decode($response->getBody(), true);
            if (!empty($data['order_form_url'])) {
                return $data;
            }
            throw new Exception("Error #{$data['error_code']} registering SPB order: {$data['error_description']}");
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
        $this->revokeOrder($payment);
        $payment->delete();
        return true;
    }

    /**
     * {@inheritdoc}
     * @link https://api.developer.sber.ru/how-to-use/token_oauth
     */
    public function getToken($scope)
    {
        $response = self::$client->post(self::URI_TOKEN, [
            RequestOptions::HEADERS => [
                'authorization' => 'Basic ' . base64_encode($this->config['clientID'] . ':' . $this->config['clientSecret']),
                'rquid' => $this->generateRQUID()
            ],
            RequestOptions::CERT => [$this->config['certPath'], $this->config['certPassword']],
            'curl' => [
                CURLOPT_SSLCERTTYPE => 'P12'
            ],
            RequestOptions::FORM_PARAMS => [
                'scope' => $scope,
                'grant_type' => 'client_credentials'
            ],
        ]);
        $response = \GuzzleHttp\json_decode($response->getBody(), true);
        return $response['access_token'];
    }


    /**
     * {@inheritdoc}
     */
    public function getPaymentStatus($object)
    {
        if ($object instanceof Order) {
            $order = $object;
            $payment = $object->payments->firstWhere('gateway', $this->getKey());
        } elseif ($object instanceof Payment) {
            $payment = $object;
            $order = $payment->order;
        } else {
            throw new InvalidArgumentException("getPaymentStatus() expects argument of type \Rupay\Order or \Rupay\Payment");
        }

        if (!$payment) {
            return 0;
        }

        $data = [
            'partner_order_number' => $order->order_number . '_' . $payment->id
        ];

        if (!empty($payment->gateway_order_id)) {
            $data['order_id'] = $payment->gateway_order_id;
        }

        try {
            $response = self::$client->request(
                'POST',
                $this->baseURI . '/status',
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => "Bearer {$this->getToken('https://api.sberbank.ru/qr/order.status')}",
                        'rquid' => $this->generateRQUID(),
                    ],
                    RequestOptions::CERT => [$this->config['certPath'], $this->config['certPassword']],
                    RequestOptions::JSON => array_merge(
                        $this->generateRQParams(),
                        [
                            'tid' => $this->config['idQR'],
                        ],
                        $data
                    )
                ]
            );
            return \GuzzleHttp\json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            $response = $e->getResponse()->getBody()->getContents();
            throw new Exception($response, $e->getCode());
        }
    }


    /**
     * Список заказов за определенный период, по которым есть операции оплаты или отмены
     * {@inheritdoc}
     */
    public function getRegisterOrdersByPeriod(DateTimeInterface $startDate, DateTimeInterface $endDate)
    {
        $data = [
            'startPeriod' => $startDate->format('Y-m-d\TH:i:s\Z'),
            'endPeriod' => $endDate->format('Y-m-d\TH:i:s\Z'),
        ];
        try {
            $rq_data = $this->generateRQParams();
            $response = self::$client->request(
                'POST',
                $this->baseURI . '/registry',
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => "Bearer {$this->getToken('auth://qr/order.registry')}",
                        'rquid' => $this->generateRQUID(),
                    ],
                    RequestOptions::CERT => [$this->config['certPath'], $this->config['certPassword']],
                    RequestOptions::JSON => array_merge(
                        [
                            'rqUid' => $rq_data['rq_uid'],
                            'rqTm' => $rq_data['rq_tm'],
                            'idQR' => $this->config['idQR'],
                            'registryType' => 'REGISTRY',
                        ],
                        $data
                    )
                ]
            );
            $response = \GuzzleHttp\json_decode($response->getBody(), true);
            return $response['registryData']['orderParams']['orderParam'];
        } catch (ClientException $e) {
            $response = $e->getResponse()->getBody()->getContents();
            throw new Exception($response, $e->getCode());
        }
    }


    /**
     * Отмена заказа на шлюзе
     * {@inheritdoc}
     */
    public function revokeOrder($object)
    {
        if ($object instanceof Order) {
            $payment = $object->payments->firstWhere('gateway', $this->getKey());
        } elseif ($object instanceof Payment) {
            $payment = $object;
        } else {
            throw new InvalidArgumentException("getPaymentStatus() expects argument of type \Rupay\Order or \Rupay\Payment");
        }
        try {
            $response = self::$client->request(
                'POST',
                $this->baseURI . '/revocation',
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => "Bearer {$this->getToken('https://api.sberbank.ru/qr/order.revoke')}",
                        'rquid' => $this->generateRQUID(),
                    ],
                    RequestOptions::CERT => [$this->config['certPath'], $this->config['certPassword']],
                    RequestOptions::JSON => array_merge(
                        $this->generateRQParams(),
                        [
                            'order_id' => $payment->gateway_order_id,
                        ]
                    )
                ]
            );
            return \GuzzleHttp\json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            $response = $e->getResponse()->getBody()->getContents();
            throw new Exception($response, $e->getCode());
        }
    }

    public function refundOrder($object, $sum)
    {
        if ($object instanceof Order) {
            $payment = $object->payments->firstWhere('gateway', $this->getKey());
            $order = $object;
        } elseif ($object instanceof Payment) {
            $payment = $object;
            $order = $payment->order;
        } else {
            throw new InvalidArgumentException('getPaymentStatus() expects argument of type \Rupay\Order or \Rupay\Payment');
        }

        $currentStatus = $this->getPaymentStatus($payment);
        $orderOperationParams = $currentStatus['order_operation_params'];
        $orderOperationParams = array_filter($orderOperationParams, function ($o) {
            return $o['response_code'] === '00' && $o['operation_type'] === 'PAY';
        });
        if (!$orderOperationParams) {
            return null;
        }
        $orderOperation = $orderOperationParams[0];

        try {
            $response = self::$client->request(
                'POST',
                $this->baseURI . '/cancel',
                [
                    RequestOptions::HEADERS => [
                        'Authorization' => "Bearer {$this->getToken('https://api.sberbank.ru/qr/order.cancel')}",
                        'rquid' => $this->generateRQUID(),
                    ],
                    RequestOptions::CERT => [$this->config['certPath'], $this->config['certPassword']],
                    RequestOptions::JSON => array_merge(
                        $this->generateRQParams(),
                        [
                            'order_id' => $payment->gateway_order_id,
                            'cancel_operation_sum' => (int)$sum,
                            'id_qr' => $this->config['idQR'],
                            'tid' => $this->config['idQR'],
                            'operation_currency' => '643',
                            'operation_id' => $orderOperation['operation_id'],
                            'auth_code' => $orderOperation['auth_code'],
                            'operation_type' => 'REFUND',
                            'operation_description' => "Возврат по счету {$order->order_number}",
                        ]
                    )
                ]
            );
            return \GuzzleHttp\json_decode($response->getBody(), true);
        } catch (ClientException $e) {
            $response = $e->getResponse()->getBody()->getContents();
            throw new Exception($response, $e->getCode());
        }
    }

    public function getPaymentStatusCode($object)
    {
        $statusData = $this->getPaymentStatus($object);
        $paymentState = Arr::path($statusData, 'order_state');
        return $this->switchPaymentStatusCode($paymentState);
    }

    public function switchPaymentStatusCode($paymentState)
    {
        switch ($paymentState) {
            case 'REFUNDED' :
                return Common::ORDER_STATUS_REFUNDED;
            case 'ON_PAYMENT' :
                return Common::ORDER_STATUS_ON_PAYMENT;
            case 'PAID' :
                return Common::ORDER_STATUS_DEPOSITED;
            case 'CREATED'  :
                return Common::ORDER_STATUS_CREATED;
            case 'REVOKED'  :
            case 'EXPIRED'  :
            case 'DECLINED'  :
                return Common::ORDER_STATUS_DECLINED;
            default:
                return false;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findOrderByRequestData($data = [])
    {
        if (empty($data)) {
            return null;
        }

        $payment = $this->findPaymentByRequestData($data);
        return $payment ? $payment->order : null;
    }

    /**
     * {@inheritdoc}
     */
    public function findPaymentByRequestData($data)
    {
        return Payment::findPayment(['gateway_order_id' => Arr::get($data, 'orderId')]);
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

    public function generateRQUID()
    {
        return str_replace('-', '', Uuid::uuid4());
    }

    public function generateRQParams()
    {
        return [
            'rq_uid' => $this->generateRQUID(),
            'rq_tm' => Carbon::now()->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getCallbackOperationStatus($data = [])
    {
        if (empty($data)) {
            return null;
        }

        if (empty($data['orderState'])) {
            return false;
        }

        return $this->switchPaymentStatusCode($data['orderState']);
    }

    public function checkSignature($order, $data = [])
    {
        return ($this->config['idQR'] === $data['tid']) && ($this->config['memberID'] === $data['memberId']);
    }
}
