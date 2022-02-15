<?php
namespace Rupay\Helper;


use Carbon\Carbon;
use Rupay\Exception;

class Payment
{
    /**
     * @return mixed
     * @throws Exception
     * @throws \Exception
     */
    public static function getPaymentsOlderThan3DaysForGateway($gateway)
    {
        return \Rupay\Payment::query()
            ->where('created_at', '<', Carbon::now()->subDays(3))
            ->where('gateway', '=', $gateway->getKey())
            ->whereHas('order', function ($q){
                $q->where('paid', null);
            })
            ->get();
    }

    public static function getPaymentsWithRecentActivity($gateway, $hours, $ignoreId = [])
    {
        return \Rupay\Payment::query()
            ->where('created_at', '<=', Carbon::now()->subDays(3))
            ->where('updated_at', '>=', Carbon::now()->subHours($hours))
            ->where('gateway', '=', $gateway->getKey())
            ->whereNotIn('id', $ignoreId)
            ->get();
    }
}
