<?php

namespace App\Services;
use App\Entity\BackProfit;

trait BackProfitTrait
{
    public function insertProfit($uid, $exchange, $createdDate, $percent)
    {
        $profit = new BackProfit();
        $profit->setUid($uid);
        $profit->setExchange($exchange);
        $profit->setCreatedDate($createdDate);
        $profit->setPercent($percent);
        $this->entityManage->persist($profit);
        $this->entityManage->flush();
    }

    public function updateProfit($profit)
    {
        $this->entityManage->persist($profit);
        $this->entityManage->flush();
    }

    public function findProfitByCreatedDate($userId, $createdDate, $exchange = 'bn') :? BackProfit
    {
        return $this->profitRepo->findOneBy(['uid' => $userId, 'exchange' => $exchange, 'created_date' => $createdDate]);
    }

    public function calculatorProfit($uid, $time, $percent, $exchange = 'bn')
    {
        $profit = $this->findProfitByCreatedDate($uid, $time, $exchange);
        if (!$profit) {
            $this->insertProfit($uid, $exchange, $time, $percent);
        }
        else {
            $profit->setPercent($profit->getPercent() + $percent);
            $this->updateProfit($profit);
        }
    }


}
