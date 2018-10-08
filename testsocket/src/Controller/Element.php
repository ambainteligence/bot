<?php

namespace App\Controller;


trait Element
{

    public function splitElement($array, $offset)
    {
        $rootCandles = array_splice($array, 0, $offset);
        return [
            'part_1' => $rootCandles,
            'part_2' => $array
        ];
    }

    public function moveElement(&$from, &$to, $quantity)
    {
        $result = array_splice( $from, 0, $quantity);
        $to = array_merge($to, $result);
    }

}
