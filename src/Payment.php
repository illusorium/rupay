<?php
namespace Rupay;


use Rupay\Order\Structure;

class Payment extends Model
{
    public function order()
    {
        return $this->belongsTo('\Rupay\Order', 'order_id');
    }


    public function __set($key, $value)
    {
        if ($this->order && $this->order->paid && in_array($key, Structure::getPaymentFields('public'))) {
            throw new Exception("Updating payment data of order that has been paid already is forbidden");
        }
        parent::__set($key, $value);
    }
}