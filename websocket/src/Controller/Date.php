<?php

namespace App\Controller;

trait Date
{

    /**
     * eg: $strTime = 08:12:2018 22:08, $format = 'd/m/Y H:i'
     * $result = 1538157600000
     * @param $strTime
     * @param $format
     * @return false|int
     */
    public function changeTimeStringToMilliSecond($strTime, $format = 'd/m/Y') {
        $timeStamp = \DateTime::createFromFormat($format, $strTime)->getTimestamp();
        return $timeStamp * 1000;
    }

    /**
     * @param        $millisecond = 1538157600000
     * @param string $format = 'd/m/Y H:i'
     * @return false|string = 08:12:2018 22:08
     */
    public function changeMillisecondToTimeString($millisecond, $format = 'd/m/Y') {
        $time = $millisecond / 1000;
        return date($format, $time);
    }

    public function changeTimestampToTimeString($timestamp, $format = 'd/m/Y') {
        return date($format, $timestamp);
    }

    public function reduceMilliSecondFromMinute($milliSecond, $minutes)
    {
        return ($milliSecond - ($minutes * 60 * 1000));
    }

}
