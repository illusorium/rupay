<?php
if (PHP_SAPI === 'cli') {
    die('This script must be executed from browser' . PHP_EOL);
}

require '../vendor/autoload.php';

Rupay\Config::load('../rupay.yml');

use Rupay\Helper\Response as R;

$data = $_REQUEST;

if (empty($data['order_number'])/* || empty($data['items'])*/) {
    R::render(
        R::set('Bad Request', 400)
    );
    return;
}

$items = Rupay\Helper\Arr::get($data, 'items', []);
if (!is_array($items)) {
    R::render(
        R::set('Invalid order items', 400)
    );
    return;
}
if (!empty($data['items'])) {
    unset($data['items']);
}

$order = Rupay\Order::import($data, $items);

$gateway = Rupay\Gateway::create();
if (!empty($items) && $gateway->ordersNeedPreregistration()) {
    $gateway->registerOrder(
        $order,
        $order->transaction_id,
        "Счет на оплату № {$order->order_number}"
    );
}

R::render(
    R::set("Payment link hash: {$order->hash}", 200)
);
