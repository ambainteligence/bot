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
        $vol = $this->getCandleBySign($candles, 'volume');

        return [
            'close' => $close,
            'high'  => $high,
            'low'   => $low,
            'open'  => $open,
            'volume' => $vol
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

    /**
     * @param        $candles
     * @param string $strategyName
     * @param int    $previousTimes
     * @param string $text
     * @return mixed get result of a strategy at a position
     */
    public function getResultOfStrategy(&$candles, $strategyName = 'phuongb_bowhead_macd', $previousTimes = 0, &$text = '')
    {
        // get previous data
        for ($time = 0; $time < $previousTimes; $time++) {
            array_pop($candles);
        }

        $data = $this->changeCandlesToData($candles);
        return $this->{$strategyName}('', $data, false, $text);
    }

    /**
     * @param        $candles
     * @param string $strategyName
     * @param int    $previousTimes
     * @param string $text
     * @return int   get results of Strategy . 0 => not same, -1 => false, 1 => true
     */
    public function getSameResultsOfStrategy(&$candles, $strategyName = 'phuongb_bowhead_macd', $previousTimes = 0, &$text = '')
    {
        $begin = 0;
        $result = 1;

        // get previous data
        for ($time = 0; $time < $previousTimes; $time++) {
            array_pop($candles);
            $data = $this->changeCandlesToData($candles);
            $result = $this->{$strategyName}('', $data, false, $text);
            // init begin
            $begin = ($time == 0) ? $result : $begin;

            if ($begin != $result) {
                $text .= ' , can not buy because previous candles do not same values';
                return 0;
            }
        }

        $endCandle = end($candles);
        $endCandleTime = $this->changeMillisecondToTimeString($endCandle['openTime'], 'H:i');

        $statusResult = ($result === 1) ? self::BUY : self::SELL;
        $text .= ', Previous ' . self::PREVIOUS_CANDLES . ' candle: ' . $statusResult . ' at ' . $endCandleTime . ' | ';

        return $result;
    }

    public function comparePriceSMA($price, $sma)
    {
        $result = $sma - $price;
        return ($result >= 0) ? 1 : -1;
    }



}
