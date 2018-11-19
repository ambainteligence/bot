<?php

namespace App\Controller;

trait Date
{

    /**
     * eg: $time = -5 day
     * $result = 1538157600000
     * @param $time
     * @return false|int
     */
    public function changeTimeStringToMilliSecond($strTime, $format = 'd/m/Y') {
        $timeStamp = \DateTime::createFromFormat($format, $strTime)->getTimestamp();
        return $timeStamp * 1000;
    }

    public function changeMillisecondToTimeString($millisecond, $format = 'd/m/Y') {
        $time = $millisecond / 1000;
        return date($format, $time);
    }

    public function changeTimestampToTimeString($timestamp, $format = 'd/m/Y') {
        return date($format, $timestamp);
    }

}
