<?php
namespace Rupay;


use Rupay\Helper\Arr;
use Rupay\Order\Structure;

class Payment extends Model
{
    public function order()
    {
        return $this->belongsTo('\Rupay\Order', 'order_id');
    }


    /**
     * @param  array|string $criteria
     * @return false|Payment
     * @throws Exception
     * @throws \Exception
     */
    public static function findPayment($criteria)
    {
        if (empty($criteria)) {
            throw new Exception('Order number must not be empty');
        }

        $modelPayment = new self();

        if (is_string($criteria)) {
            $modelPayment = $modelPayment->find($criteria);
        } elseif (Arr::is_array($criteria)) {
            foreach ($criteria as $field => $criterion) {
                $modelPayment = $modelPayment->where(
                    $field, '=', $criterion
                );
            }
            $orders = $modelPayment->get();

            if (!$orders->count()) {
                return false;
            }
            if ($orders->count() > 1) {
                throw new Exception('Could not explicitly identify order by given conditions');
            }
            $modelPayment = $orders->first();
        } else {
            throw new Exception('Conditions must be either an associative array or a string');
        }

        if (!$modelPayment->exists) {
            $modelPayment->delete();
            return false;
        }

        return $modelPayment;
    }


    public function __set($key, $value)
    {
        if ($this->order && $this->order->paid && in_array($key, Structure::getPaymentFields('public'))) {
            throw new Exception("Updating payment data of order that has been paid already is forbidden");
        }
        parent::__set($key, $value);
    }
}