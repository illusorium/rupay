<?php
namespace Rupay\Till;

use Rupay\Exception;
use Rupay\Order;

/**
 * Interface of interaction with fiscalization server in accordance with Federal Law of the Russian Federation No. 54-FZ
 *
 * @package Rupay\Till
 */
interface TillInterface
{
    /**
     * Check status of fiscalization service
     *
     * @return mixed
     * @throws Exception
     */
    public function getServiceStatus();


    /**
     * @param array $data
     * @return bool
     */
    public function isReady($data = []);


    /**
     * @param array $data
     * @param mixed $dateFormat Date format to be returned or false/null to return time instead of date
     * @return string|int
     */
    public function lastCheck($data = [], $dateFormat = 'Y-m-d H:i:s');


    /**
     * Prepare a document (receipt) with order information and send it to a fiscalization service
     *
     * @param  Order  $order
     * @param  string $responseURL  URL to process webhooks with information about changes of receipt status
     * @param  int    $docType      Code of operation type: sale, refund, etc.
     * @param  string $paymentType  Payment method: card, cash, etc.
     * @return mixed
     * @throws Exception
     */
    public function sendReceipt($order, $responseURL = null, $docType = null, $paymentType = null);


    /**
     * Check status of document by its ID
     *
     * @param  Order|string $id
     * @return mixed
     * @throws Exception
     */
    public function getReceiptStatus($id);
}