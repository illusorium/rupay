<?php
namespace Rupay\Till;

use GuzzleHttp\Exception\ClientException;
use Rupay\Helper\Arr;
use Rupay\Helper\FZ54;
use Rupay\Exception;
use Rupay\Order;
use Rupay\Till;

/**
 * @link https://support.modulkassa.ru/upload/medialibrary/abb/api-avtomaticheskoy-fiskalizatsii-chekov-internet_magazinov-_ver.1.2_.pdf
 *
 * @package Rupay\Till
 */
class Modulkassa extends Till
{
    const BASE_URI_TEST = 'https://demo-fn.avanpos.com/fn';
    const BASE_URI_PROD = 'https://service.modulpos.ru/api/fn';

    /**
     * Если при отправке чека на сервер фискализации не был задан responseURL
     * (на который будут отправляться уведомления об изменении статуса документа),
     * можно узнать статус документа, отправив запрос с его идентификатором на STATUS_URI.
     * Вместо {{document_id}} нужно указать id документа, отправлявшийся на DATA_URI.
     */
    const STATUS_URI      = '/v1/doc/{{document_id}}/status';      // для проверки статуса отправленного ранее документа
    const ASSOCIATE_URI   = '/v1/associate/{{retail_point_uuid}}'; // для привязки интернет-магазина к розничной точке
    const CHECK_URI       = '/v1/status';                          // для проверки готовности сервиса фискализации
    const DATA_URI        = '/v1/doc';                             // для отправки документов

    const PAYMENT_CARD = 'CARD';     // оплата картой
    const PAYMENT_CASH = 'CASH';     // оплата наличными

    protected $requiredConfigParams = ['login', 'password', 'vat_tag'];

    /**
     * Array from which Auth header will be formed for requests to fiscalization server
     *
     * @var array
     */
    protected $authCredentials;


    protected function __construct($config)
    {
        parent::__construct($config);
        $this->baseURI = $this->testMode ? self::BASE_URI_TEST : self::BASE_URI_PROD;
        $this->authCredentials = [
            'auth' => [$config['login'], $config['password']]
        ];
    }


    protected function validateConfig($config)
    {
        parent::validateConfig($config);
        if (!in_array(Arr::get($config, 'vat_tag'), FZ54::$vatTags)) {
            throw new \InvalidArgumentException('Incorrect vat_tag value in modulkassa config');
        }
    }


    /**
     * @param  string $method
     * @param  string $uri
     * @param  array  $body
     * @return array
     * @throws Exception
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    protected function requestWrapper($method, $uri, $body = null)
    {
        $options = $this->authCredentials;
        if (!empty($body)) {
            $options['json'] = $body;
        }

        try {
            $response = self::$client->request($method, $uri, $options);
            return \GuzzleHttp\json_decode($response->getBody(), true);

        } catch (ClientException $e) {

            $response = $e->getResponse()->getBody()->getContents();
            $jsonResponse = \GuzzleHttp\json_decode($response);

            if ($jsonResponse && $jsonResponse->message) {
                $message = $jsonResponse->message;
            } else {
                $message = $response;
            }

            throw new Exception($message, $e->getCode());
        }
    }


    /**
     * {@inheritdoc}
     */
    public function getServiceStatus()
    {
        return $this->requestWrapper('GET', $this->baseURI . self::CHECK_URI);
    }


    /**
     * {@inheritdoc}
     */
    public function isReady($data = [])
    {
        if (empty($data)) {
            $data = $this->getServiceStatus();
        }
        return $this->isTestMode() || Arr::get((array) $data, 'status') === 'READY';
    }


    /**
     * {@inheritdoc}
     */
    public function lastCheck($data = [], $dateFormat = 'Y-m-d H:i:s')
    {
        if ($this->isTestMode()) {
            return $dateFormat ? date($dateFormat) : time();
        }

        if (empty($data)) {
            $data = $this->getServiceStatus();
        }

        if ($dateTime = Arr::get($data, 'dateTime')) {
            $dateTime = strtotime($dateTime);
        } else {
            $dateTime = 0;
        }

        return $dateFormat ? date($dateFormat, $dateTime) : $dateTime;
    }


