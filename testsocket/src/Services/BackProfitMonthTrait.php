<?php

namespace App\Services;
use App\Entity\BackProfitMonth;

trait BackProfitMonthTrait
{

    /**
     * require entityManage and repo of profitMonth
     */
    public function insertProfitMonth($uid, $exchange, $createdDate, $percent)
    {
        $profit = new BackProfitMonth();
        $profit->setUid($uid);
        $profit->setExchange($exchange);
        $profit->setCreatedDate($createdDate);
        $profit->setPercent($percent);
        $this->entityManage->persist($profit);
        $this->entityManage->flush();
    }

    public function updateProfitMonth($profit) {
        $this->entityManage->persist($profit);
        $this->entityManage->flush();
    }

    public function findProfitMonthByCreatedDate($userId, $createdDate, $exchange = 'bn') :? BackProfitMonth
    {
        return $this->profitMonthRepo->findOneBy(['uid' => $userId, 'exchange' => $exchange, 'created_date' => $createdDate]);
    }

    public function calculatorProfitMonth($uid, $time, $percent, $exchange = 'bn')
    {
        $profit = $this->findProfitMonthByCreatedDate($uid, $time, $exchange);
        if (!$profit) {
            $this->insertProfitMonth($uid, $exchange, $time, $percent);
        }
        else {
            $profit->setPercent($profit->getPercent() + $percent);
            $this->updateProfitMonth($profit);
        }
    }


}
