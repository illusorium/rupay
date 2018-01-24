<?php
namespace Rupay;

use GuzzleHttp\Psr7\Response;

/**
 * Interface defining methods for processing different callback requests from payment service or online-till
 * (fiscalization server).
 *
 * Interface CallbackInterface
 * @package Rupay
 */
interface CallbackInterface
{
    /**
     * Finds order by parameters of incoming request
     *
     * Возвращает объект Order, соответствующий заказу, найденному по параметрам запроса
     * (обычно это transaction_id), или false, если заказ не найден.
     * Если массив $data не пуст, поиск заказа должен осуществляться по переданными данным.
     * Иначе будут использоваться глобальные массивы (в зависимости от свойств конкретного объекта)
     *
     * @param  array $data Array of parameters. If
     * @return Order|false
     */
    public function findOrderByRequestData($data = []);


    /**
     * Returns GuzzleHttp\Psr7\Response object with appropriate parameters for the calling object (gateway/till).
     * This object then should be converted to HTTP response for service that has sent callback request.
     *
     * @see \Rupay\Helper\Response
     *
     * @param  mixed    $body
     * @param  int      $statusCode
     * @param  array    $headers
     * @return Response
     */
    public function setResponse($body = null, $statusCode = 200, $headers = []);
}