    /**
     * {@inheritdoc}
     */
    public function sendReceipt(
        $order,
        $responseURL = null,
        $docType = self::ORDER_STATUS_DEPOSITED,
        $paymentType = self::PAYMENT_CARD
    ) {
        if (empty($order)) {
            throw new Exception('Order data must not be empty');
        }
        if (!in_array($docType, [self::ORDER_STATUS_DEPOSITED, self::ORDER_STATUS_REFUNDED])) {
            throw new Exception("Incorrect document type $docType");
        }
        $docType = ($docType === self::ORDER_STATUS_DEPOSITED) ? 'SALE' : 'RETURN';

        $email = $order->email;
        if (empty($email)) {
            throw new Exception("Buyer email is required to send order to Modulkassa fiscalization service");
        }

        /*
         * TODO
         * "старый" transaction_id еще может понадобиться
         * (например, при проверке статуса, если orderNumber=transaction_id);
         * придумать более надежное решение
         */
        $transactionId = $order->checkTransactionId(true);
        $order->save();

        $requestData = [
            'id' => $transactionId,
            'checkoutDateTime' => date('c'),
            'docNum' => $order->order_number,
            'docType' => $docType,
            'email' => $email,
            'inventPositions' => [],
            'moneyPositions' => []
        ];

        if (is_null($responseURL)) {
            $responseURL = Arr::get($this->config, 'responseURL');
        }
        if (!empty($responseURL)) {
            $requestData['responseURL'] = \Rupay\Helper\Order::fillResponseURL($responseURL, $order);
        }

        if ($items = $order->getItems()) {

            foreach ($items as $item) {
                $requestData['inventPositions'][] = [
                    'name' => $item->product,
                    'price' => $item->price,
                    'quantity' => $item->quantity,
                    'measure' => $item->units,
                    'vatTag' => $this->config['vat_tag']
                ];
            }

            /**
             * Сейчас все оплаты одного типа - либо картой, либо наличными.
             * По документации МодульКассы moneyPositions может состоять из элементов с разными paymentType.
             * При необходимости реализовать этот функционал.
             */
            $requestData['moneyPositions'][] = [
                'paymentType' => $paymentType,
                'sum' => $order->getSum() // $this->getOrderSumForOldApi($items)
            ];
        }

        return $this->requestWrapper('POST', $this->baseURI . self::DATA_URI, $requestData);
    }


    /**
     * На стороне сервера фискализации МодульКассы производится сверка суммы заказа по позициям в чеке.
     * Раньше вычисленная там стоимость каждой позиции не округлялась до копеек
     * (например, покупается 0,321 кг продукции по цене 9876,54 р. за кг), поэтому при фискализации
     * могла возникнуть ошибка из-за расхождения переданной в moneyPositions и вычисленной стоимости заказа.
     * Сейчас это вроде бы исправлено. После проверки этот метод можно будет удалить.
     *
     * @deprecated
     * @param array $items
     * @return float
     */
    protected function getOrderSumForOldApi($items)
    {
        $sum = 0;
        foreach ($items as $item) {
            $itemCost = $item['price'] * $item['quantity'];
//            $itemCost = round($item['price'] * $item['quantity'], 2);

            $sum += $itemCost;
        }
        return $sum;
    }


    /**
     * {@inheritdoc}
     */
    public function getReceiptStatus($id)
    {
        if ($id instanceof Order) {
            $id = $id->transaction_id;
        }
        $statusURI = str_replace('{{document_id}}', urlencode($id), self::STATUS_URI);
        return $this->requestWrapper('GET', $this->baseURI . $statusURI);
    }


    /**
     * {@inheritdoc}
     */
    public function findOrderByRequestData($data = [])
    {
        if (!empty($data)) {
            // массив параметров для поиска заказа уже сформирован во внешнем скрипте
            return Order::findOrder($data);
        }

        if (!$template = Arr::get($this->config, 'responseURL')) {
            // непонятно, как искать заказ: шаблон responseURL не задан, параметры для поиска заказа - тоже
            throw new Exception("Could not find order: responseURL template is not set");
        }

        return \Rupay\Helper\Order::getOrderByUrlTemplate($template);
    }
}