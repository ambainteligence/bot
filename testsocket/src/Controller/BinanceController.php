<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Exchange\BinanceExchange;
use App\Services\BackHelperService;
use App\Services\TemplateService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Binance\API;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\ServerResponse;
use Longman\TelegramBot\Entities\Update;
use Ramsey\Uuid\Uuid;
use Doctrine\DBAL\Connection;

use Keiko\Uuid\Shortener\Dictionary;
use Keiko\Uuid\Shortener\Number\BigInt\Converter;
use Keiko\Uuid\Shortener\Shortener;
use App\Controller\Strategies;

use DateTime;

class BinanceController extends Controller
{
    // config
    const TIME             = 15 * 60; // 3 minutues
    const PREVIOUS_CANDLES = 0; // recheck previous candles

    const BUY  = 'buy';
    const SELL = 'sell';

    const SHOULD_SELL = -1;
    const SHOULD_BUY  = 1;

    private $botApiKey;
    private $botUserName;
    private $botChatId;
    private $binanceKey;
    private $binanceSecret;
    private $binance;
    private $helper;
    private $templateService;

    use Strategies;
    use File;
    use Element;
    use Date;

    public function __construct(BackHelperService $helper, TemplateService $templateService)
    {
        $this->botApiKey = ($_SERVER['BOT_API_KEY']) ?? null;
        $this->botUserName = ($_SERVER['BOT_USER_NAME']) ?? null;
        $this->botChatId = ($_SERVER['CHAT_ID']) ?? null;
        $this->binanceKey = ($_SERVER['BINANCE_KEY']) ?? null;
        $this->binanceSecret = ($_SERVER['BINANCE_SECRET']) ?? null;
        $this->binance = new API($this->binanceKey, $this->binanceSecret, ["useServerTime" => true]);
        $telegram = new Telegram($this->botApiKey, $this->botUserName);

        $this->helper = $helper;
        $this->templateService = $templateService;
        ini_set('trader.real_precision', '8');
    }

    public function getText()
    {
        $update = new Update(json_decode(Request::getInput(), true), $this->botUserName);

        return $update->getMessage()->getText();
    }

    public function getUserName()
    {
        $update = new Update(json_decode(Request::getInput(), true), $this->botUserName);

        return $update->getMessage()->getChat()->username;
    }

    public function getCandleSMA($close = 0)
    {
        $candles = $this->binance->candlesticks('ADAUSDT', "1h", 100);
        $results = [];
        foreach ($candles as $candle) {
            $results[] = $candle['close'];
        }

        for ($i = 0; $i < $close; $i++) {
            array_pop($results);
        }

        $macd = trader_macd($results, 12, 26, 9);

        $macd_raw = $macd[0];
        $signal = $macd[1];

        //Not enough Data
        if (!$macd_raw || !$signal) {
            return 0;
        }

        $macd_current = number_format(array_pop($macd_raw) - array_pop($signal), 8);
        $text = 'Macd: ' . array_pop($macd_raw) . '\n';
        $text .= 'Sign: ' . array_pop($signal) . '\n';
        $text .= 'Current Macd: ' . $macd_current . '\n';
        if (-0.0001 <= $macd_current && $macd_current < 0) {
            $text .= 'The macd signature is should buy';
        }
        if ($macd_current <= 0.0002) {
            $text .= 'The macd signature is should sell';
        }

        return $text;
    }

    public function changeStrToUnixtime($date, $period)
    {
        return [
            'from' => self::changeTimeStringToMilliSecond($date),
            'to' => strtotime($period, strtotime($date)) * 1000,
            'date' => self::changeTimestampToTimeString(strtotime($date), 'd-m-Y')
        ];
    }

    public function takeCandleFile($date, $perior)
    {
        $time = $this->changeStrToUnixtime($date, $perior);
        $fileName = "{$time['date']}.json";
        if (self::existFile($fileName)) {
            return self::readFile($fileName);
        }

        // we limit by date so canceled the total candles
        $candles = $this->binance->candlesticks('ADAUSDT', "1h", $range = 5000, $time['from'], $time['to']);
        self::writeFile($fileName, $candles);
        return $candles;
    }

    /**
     * limit in 26h
     * time to test in 1 day = (24 + 26)
     * 26 is number candle
     *
     * @param $period
     * @return mixed
     */
    public function definePeriod($period)
    {
        return $period;
    }

