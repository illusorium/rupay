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
            if (!empty($status['status'])) {
                // successful operation

                if ($status['operation'] === $gateway::ORDER_STATUS_DEPOSITED) {
                    $order->paid = date('Y-m-d H:i:s');
                    $order->save();

                    // Отправка чека на сервер фискализации ($till->assignedToGateway())

                } elseif ($status['operation'] === $gateway::ORDER_STATUS_REFUNDED) {
                    $order->refunded = date('Y-m-d H:i:s');
                    $order->save();

                    // Отправка чека возврата на сервер фискализации ($till->assignedToGateway())
                } else {
                    // another operation
                }
            } else {
                // unsuccessful operation (e.g. insufficient funds on a card)
            }
        } else {
            // unknown operation
        }
        $response = $gateway->setResponseSuccess();
    } else {
        // invalid data - we're not sure that sender is real gateway
        $response = $gateway->setResponseFail();
    }
} else {
    $response = $gateway->setResponse('Page not found', 404);
}

Rupay\Helper\Response::render($response);