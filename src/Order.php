<?php
namespace Rupay;

use Rupay\Helper\Arr;
use Rupay\Order\Structure;

class Order extends Model
{
    protected $with = ['orderItems', 'payment'];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->checkTransactionId();
        $this->checkHash();
    }


    public function checkTransactionId($forceUpdate = false)
    {
        $transactionId = $this->transaction_id;
        if (empty($transactionId) || $forceUpdate) {
            $orderNumber = preg_replace('|[ а-я%:;.,!?+]|ui', '', $this->order_number);
            $orderNumber = str_replace(['\\', '/', '_'], ['-', '-', '-'], $orderNumber);
            $this->transaction_id = substr($orderNumber . '-' . date('ymdHis') . '-' . rand(10, 99), 0, 30);
            $this->transaction_id = trim($this->transaction_id, '_- ');
        }
        return $this->transaction_id;
    }


    protected function checkHash($forceUpdate = false)
    {
        $hash = $this->hash;
        if (empty($hash) || $forceUpdate) {
            $tid = $this->checkTransactionId($forceUpdate);
            $this->hash = md5($tid);
        }
        return $this->hash;
    }


    /**
     * @param  array|string $criteria
     * @return false|Order
     * @throws Exception
     * @throws \Exception
     */
    public static function findOrder($criteria)
    {
        if (empty($criteria)) {
            throw new Exception('Order number must not be empty');
        }

        $modelOrder = new self();

        if (is_string($criteria)) {
            $modelOrder = $modelOrder->find($criteria);
        } elseif (Arr::is_array($criteria)) {
            foreach ($criteria as $field => $criterion) {
                $modelOrder = $modelOrder->where(
                    $field, '=', $criterion
                );
            }
            $orders = $modelOrder->get();

            if (!$orders->count()) {
                return false;
            }
            if ($orders->count() > 1) {
                throw new Exception('Could not explicitly identify order by given conditions');
            }
            $modelOrder = $orders->first();
        } else {
            throw new Exception('Conditions must be either an associative array or a string');
        }

        if (!$modelOrder->exists) {
            $modelOrder->delete();
            return false;
        }

        return $modelOrder;
    }


    /**
     * @param $number
     * @return false|Order
     * @throws Exception
     * @throws \Exception
     */
    public static function findByNumber($number)
    {
        return self::findOrder(['order_number' => $number]);
    }


    /**
     * @param array  $attributes   Order data
     * @return Order
     * @throws Exception
     */
    public static function createOrder(array $attributes = [])
    {
        if (!empty($attributes['valid_through'])) {
            $attributes['valid_through'] = date('Y-m-d H:i:s', strtotime($attributes['valid_through']));
        } elseif ($lifetime = Config::get('order.link_lifetime')) {
            if (!is_numeric($lifetime)) {
                $lifetime = strtotime($lifetime);
            }
            $attributes['valid_through'] = date('Y-m-d H:i:s', $lifetime);
        }

        Order\Validation::validateOrderData($attributes);
        return new self($attributes);
    }


    /**
     * @param  array  $orderData
     * @param  array  $orderItems
     * @param  bool   $updateIfExists  Whether to update order if it already exists (found by order_number)
     * @param  bool   $updateHash      Whether to update order hash (regenerate payment link) for updated order
     * @param  bool   $updateLifetime  Whether to update payment lifetime of payment page
     * @return Order
     * @throws Exception
     * @throws \Exception
     */
    public static function import($orderData, $orderItems = null, $updateIfExists = true, $updateHash = false, $updateLifetime = true)
    {
        $orderNumber = Arr::get($orderData, 'order_number');
        $order = self::findByNumber($orderNumber);

        if ($order) {

            if ($order->paid) {
                throw new Exception("Order $orderNumber has been paid already, updating is forbidden");
            }

            if (!$updateIfExists) {
                throw new Exception("Order $orderNumber already exists");
            }

            if (!empty($order->payment)) {
                // order data has changed, so mark payment data as not actual
                $order->payment->is_outdated = true;
            }

            foreach (Structure::getOrderFields('public') as $field) {
                $order->$field = Arr::get($orderData, $field, '');
            }

            if (!empty($orderData['valid_through'])) {
                $order->valid_through = date('Y-m-d H:i:s', strtotime($orderData['valid_through']));
            } elseif ($updateLifetime) {
                if ($lifetime = Config::get('order.link_lifetime')) {
                    if (!is_numeric($lifetime)) {
                        $lifetime = strtotime($lifetime);
                    }
                    $order->valid_through = date('Y-m-d H:i:s', $lifetime);
                }
            }

            // updating transaction_id is required if it will be sent to a gateway instead of order number:
            // some gateways (e.g. Sberbank) don't allow reloading orders with the same id
            $order->checkTransactionId(true);

            if ($updateHash) {
                $order->checkHash(true);
            }

            // TODO update without deleting if possible
            $order->orderItems()->delete();

        } else {
            $order = self::createOrder($orderData);
        }

        $order->addItems($orderItems);

        $order->save();

        return $order;
    }


    public function validThrough($asDate = false)
    {
        $val = $this->valid_through;
        if (empty($val)) {
            return true;
        }
        if (is_numeric($val)) {
            return $asDate ? date('Y-m-d H:i:s', $val) : $val;
        }

        $time = strtotime($val);
        return $asDate ? date('Y-m-d H:i:s', $time) : $time;
    }


    public function isValidAt($date = null)
    {
        $validThrough = $this->validThrough();
        if ($validThrough === true) {
            return true;
        }

        if (empty($date)) {
            $date = time();
        } elseif (!is_numeric($date)) {
            $date = strtotime($date);
        }
        return $date <= $validThrough;
    }


    public function __set($key, $value)
    {
        $publicFields = Structure::getOrderFields('public');
        if ($this->paid && in_array($key, $publicFields)) {
            throw new Exception("Updating order data is forbidden: order has been paid already");
        }

        /**
         * @var Payment $payment
         */
        $payment = $this->payment;
        if (!empty($payment) && $payment->exists && in_array($key, $publicFields)) {
            $payment->is_outdated = true;
        }

        parent::__set($key, $value);
    }


    /**
     * {@inheritdoc}
     */
    public function save(array $options = [])
    {
        try {
            parent::save($options);

            /**
             * @var \Rupay\Item $item
             */
            foreach ($this->getItems() as $item) {
                $this->orderItems()->save($item);
            }

            if ($this->payment) {
                $this->payment->save();
            }

            $this->refresh();
        } catch (\Exception $e) {
            throw new Exception($e->getMessage());
        }
    }


    /**
     * Возвращает список заказов из БД, оплаченных за период
     *
     * @param  null $from
     * @param  null $to
     * @return array
     */
    public static function getPaidOrdersByDateRange($from = null, $to = null)
    {
        return self::getOrdersByDateRangeAndStatus($from, $to, Common::ORDER_STATUS_DEPOSITED);
    }


    /**
     * Возвращает список заказов из БД, по которым за указанный период был возврат
     *
     * @param  null $from
     * @param  null $to
     * @return array
     */
    public static function getRefundedOrdersByDateRange($from = null, $to = null)
    {
        return self::getOrdersByDateRangeAndStatus($from, $to, Common::ORDER_STATUS_REFUNDED);
    }


    /**
     * Возвращает список заказов из БД, у которых указанный статус в заданном диапазоне.
     * Например, оплаченные за период или те, по которым были возвраты.
     * После этого следует проверить статус каждого найденного заказа на шлюзе,
     * чтобы подтвердить оплату (и исключить возможность смены его статуса в БД вручную)
     *
     * @param  null $from
     * @param  null $to
     * @param  int  $status
     * @return array
     */
    public static function getOrdersByDateRangeAndStatus($from = null, $to = null, $status = Common::ORDER_STATUS_DEPOSITED)
    {
        $format = 'Y-m-d H:i:s';
        $from = date($format, $from ? strtotime($from) : null);

        if (empty($to)) {
            $to = time();
        } elseif (preg_match('|^\d{4}-?\d{2}-?\d{2}$|', $to)) {
            $to = strtotime($to . ' 23:59:59');
        } else {
            $to = strtotime($to);
        }
        $to = date($format, $to);

        switch ($status) {
            case Common::ORDER_STATUS_REFUNDED:
                $field = 'refunded';
                break;
            default:
                $field = 'paid';
        }

        $model = new self();
        return $model->whereBetween($field, [$from, $to])->get();
    }


    public function getItems()
    {
        return $this->orderItems ?: [];
    }


    public function addItems($items)
    {
        if (!empty($items)) {
            foreach ($items as $item) {
                $this->addItem($item);
            }
        }
        return $this;
    }

    public function addItem($item)
    {
        Order\Validation::validateItem($item);
        $model = new Item($item);
        $this->orderItems->add($model);
        return $this;
    }


    public function getSum()
    {
        $sum = 0;

        /**
         * @var \Rupay\Item $item
         */
        foreach ($this->getItems() as $item) {
            $sum += $item->getCost();
        }

        return $sum;
    }


    public function orderItems()
    {
        return $this->hasMany('\Rupay\Item', 'order_id');
    }

    public function payment()
    {
        return $this->hasOne('\Rupay\Payment', 'order_id');
    }
}