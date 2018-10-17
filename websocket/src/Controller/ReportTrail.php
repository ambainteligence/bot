<?php

namespace App\Controller;

trait ReportTrail
{
    use Date;
    public function reportPriceResultTime($price, $result, $time)
    {
        $result = $this->changeResultToString($result);
        $time = $this->changeMillisecondToTimeString($time, 'H:i');
        return "Current price: {$price}  {$result}  {$time}";
    }

    public function changeResultToString($result)
    {
        return ($result === 1) ? 'BUY' : 'SELL';
    }
}
