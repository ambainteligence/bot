<?php

namespace App\Controller;

trait CandlesTrait
{

    public function changeCandlesToData($candles)
    {
        $close = $this->getCandleBySign($candles, 'close');
        $high = $this->getCandleBySign($candles, 'high');
        $low = $this->getCandleBySign($candles, 'low');

        return [
            'close' => $close,
            'high'  => $high,
            'low'   => $low,
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

    public function getResultOfStrategy($candles, $strategyName = 'phuongb_bowhead_macd', $previousTimes = 0, &$text = '')
    {
        // get previous data
        for ($time = 0; $time < $previousTimes; $time++) {
            array_pop($candles);
        }

        $data = $this->changeCandlesToData($candles);

        return $this->{$strategyName}('', $data, false, $text);
    }



}
