<?php

namespace App\Services;

use App\Entity\Activity;
use App\Entity\Profit;
use App\Entity\User;
use App\Exchange\BinanceExchange;
use App\Message\ErrorMessage;
use App\Repository\ActivityRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\EntityManager;
use GuzzleHttp\Exception\RequestException;
use Keiko\Uuid\Shortener\Dictionary;
use Keiko\Uuid\Shortener\Number\BigInt\Converter;
use Keiko\Uuid\Shortener\Shortener;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Exception;

//use App\Command\uyCommand;

class HelperService
{
    private $text;
    private $messages;
    CONST BINANCE_SIGN = 'bn';
    CONST BT_SIGN      = 'bt';

    private $entityManage;
    private $activityRepo;
    private $userRepo;
    private $templateService;

    use BinanceServiceTrait;

    public function __construct(ObjectManager $entityManager, TemplateService $templateService)
    {
        $this->messages = new ErrorMessage();
        $this->entityManage = $entityManager;
        $this->activityRepo = $entityManager->getRepository(Activity::class);
        $this->profitRepo = $entityManager->getRepository(Profit::class);
        $this->userRepo = $entityManager->getRepository(User::class);
        $this->templateService = $templateService;
    }

    public function validateExchange($sign)
    {
        if (strtolower($sign) == self::BINANCE_SIGN) {
            return true;
        }
        //    if (strtolower($sign) == self::BT_SIGN) {
        //      return true;
        //    }

        $this->messages->log('The exchange not found');

        return false;
    }

    public function getMessage()
    {
        return $this->messages->getMessages();
    }

    /**
     * @param $sign
     * @param $userId
     * @return BinanceExchange|null
     */
    public function getExchange($sign, $userId):? BinanceExchange
    {
        if (strtolower($sign) == self::BINANCE_SIGN) {
            $user = $this->findUserById($userId);
            return new BinanceExchange($user->getApiKey(), $user->getSecretKey());
        }

        return null;
    }

    public function findActivityById($id): ?Activity
    {
        return $this->activityRepo->find($id);
    }

    public function findProfitByCreatedDate($userId, $createdDate, $exchange = 'bn'): ?Profit
    {
        return $this->profitRepo->findOneBy(['uid' => $userId, 'exchange' => $exchange, 'created_date' => $createdDate]);
    }

    public function insertProfit($uid, $exchange, $createdDate, $percent, $data)
    {
        $profit = new Profit();
        $profit->setUid($uid);
        $profit->setExchange($exchange);
        $profit->setCreatedDate($createdDate);
        $profit->setPercent($percent);
        $profit->setData($data);
        $this->entityManage->persist($profit);
        $this->entityManage->flush();
    }

    public function updateProfit($profit)
    {
        $this->entityManage->persist($profit);
        $this->entityManage->flush();
    }

    public function calculatorProfit($uid, $time, $percent, $money, $exchange = 'bn')
    {
        $profit = $this->findProfitByCreatedDate($uid, $time, $exchange);
        $data = json_encode(['money' => $money]);
        if (!$profit) {
            $this->insertProfit($uid, $exchange, $time, $percent, $data);
        }
        else {
            $profit->setPercent($profit->getPercent() + $percent);
            $profit->setData($data);
            $this->updateProfit($profit);
        }
    }

    public function findActivityByUuid($uuid, $userId)
    {
        return $this->activityRepo->findOneBy(['uuid' => $uuid, 'uid' => $userId, 'outcome' => 'pending']);
    }

    public function findActivityByOutcome($userId, $outcome)
    {
        return $this->activityRepo->findOneBy(['uid' => $userId, 'outcome' => $outcome]);
    }

    public function findLatestActivity()
    {
        return $this->activityRepo->findOneBy([], ['id' => 'DESC']);
    }

    public function findActivityByOrderId($orderId): ?Activity
    {
        return $this->activityRepo->findOneBy(['tradeId' => $orderId]);
    }

    public function findUserByUserName($userName)
    {
        return $this->userRepo->findOneBy(['user_name' => $userName]);
    }

    public function findUserById($id)
    {
        return $this->userRepo->find($id);
    }

    public function insertActivity($uuid, $uid, $class, $exchange, $outcome, $data)
    {
        $activity = new Activity();
        $activity->setData(json_encode($data));
        $activity->setUid($uid);
        $activity->setClass($class);
        $activity->setExchange($exchange);
        $activity->setUuid($uuid);
        $activity->setOutcome($outcome);
        $this->entityManage->persist($activity);
        $this->entityManage->flush();
    }

    public function updateEntity($activity)
    {
        $this->entityManage->persist($activity);
        $this->entityManage->flush();
    }

