<?php
namespace Rupay\Helper;


use Rupay\Exception;

class Order
{
    /**
     * @param  string $template
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public static function getOrderByUrlTemplate($template)
    {
        if (!$urlTemplate = parse_url($template)) {
            throw new Exception("Could not parse responseURL template");
        }

        /**
         * По шаблону responseURL определяем, где в REQUEST_URI нужно искать идентификатор заказа
         * и какому полю заказа он соответствует
         */

        // Query string
        if ($q = Arr::get($urlTemplate, 'query')) {
            /**
             * TODO проверка домена и REQUEST_URI. Сейчас в этом блоке анализируются только GET-параметры.
             * Например, если в конфиге ResponseURL вида https://example.org/path/to/callback.php?param={{hash}},
             * то здесь будет осуществляться только поиск по $_GET, даже для URL
             * https://another-example.com/custom/path?param=12345
             */
            foreach (explode('&', $q) as $i => $param) {
                $param = explode('=', $param);
                if (count($param) === 2) {

                    $key = $param[0];
                    $value = $param[1];

                    if (!empty($_GET[$key]) && preg_match('|^\{\{(.+?)\}\}$|', $value, $match)) {
                        /*
                         * $match[1] - название поля в БД, по которому будем искать заказ
                         * Например, если шаблон '/path/to/script.php?order_id={{transaction_id}}',
                         * ищем ['transaction_id' => $_GET['order_id']]
                         */
                        return \Rupay\Order::findOrder([
                            $match[1] => $_GET[$key]
                        ]);
                    }
                } elseif (preg_match('|^\{\{(.+?)\}\}$|', $param[0], $match)) {
                    /*
                     * Для шаблонов вида 'path/to/script.php?{{hash}}':
                     * ищем по названию GET-параметра, а не по значению
                     */
                    $keys = array_keys($_GET);
                    return \Rupay\Order::findOrder([
                        $match[1] => $keys[$i]
                    ]);
                }
            }
        }

        // Request path
        if (preg_match('|/\{\{(.+?)\}\}|ui', Arr::get($urlTemplate, 'path', ''), $match)) {
            /**
             * Для шаблонов вида /callback/fiscal/{{hash}} "совмещаем" шаблон и REQUEST_URI
             * и получаем нужное значение из последнего.
             *
             * Например, если responseURL /payment/callbackURL/{{transaction_id}},
             * а REQUEST_URI /payment/callbackURL/12345,
             * ищем заказ с transaction_id = 12345
             */
            $uri = ltrim(parse_url(Arr::get($_SERVER, 'REQUEST_URI', ''), PHP_URL_PATH), '/');
            if (!empty($uri)) {

                $path = ltrim($urlTemplate['path'], '/ ');

                $pos = strpos($path, '{{');
                if ($pos !== false) {

                    $value = substr($uri, $pos);
                    if ($to = strpos($value, '/')) {
                        $value = substr($value, 0, $to);
                    }

                    return \Rupay\Order::findOrder([
                        $match[1] => $value
                    ]);
                }
            }
        }

        // по шаблону не удалось определить, откуда в $_SERVER или $_GET брать параметр заказа
        return false;
    }


    /**
     * Заполнение шаблона URL заданным параметром заказа
     *
     * @param  string        $template
     * @param  \Rupay\Order  $order
     * @return string
     */
    public static function fillResponseURL($template, $order)
    {
        if (preg_match('|\{\{(.+?)\}\}|', $template, $match)) {
            $field = strtolower($match[1]);
            if (strpos($field, 'transaction') === 0) {
                $value = $order->transaction_id;
            } elseif (strpos($field, 'hash') === 0) {
                $value = $order->hash;
            } elseif (in_array($field, ['order', 'order_id', 'order_number'])) {
                $value = $order->order_number;
            } else {
                $value = (string) $order->$field;
            }
            return str_replace($match[0], $value, $template);
        }
        return $template;
    }

}