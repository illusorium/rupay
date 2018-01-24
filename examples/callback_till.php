<?php
if (PHP_SAPI === 'cli') {
    die('This script must be executed from browser' . PHP_EOL);
}

/*
 * Example of how to handle incoming request (callback) from fiscalization service.
 */

require '../vendor/autoload.php';

Rupay\Config::load('../rupay.yml');

$till = Rupay\Till::create();
$order = $till->findOrderByRequestData();

if (!$order) {
    Rupay\Helper\Response::render(
        new \GuzzleHttp\Psr7\Response(404, [], 'Order not found')
    );
    return;
}

$order->fiscalized = date('Y-m-d H:i:s');
$order->save();

Rupay\Helper\Response::render(
    $till->setResponse('SUCCESS')
);