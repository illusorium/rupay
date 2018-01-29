<?php
if (PHP_SAPI === 'cli') {
    die('This script must be executed from browser' . PHP_EOL);
}

require '../vendor/autoload.php';

Rupay\Config::load('../rupay.yml');

if (empty($_REQUEST['hash'])) {
    Rupay\Helper\Response::render(
        new \GuzzleHttp\Psr7\Response(400, [], 'Order hash is empty')
    );
    return;
}
$hash = $_REQUEST['hash'];

### Find order by hash (e.g. when buyer visits payment page)
$order = Rupay\Order::findOrder(['hash' => $hash]);

if (!$order) {
    Rupay\Helper\Response::render(
        new \GuzzleHttp\Psr7\Response(404, [], 'Order not found')
    );
    return;
}

if (!$order->isValidAt()) {
    Rupay\Helper\Response::render(
        new \GuzzleHttp\Psr7\Response(403, [], 'Order is outdated')
    );
    return;
}

### Gateway object
$gateway = Rupay\Gateway::create();

echo '<pre>';

/**
 * Example of how to change item
 *
 * @var \Rupay\Item $item
 */
//foreach ($order->getItems() as $item) {
//    $item->product = 'item ' . date('Ymd-His');
//    $item->save();
//}

echo '<strong>Order number:</strong> '      . $order->order_number . PHP_EOL;
echo '<strong>Order sum:</strong> '         . $order->getSum() . PHP_EOL;
echo '<strong>Transaction ID:</strong> '    . $order->transaction_id . PHP_EOL;
echo '<strong>Payment link hash:</strong> ' . $order->hash . PHP_EOL;
//echo '<strong>Order signature:</strong> '   . $gateway->sign($order) . PHP_EOL;
echo '<strong>Payment page:</strong> '      . $gateway->getPaymentUrl($order) . PHP_EOL;
echo '<strong>Order data:</strong> '        . print_r($order->toArray(), true);
echo '<strong>Payment data:</strong> '      . print_r($gateway->getPaymentStatus($order), true);

echo '</pre>';
