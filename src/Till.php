<?php
namespace Rupay;

abstract class Till extends Common implements Till\TillInterface, CallbackInterface
{
    /**
     * @var string
     */
    protected $baseURI;

    /**
     * @param  string $tillClass
     * @return Till
     * @throws Exception
     */
    public static function create($tillClass = null)
    {
        if (empty($tillClass)) {
            $tillClass = Config::get('default.till');
        }
        return parent::getObject($tillClass, 'till');
    }
}