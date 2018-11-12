<?php
/**
 * Created by PhpStorm.
 * User: joeldg
 * Date: 4/13/17
 * Time: 6:26 PM
 */
namespace App\Controller;


trait ManagerLogic
{

    public function getPreviousTime($conditions) {
        if (4 == count($conditions)) {
            return end($conditions);
        }
        return 0;
    }

    public function getSingleOrAll(&$condition)
    {
        if (5 == count($condition)) {
            return array_pop($condition);
        }
        return 0;
    }

    public function processActions($candles = [], $conditions = [], &$text = '') {
        $results = true;
        foreach ($conditions as $condition) {
            $singleOrAll = $this->getSingleOrAll($condition);
            $previousTime = $this->getPreviousTime($condition);
            list($logicName, $target, $operator) = $condition;
            if ('ALL' === $singleOrAll) {
                $result = $this->getSameResultsOfStrategy($candles, $logicName, $previousTime, $text);
            }
            else {
                $result = $this->getResultOfStrategy($candles, $logicName, $previousTime, $text);
            }
            if ('AND' == $operator) {
                // 1 && (sell = sell)
                // 1 && (buy = buy)
                $results = $results && ($target === $result);
            }
            else {
                $results = $results || ($target === $result);
            }
        }
        return $results;
    }

}
