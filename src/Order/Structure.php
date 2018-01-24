<?php
namespace Rupay\Order;


use Rupay\Helper\Arr;

class Structure
{
    /**
     * Tables structure
     *
     * @var array
     */
    protected static $structure = [
        'order' => [
            'table'  => 'orders',
            'fields' => [
                'private' => [
                    'id', 'transaction_id', 'hash', 'created_at', 'updated_at',
                    'paid', 'fiscalized'
                ],
                'public' => [
                    'order_number', 'valid_through',
                    'buyer', 'email', 'phone', 'address',
                    'passport', 'inn', 'comment'
                ]
            ]
        ],
        'item' => [
            'table'  => 'orders_items',
            'fields' => [
                'private' => [
                    'id', 'order_id'
                ],
                'public' => [
                    'product', 'price', 'quantity', 'units'
                ]
            ]
        ],
        'payment' => [
            'table' => 'payments',
            'fields' => [
                'private' => [
                    'id', 'order_id', 'gateway', 'created_at', 'updated_at'
                ],
                'public' => [
                    'is_outdated', 'payment_url', 'gateway_order_id', 'data'
                ]
            ]
        ]
    ];


    /**
     * @return array
     */
    public static function get()
    {
        return self::$structure;
    }


    protected static function getStructureItem($path)
    {
        return Arr::path(self::get(), $path);
    }


    protected static function getField($key, $field)
    {
        $result = (array) self::getStructureItem("$key.fields.*.$field");
        return Arr::get($result, 0);
    }


    protected static function getFields($key, $type = '*')
    {
        $result = [];
        if (in_array($type, ['public', 'all', '*'])) {
            $result = (array) self::getStructureItem("$key.fields.public.*");
        }
        if (in_array($type, ['private', 'all', '*'])) {
            $result += (array) self::getStructureItem("$key.fields.private.*");
        }
        return $result;
    }


    public static function getOrderFields($type = '*')
    {
        return self::getFields('order', $type);
    }


    public static function getItemFields($type = '*')
    {
        return self::getFields('item', $type);
    }


    public static function getPaymentFields($type = '*')
    {
        return self::getFields('payment', $type);
    }


    public static function getOrdersTable()
    {
        return self::getStructureItem('order.table');
    }


    public static function getOrdersItemsTable()
    {
        return self::getStructureItem('item.table');
    }


    public static function getPaymentsTable()
    {
        return self::getStructureItem('payment.table');
    }
}