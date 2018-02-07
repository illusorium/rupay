<?php
namespace Rupay\Helper;


/**
 * Класс для облегчения взаимодествия с платежными шлюзами и онлайн-кассами/сервисами фискализации по 54-ФЗ
 */
class FZ54
{
    const VAT_18     = 1102; // НДС 18%
    const VAT_10     = 1103; // НДС 10%
    const VAT_0      = 1104; // НДС 0%
    const VAT_NONE   = 1105; // НДС не облагается
    const VAT_18_118 = 1106; // НДС 18/118
    const VAT_10_110 = 1107; // НДС 10/110


    const TAX_OSN    = 0; // Общая система налогообложения
    const TAX_USN_D  = 1; // Упрощенная система налогообложения (доходы)
    const TAX_USN_DR = 2; // Упрощенная система налогообложения (доходы минус расходы)
    const TAX_ENVD   = 3; // Единый налог на вмененный доход
    const TAX_ESN    = 4; // Единый сельскохозяйственный налог
    const TAX_PSN    = 5; // Патентная система налогообложения


    public static $taxSystems = [
        self::TAX_OSN,
        self::TAX_USN_D,
        self::TAX_USN_DR,
        self::TAX_ENVD,
        self::TAX_ESN,
        self::TAX_PSN
    ];


    public static $vatTags = [
        self::VAT_18,
        self::VAT_10,
        self::VAT_0,
        self::VAT_NONE,
        self::VAT_18_118,
        self::VAT_10_110
    ];
}