<?php
namespace Rupay;


abstract class Gateway extends Common implements Gateway\GatewayInterface, CallbackInterface
{
    /**
     * Indicates that order should be registered beforehand at the gateway.
     * Some gateways generate unique payment link for each order,
     * so it make sense to get that link before buyer visits payment page.
     *
     * @var bool
     */
    protected $preregisterOrder = false;


    /**
     * @return bool
     */
    public final function ordersNeedPreregistration()
    {
        return $this->preregisterOrder;
    }


    /**
     * @param  string    $gatewayClass
     * @return Gateway
     * @throws Exception
     */
    public static function create($gatewayClass = null)
    {
        if (empty($gatewayClass)) {
            $gatewayClass = Config::get('default.gateway');
        }
        return parent::getObject($gatewayClass, 'gateway');
    }


    /**
     * @param  Order $order
     * @param  bool  $createIfNotExists
     * @return Payment|false
     * @throws Exception
     */
    public function getPayment($order, $createIfNotExists = true)
    {
        /**
         * @var Payment $payment
         */
        if ($payment = $order->payments()->where('gateway', $this->getKey())->first()) {
//            if ($payment->gateway !== $this->getKey()) {
//                throw new Exception("Order is assigned to another gateway");
//            }
            return $payment;
        }

        if ($createIfNotExists) {
            $payment = new Payment();
            $payment->gateway = $this->getKey();
            $payment->order()->associate($order);
            $payment->save();
            return $payment;
        } else {
            return false;
        }
    }


    /**
     * Process outdated payment data (e.g. after order has been updated)
     * In children classes this method may send requests declining outdated orders (if the gateway's API allows it)
     *
     * @param  Payment $payment
     * @throws \Exception
     */
    protected function processOutdatedPaymentData($payment)
    {
        foreach ($payment->getFillable() as $field) {
            $payment->$field = '';
        }
        $payment->save();
    }


    /**
     * @param  Payment $payment
     * @param  array   $info
     * @param  string  $paymentUrl
     * @param  string  $gatewayOrderId
     * @throws \Exception
     */
    protected function updatePaymentData($payment, $info, $paymentUrl = null, $gatewayOrderId = null)
    {
        $payment->gateway = $this->getKey();
        $payment->data = json_encode($info);
        if (!empty($paymentUrl)) {
            $payment->payment_url = $paymentUrl;
        }
        if (!empty($gatewayOrderId)) {
            $payment->gateway_order_id = $gatewayOrderId;
        }
        $payment->save();
    }
}