<?php
/**
 * Created by PhpStorm.
 * User: joeldg
 * Date: 4/13/17
 * Time: 6:26 PM
 */
namespace App\Controller;

use App\Controller\OHLC;
use Bowhead\Util\Util;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class CustomIndicators
{
    use OHLC;

    public function phuongMfis($pair='BTC/USD', $data=null, $period=14, $quantity = 3)
    {
        if (empty($data)) {
            $data = $this->getRecentData($pair);
        }

        $mfi = trader_mfi($data['high'], $data['low'], $data['close'], $data['volume'], $period);
        $queue = [];
        for($count = 0; $count < $quantity; $count++) {
            array_unshift($queue, array_pop($mfi));
        }
        return $queue;
    }

}
