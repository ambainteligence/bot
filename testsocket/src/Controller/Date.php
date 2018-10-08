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
    public function changeTimeStringToMilliSecond($time) {
        return strtotime($time) * 1000;
    }

    public function changeMillisecondToTimeString($millisecond) {
        $time = $millisecond / 1000;
        return date('d/m/Y', $time);
    }

    public function changeTimestampToTimeString($timestamp, $format = 'd/m/Y') {
        return date($format, $timestamp);
    }

}
