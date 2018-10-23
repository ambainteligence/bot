<?php

namespace App\Controller;

trait CandlesTrait
{

    public function changeCandlesToData($candles)
    {
        $close = $this->getCandleBySign($candles, 'close');
        $high = $this->getCandleBySign($candles, 'high');
        $low = $this->getCandleBySign($candles, 'low');
        $open = $this->getCandleBySign($candles, 'open');

        return [
            'close' => $close,
            'high'  => $high,
            'low'   => $low,
            'open'  => $open
        ];
    }

    public function getCandleBySign($candles, $sign)
    {
        $results = [];
        foreach ($candles as $candle) {
            $results[] = $candle[$sign];
        }

        return $results;
    }

    public function getResultOfStrategy(&$candles, $strategyName = 'phuongb_bowhead_macd', $previousTimes = 0, &$text = '')
    {
        // get previous data
        for ($time = 0; $time < $previousTimes; $time++) {
            array_pop($candles);
        }

        $data = $this->changeCandlesToData($candles);

        return $this->{$strategyName}('', $data, false, $text);
    }

    public function comparePriceSMA($price, $sma)
    {
        $result = $sma - $price;
        return ($result >= 0) ? 1 : -1;
    }



}
