<?php
namespace Rupay\Order;


use Rupay\Exception;
use Rupay\Helper\Arr;

class Validation
{
    /**
     * @param  array $data
     * @throws Exception
     */
    public static function validateOrderData(&$data)
    {
        if (!Arr::is_array($data)) {
            throw new Exception('Order data must be an array');
        }
        foreach (['order_number'] as $field) {
            if (empty($data[$field])) {
                throw new Exception("$field is required and must not be empty");
            }
        }
        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Email {$data['email']} is not valid");
        }
        if (!empty($data['transaction_id']) && !preg_match('|^[\d[:alpha:]]+$|ui', $data['transaction_id'])) {
            throw new Exception("Transaction ID must consist only of letters and digits");
        }
    }


    /**
     * @param  array $items
     * @throws Exception
     */
    public static function validateItems(&$items = null)
    {
        if (!is_null($items) && !Arr::is_array($items)) {
            throw new Exception('Order items must be an array');
        }

        foreach ($items as $i => &$item) {
            try {
                self::validateItem($item);
            } catch (Exception $e) {
                throw new Exception("Item #$i - " . $e->getMessage());
            }
        }
    }


    public static function validateItem(&$item)
    {
        $item['price'] = str_replace(',', '.', $item['price']);
        $item['quantity'] = str_replace(',', '.', $item['quantity']);

        foreach (['price', 'quantity'] as $field) {
            if (!is_numeric($item[$field]) || floatval($item[$field]) <= 0) {
                throw new Exception("$field must be greater than 0");
            }
        }

        $cost = $item['price'] * $item['quantity'];
        if ((string) round($cost, 2) !== (string) $cost) {
            throw new Exception(
                "Invalid item cost: {$item['quantity']} * {$item['price']} = $cost - too long fractional part"
            );
        }
    }

}