<?php
namespace Rupay\Helper;

/**
 * Array helper
 * Methods are basically taken from Arr class of deprecated Kohana framework
 *
 * Class Arr
 * @package Rupay\Helper
 */
class Arr
{
    /**
     * @var string Delimiter for path() method
     */
    public static $delimiter = '.';


    /**
     * Retrieve a single key from an array. If the key does not exist in the
     * array, the default value will be returned instead.
     *
     *     // Get the value "username" from $_POST, if it exists
     *     $username = Arr::get($_POST, 'username');
     *
     *     // Get the value "sorting" from $_GET, if it exists
     *     $sorting = Arr::get($_GET, 'sorting');
     *
     * @param   array   $array      array to extract from
     * @param   mixed   $key        key name
     * @param   mixed   $default    default value
     * @return  mixed
     */
    public static function get($array, $key, $default = null)
    {
        if (self::is_array($key)) {
            $result = [];
            foreach ($key as $item) {
                $result[$item] = Arr::get($array, $item, $default);
            }
            return $result;
        }
        if ($array instanceof \ArrayObject) {
            return $array->offsetExists($key) ? $array->offsetGet($key) : $default;
        } else {
            return isset($array[$key]) ? $array[$key] : $default;
        }
    }


    /**
     * Test if given value is an array or array-like object
     *
     * @param   mixed   $item  value to check
     * @return  boolean
     */
    public static function is_array($item)
    {
        return \is_array($item) || is_object($item) && $item instanceof \Traversable;
    }


    /**
     * Gets a value from an array using a $delimiter-separated path
     *
     *     // Get the value of $array['foo']['bar']
     *     $value = Arr::path($array, 'foo.bar');
     *
     * Using a wildcard "*" will search intermediate arrays and return an array.
     *
     *     // Get the values of "color" in theme
     *     $colors = Arr::path($array, 'theme.*.color');
     *
     *     // Using an array of keys
     *     $colors = Arr::path($array, array('theme', '*', 'color'));
     *
     * @param   array   $array      array to search
     * @param   mixed   $path       key path string (delimiter separated) or array of keys
     * @param   mixed   $default    default value if the path is not set
     * @param   string  $delimiter  key path delimiter
     * @return  mixed
     */
    public static function path($array, $path, $default = null, $delimiter = null)
    {
        if (!Arr::is_array($array)) {
            return $default;
        }

        if (is_array($path)) {
            // The path has already been separated into keys
            $keys = $path;
        } else {

            if (array_key_exists($path, $array)) {
                return $array[$path];
            }

            if ($delimiter === null) {
                // Use the default delimiter
                $delimiter = Arr::$delimiter;
            }

            // Remove starting delimiters and spaces
            $path = ltrim($path, "{$delimiter} ");

            // Remove ending delimiters, spaces, and wildcards
            $path = rtrim($path, "{$delimiter} *");

            // Split the keys by delimiter
            $keys = explode($delimiter, $path);
        }

        do {
            $key = array_shift($keys);

            if (ctype_digit($key)) {
                $key = (int) $key;
            }

            if (isset($array[$key])) {

                if ($keys) {
                    if (Arr::is_array($array[$key])) {
                        // Dig down into the next part of the path
                        $array = $array[$key];
                    } else {
                        // Unable to dig deeper
                        break;
                    }
                } else {
                    // Found the path requested
                    return $array[$key];
                }

            } elseif ($key === '*') {
                // Handle wildcards

                $values = [];
                foreach ($array as $arr) {
                    if ($value = Arr::path($arr, implode('.', $keys))) {
                        $values[] = $value;
                    }
                }

                if ($values) {
                    // Found the values requested
                    return $values;
                } else {
                    // Unable to dig deeper
                    break;
                }
            } else {
                // Unable to dig deeper
                break;
            }

        } while ($keys);

        // Unable to find the value requested
        return $default;
    }
}