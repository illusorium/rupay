<?php
if (PHP_SAPI === 'cli') {
    die('This script must be executed from browser' . PHP_EOL);
}

/*
 * Example of how to handle incoming request (callback) from payment system.
 * Such requests usually contain information about transaction status
 */

require '../vendor/autoload.php';

Rupay\Config::load('../rupay.yml');

$gateway = Rupay\Gateway::create();
$order = $gateway->findOrderByRequestData();

if ($order) {
    if ($gateway->checkSignature($order)) {

        if ($status = $gateway->getCallbackOperationStatus()) {
            if ($status['operation'] = $gateway::PAYMENT_STATUS_DEPOSITED && !empty($status['status'])) {
                $order->paid = date('Y-m-d H:i:s');
                $order->save();

                /*
                 * Отправка чека на сервер фискализации
                 *
                 * TODO $till->assignedToGateway()
                 * если касса привязана к шлюзу (напрмер, Сбербанк + АТОЛ),
                 * шлюз отправляет чеки автоматически, отправка чека вручную не требуется
                 */
//                $till = Rupay\Till::create();
//                $till->sendReceipt($order);
            }
        }
        $response = $gateway->setResponseSuccess();
    } else {
        $response = $gateway->setResponseFail();
    }
} else {
    $response = $gateway->setResponse('Page not found', 404);
}

Rupay\Helper\Response::render($response);