    public function expandUuid($shorterUuid)
    {
        try {
            $shorter = new Shortener(
                Dictionary::createUnmistakable(),
                new Converter()
            );
            $shorter->expand($shorterUuid);
        }
        catch (Exception $e) {
            return false;
        }

        return $shorter->expand($shorterUuid);
    }

    public function reduceUuid($longUuid)
    {
        $shorter = new Shortener(
            Dictionary::createUnmistakable(),
            new Converter()
        );

        return $shorter->reduce($longUuid);
    }

    public function getActivityByUserId($text, $userId)
    {
        $uuid = ltrim($text, "/");
        $longUuid = $this->expandUuid($uuid);

        if ($activity = $this->findActivityByUuid($longUuid, $userId)) {
            return $activity;
        }
    }

    public function process(Activity $activity, $userId)
    {
        $className = $activity->getClass();
        $command = new $className();
        $command->addOjbect(json_decode($activity->getData(), true));
        $exchange = $this->getExchange($activity->getExchange(), $userId);
        $orderId = $command->process($exchange);
        $activity->setTradeId($orderId);
        $this->entityManage->persist($activity);
        $this->entityManage->flush();

        return $orderId;
    }

    public function updateStatus($activity)
    {
        $activity->setOutcome('finished');
        $this->entityManage->persist($activity);
        $this->entityManage->flush();
    }

    public function getBalanceInfo($uid)
    {
        $exchange = $this->getExchange('bn', $uid);
        $balances = $exchange->getBalance();

        return $this->templateService->renderBalances($balances);
    }

    public function processUserCommand($text, $uid)
    {
        if ($args = explode(" ", $text)) {
            switch ($args[0]) {
                case '/price':
                    return $this->getPriceInfo($args[1], $uid);

                case '/bl':
                    return $this->getBalanceInfo($uid);
            }
        }
    }

    public function formatSymbol($symbol)
    {
        $noBtc = (strpos($symbol, 'btc') == false);
        $noEth = (strpos($symbol, 'eth') == false);
        $noUsdt = (strpos($symbol, 'usdt') == false);

        if ($noBtc && $noEth && $noUsdt) {
            $symbol .= 'btc';
        }

        return strtoupper($symbol);
    }

    public function getPriceInfo($symbol, $uid)
    {
        $exchange = $this->getExchange('bn', $uid);
        $symbol = $this->formatSymbol($symbol);
        $symbolInfo = $exchange->getPrevDay($symbol);

        return $this->templateService->renderPriceSymbol(
            $symbolInfo['commandName'],
            $symbolInfo['quoteAsset'],
            $symbolInfo['lastPrice'],
            $symbolInfo['bidPrice'],
            $symbolInfo['askPrice'],
            $symbolInfo['lowPrice'],
            $symbolInfo['highPrice'],
            round($symbolInfo['priceChangePercent'], 2),
            round($symbolInfo['quoteVolume'], 2)
        );
    }

    public function detach($text = null, $uid)
    {
        $text = ($text) ?? $this->text;
        if ($activity = $this->getActivityByUserId($text, $uid)) {
            $this->updateStatus($activity);

            return $this->process($activity, $uid);
        }

        if ($result = $this->processUserCommand($text, $uid)) {
            return $result;
        }

        return $this->processCommand($text, $uid);
    }

    public function processCommand($text, $uid)
    {
        $args = explode(' ', $text);
        $commandName = array_shift($args);
        $commandName .= 'Command';

        $sign = substr($commandName, 0, 2);
        $commandName = ucfirst(substr($commandName, 2));
        if (!$this->validateExchange($sign)) {
            return $this->messages->getMessages();
        }

        $className = "App\\Command\\" . $commandName;
        $command = new $className();

        if (!$command->validate($args)) {
            return $command->message->getMessages();
        }

        $command->addOjbect($args);
        $exchange = $this->getExchange($sign, $uid);

        $uuid = Uuid::uuid4()->toString();
        $this->insertActivity($uuid, $uid, $className, $sign, 'pending', $args);
        $confirmInfo = $command->confirmInfo($exchange);
        $confirmInfo['uuid'] = $this->reduceUuid($uuid);

        return $this->templateService->renderConfirmTemplete(
            $confirmInfo['commandName'],
            $confirmInfo['exchange'],
            $confirmInfo['symbol'],
            $confirmInfo['quantity'],
            $confirmInfo['price'],
            $confirmInfo['diff'],
            $confirmInfo['currentPrice'],
            $confirmInfo['havingAsset'],
            $confirmInfo['targetAsset'],
            $confirmInfo['total'],
            $confirmInfo['uuid'],
            $confirmInfo['stop'],
            $confirmInfo['limit']
        );
    }
}
