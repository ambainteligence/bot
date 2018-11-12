<?php

namespace App\Controller;

trait ReportTrail
{
    use Date;
    public function reportPriceResultTime($price, $time, $prevCandles = '')
    {
//        $result = $this->changeResultToString($result);
        $result = '';
        $time = $this->changeMillisecondToTimeString($time, 'H:i');
        return "Current price: {$price}  {$result} {$prevCandles} {$time}";
    }

    public function changeResultToString($result)
    {
        return ($result === 1) ? 'BUY' : 'SELL';
    }
}