    public function process($data)
    {
        $ex = $this->helper->getExchange('bn', 1);

        $text = '';

        $preCurrentClose = end($data);

        // 1000
        $macd = $this->getResultOfStrategy($data, 'phuongb_bowhead_macd', 1, $text);
        $prePrice = $preCurrentClose['close'];
        $time = self::changeTimestampToTimeString($preCurrentClose['openTime'] / 1000);

        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => 'macd: ' . $macd . ' price: ' . $prePrice]);

        if ($activity = $this->helper->findActivityByOutcome(1, self::BUY)) {
            if ($macd === self::SHOULD_SELL) {
                $beforeData = json_decode($activity->getData(), true);
                $text .= ' prev: ' . $prePrice;
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);

                if ($beforeData['prev'] != $prePrice) {
                    $data = ['before_buyer' => $beforeData['price'], 'current_price' => $prePrice];
                    $percent = $ex->percentIncreate($data['before_buyer'], $data['current_price']);
                    $data['percent'] = $percent . '%';
                    $activity->setOutcome(self::SELL);
                    $activity->setData(json_encode($data));
                    $this->helper->updateActivityForSeller($activity);
                    $this->helper->calculatorProfit($uid = 1, $time, $percent);

                    // set profit by month
                    $month = self::changeTimestampToTimeString($preCurrentClose['openTime'] / 1000, 'm/Y');
                    $this->helper->calculatorProfitMonth($uid = 1, $month, $percent);

                    $text .= ' ready for seller data: ' . json_encode($data);
                    Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                }
            }
        }
        else {
            $profitToday = $this->helper->findProfitByCreatedDate($uid = 1, $time, 'bn');
            if ($macd === self::SHOULD_BUY && !$profitToday) {
                // buy at here
                // $uuid, $uid, $class, $exchange, $outcome, $data
                $currentPrice = end($data);
                $data = ['price' => $currentPrice['open'], 'prev' => $prePrice];
                $this->helper->insertActivity(Uuid::uuid4()->toString(), 1, 'App\Command\BuyCommand', 'bn', self::BUY, $data);
                $text .= ' ready for buyer';
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
            }
        }
    }


    public function calculatorPercent($number, $total) {
        return round(($number / $total) * 100, 2) . '%';
    }

    public function testWebsocket()
    {
//        $candles = $this->binance->candlesticks('ADAUSDT', '5m', 50);
//        $macd = $this->getResultOfStrategy($candles, 'phuongb_bowhead_macd', 0, $text);
//        dump($macd);
//        return new Response('ok fine');

        $candles = $this->binance->candlesticks('ADAUSDT', '5m', 50);
        $text = '';

        $ex = $this->helper->getExchange('bn', 1);
        $macd = $this->getResultOfStrategy($candles, 'phuongb_bowhead_macd', 0, $text);
        dump($macd);
        return new Response('ok fine');
    }

    public function testWebsocket1()
    {
        ini_set('trader.real_precision', '8');
        //    $api = $this->binance;
        //
        //    //=============
        //    $minute = time();
        $period = 34;
        // case aus
        $candles = $this->takeCandleFile('-4month -7day', $this->definePeriod('+31day 34hours'));

        $times = count($candles) - $period;
        // $rootCandles is start => middle candles
        // $candles is middle => end candles
        $results = self::splitElement($candles, $period);
        $rootCandles = $results['part_1'];
        $nextCandles = $results['part_2'];

        for ($i = 0; $i < $times; $i++) {
            self::moveElement($nextCandles, $rootCandles, $quantity = 1);
            $this->process($rootCandles);
            $percent = $this->calculatorPercent($i, $times);
            $candle = end($rootCandles);
            $date = self::changeMillisecondToTimeString($candle['openTime']);
            Request::sendMessage(['chat_id' => $this->botChatId, 'text' => '======> percent: ' . $percent . ' =======> date: ' . $date]);
        }

        return new Response('ok12345');
    }

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

    public function getResultOfStrategy($candles, $strategyName = 'phuongb_bowhead_stoch', $previousTimes = 0, &$text)
    {
        // get previous data
        for ($time = 0; $time < $previousTimes; $time++) {
            array_pop($candles);
        }

        $data = $this->changeCandlesToData($candles);
        dump($data);

        return $this->{$strategyName}('', $data, false, $text);
    }


    //Should be
    //45 => 33.45528781 = same
    //46 => 31.0530724 = same
    //47 => 32.30931073 = same
    //48 => 39.26151545 greatest
    //49 => 36.92756859 buy at here
    public function checkBuyRSI($prevPrevRSI, $preRSI, $RSI, &$message)
    {
        if (intval($prevPrevRSI) > 34) {
            return 0;
        }

        if (intval($preRSI) > 34) {
            return 0;
        }

        if ($prevPrevRSI > $preRSI) {
            $message .= ' Buy: Case 1 ' . $prevPrevRSI . ' > ' . $preRSI;

            return 0;
        }

        // compare percent
        $percentChange = (1 - $prevPrevRSI / $preRSI) * 100;
        if ($percentChange > 5) {
            $message .= ' Buy: Case 2 percent: ' . $percentChange;

            return 0;
        }

        //     Current RSI is greater than every prev RSIs
        if ($RSI < $prevPrevRSI || $RSI < $preRSI) {
            $message .= ' Buy: Case 3 ' . ' RSI: ' . $RSI . ' < prevPrevRSI: ' . $prevPrevRSI . ' and RSI ' . $RSI . ' < prevRSI: ' . $preRSI;

            return 0;
        }

        return 1;
    }


    //45 => 42.74519865
    //46 => 43.19249398
    //47 => 45.04200946
    //48 => 49.06059586
    //49 => 55.08620514
    public function checkSellRSI($prevPrevRSI, $preRSI, $RSI, &$message)
    {
        if (intval($prevPrevRSI) < 53) {
            return 0;
        }

        if (intval($preRSI) < 53) {
            return 0;
        }

        if ($prevPrevRSI < $preRSI) {
            $message .= ' Sell: Case 1 prevprev RSI ' . $prevPrevRSI . ' <  prev RSI ' . $preRSI;

            return 0;
        }

        // compare percent
        $percentChange = (1 - $prevPrevRSI / $preRSI) * 100;
        if ($percentChange > 5) {
            $message .= ' Sell: Case 2 percent: ' . $percentChange;

            return 0;
        }

        // RSI is greater than every prev RSIs
        if ($RSI > $prevPrevRSI || $RSI > $preRSI) {
            $message .= ' Sell: Case 3' . ' RSI: ' . $RSI . ' > prevPrevRSI: ' . $prevPrevRSI . ' and RSI ' . $RSI . ' > prevRSI: ' . $preRSI;

            return 0;
        }

        return 1;
    }

    public function checkSellCurrent($current, &$message)
    {
        return (intval($current) >= 49) ? 1 : 0;
    }

    public function checkBuyCurrent($current, &$message)
    {
        return (intval($current) <= 44) ? 1 : 0;
    }

    // selll every
    //94 => 51.51665575
    //95 => 50.66986313
    //99 => 51.18019162
    //98 => 49.29901465
    //97 => 48.13925589
    //96 => 46.54981709
    public function checkSellRSIByTimes($candles, $times = 3)
    {
        $current = null;
        //
        for ($times; $times > 0; $times--) {
            $current = $current ??  array_pop($candles);
            $previous = array_pop($candles);
            if ($previous > $current) {
                return 0;
            }
            $current = $previous;
        }

        return 1;
    }

    // buy every
    //94 => 51.51665575
    //95 => 50.66986313
    //96 => 46.54981709
    //97 => 48.13925589
    //98 => 49.29901465
    //99 => 51.18019162
    public function checkBuyRSIByTimes($candles, $times = 5)
    {
        $current = null;
        for ($times; $times > 0; $times--) {
            $current = $current ??  array_pop($candles);
            $previous = array_pop($candles);
            if ($previous > $current) {
                return 0;
            }
            $current = $previous;
        }

        return 1;
    }

    public function webHookTelegram()
    {
        $candles = $this->binance->candlesticks('ADAUSDT', "5m", 50);
        $close = $this->getCandleBySign($candles, 'close');
        $high = $this->getCandleBySign($candles, 'high');
        $low = $this->getCandleBySign($candles, 'low');

        $data = [
            'close' => $close,
            'high'  => $high,
            'low'   => $low,
        ];

        return new Response('ok');
    }
}


/*
 * RSI is same same
 * 43.24998259
 * 96 => 44.54871469
 * 97 => 45.62087678
 * 98 => 45.38462112
 * 2 numbers are same same
 *
 *
 */

// Find smallest couple candles
// If price is same
// Set value for small price
//91 => 21.18453578
//  92 => 22.64976015
//  93 => 22.5851646
//  94 => 27.89977618
//  95 => 25.83090369
//  96 => 24.98670248
//  97 => 23.84240312
//  98 => 35.14422939
//  99 => 34.01594285
