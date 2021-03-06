<?php
namespace Rupay\Gateway;


use Rupay\Exception;
use Rupay\Order;
use GuzzleHttp\Psr7\Response;
use Rupay\Payment;

interface GatewayInterface
{
    /**
     * Returns order signature that can be used for API requests for some gateways
     * Возвращает контрольную сумму заказа. Она нужна для выполнения запросов к API некоторых сервисов.
     *
     * Второй параметр может использоваться при обработке входяших запросов от агрегатора/шлюза
     * (уведомления об оплате, проверочные запросы) - он должен содержать параметры этого запроса.
     * В этом случае метод служит для идентификации отправителя и проверки целостности данных.
     *
     * @param  Order $order
     * @param  array $data  Массив дополнительных параметров, которые могут использоваться для формирования подписи
     *                      (например, номер операции в платежной системе)
     * @return string
     */
//    public function sign($order, $data = []);

    /**
     * Checks signature of incoming request from the gateway.
     * Проверка подписи входящего запроса (используется для принятия уведомлений об оплатах и проверочных запросов)
     *
     * @param  Order $order
     * @param  array $data
     * @return bool
     */
    public function checkSignature($order, $data = []);


    /**
     * Parse data of callback request to get operation type and its status
     *
     * @param array $data
     * @return mixed
     */
    public function getCallbackOperationStatus($data = []);


    /**
     * Whether order should be registered at the gateway as soon as possible (e.g. after being added into DB).
     * Some gateways generate unique payment links for each order,
     * so it make sense to get that links before buyer visits payment page
     *
     * @return bool
     */
    public function ordersNeedPreregistration();


    /**
     * URL для оплаты возвращается при регистрации заказа на шлюзе
     * На некоторых шлюзах заказ должен быть предварительно зарегистрирован на шлюзе до попытки оплаты.
     * Например, при регистрации заказа может возвращаться уникальная ссылка на оплату на шлюзе
     * которая должна быть помещена в форму оплаты на сайте.
     *
     * Если предварительная регистрация заказа для шлюза не требуется, можно определить метод-заглушку
     *
     * @param  Order  $order
     * @param  string $orderNumber  Override order number for the gateway (by default it is $order->order_number)
     * @param  string $description  Custom order description for the gateway's payment page
     * @throws Exception
     * @return mixed
     */
    public function registerOrder($order, $orderNumber = null, $description = null);


    /**
     * @param  Payment|Order $object
     * @return mixed
     * @throws Exception
     */
    public function getPaymentStatus($object);


    /**
     * Код статуса платежа. Используется для более "читабельного" сравнения в коде
     * Например, у Сбербанка при запросе статуса через
     * $status = getPaymentStatus($order) статус будет в
     * $status['paymentAmountInfo']['paymentState'] (DEPOSITED, DECLINED и т.д.)
     * В этом методе у каждого класса должно быть прописано сопоставление всех подобных статусов
     * константам ORDER_STATUS_* из класса Common
     *
     * @param  Payment|Order $object
     * @return mixed
     * @throws Exception
     */
    public function getPaymentStatusCode($object);


    /**
     * Returns URL which must be set as "action" attribute in payment form
     *
     * @param  Order $order
     * @return string
     */
    public function getPaymentUrl($order = null);


    /**
     * Returns Response object for successfully processed request from the gateway
     *
     * @param  mixed    $data  Depends on concrete gateway: e.g. gateway may expect Order-based hash in response body
     * @return Response
     */
    public function setResponseSuccess($data = null);


    /**
     * Returns Response object for failed request
     *
     * @param  int      $statusCode
     * @return Response
     */
    public function setResponseFail($statusCode = 404);
}