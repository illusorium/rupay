<?php
namespace Rupay\Helper;


class FZ54
{
    /**
     * Codes of VAT tags in accordance with Federal Law of the Russian Federation No. 54-FZ
     *
     * @var array
     */
    public static $vatTags = [
        'vat18'     => 1102, // НДС 18%
        'vat10'     => 1103, // НДС 10%
        'vat0'      => 1104, // НДС 0%
        'vat_none'  => 1105, // НДС не облагается
        'vat18_118' => 1106, // НДС 18/118
        'vat10_110' => 1107  // НДС 10/110
    ];
}