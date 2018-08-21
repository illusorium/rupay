<?php
namespace Rupay;


use Rupay\Order\Structure;

class Item extends Model
{
    public $timestamps = false;

    public function order()
    {
        return $this->belongsTo('\Rupay\Order', 'order_id', 'id');
    }


    public function getCost()
    {
        return round($this->price * $this->quantity, 2);
    }


    public function __set($key, $value)
    {
        if ($this->order && $this->order->paid && in_array($key, Structure::getItemFields('public'))) {
            throw new Exception("Updating item of order that has been paid already is forbidden");
        }
        parent::__set($key, $value);
    }
}