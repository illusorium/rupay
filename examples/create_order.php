<?php
if (PHP_SAPI === 'cli') {
    die('This script must be executed from browser' . PHP_EOL);
}

require '../vendor/autoload.php';

Rupay\Config::load('../rupay.yml');

if (empty($_REQUEST['order_number'])) {
    Rupay\Helper\Response::render(
        new \GuzzleHttp\Psr7\Response(400, [], 'Order number is required')
    );
    return;
}

$check = \Rupay\Order::findByNumber($_REQUEST['order_number']);
if ($check) {
    Rupay\Helper\Response::render(
        new \GuzzleHttp\Psr7\Response(400, [], "Order {$_REQUEST['order_number']} already exists")
    );
    return;
}

### Create order from raw data
$order = Rupay\Order::createOrder(
    [
        'order_number' => $_REQUEST['order_number'],
        'buyer' => 'test',
        'email' => 'test@example.org'
    ]
);
$order->addItems([
    [
        'product' => 'item1',
        'price' => 1.99,
        'quantity' => 3,
        'units' => 'pcs'
    ],
    [
        'product' => 'item2',
        'price' => 59.9,
        'quantity' => 0.1,
        'units' => 'l'
    ]
]);

$order->save();

### Gateway object
$gateway = Rupay\Gateway::create();

echo '<pre>';

echo '<strong>Order number:</strong> '      . $order->order_number . PHP_EOL;
echo '<strong>Order sum:</strong> '         . $order->getSum() . PHP_EOL;
echo '<strong>Transaction ID:</strong> '    . $order->transaction_id . PHP_EOL;
echo '<strong>Payment link hash:</strong> ' . $order->hash . PHP_EOL;
echo '<strong>Order signature:</strong> '   . $gateway->sign($order) . PHP_EOL;
echo '<strong>Order data:</strong> '        . print_r($order->toArray(), true);

echo '</pre>';

//$order->delete();