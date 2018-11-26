<?php

namespace App\Controller;

use App\Services\HelperService;
use App\Services\TemplateService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Binance\API;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;
use Longman\TelegramBot\Entities\Update;
use Ramsey\Uuid\Uuid;
use Doctrine\DBAL\Connection;


class BinanceController extends Controller
{
    // config
    const TIME             = 3 * 60; // 3 minutues
    const PREVIOUS_CANDLES = 5; // recheck previous candles
    const SYMBOL = 'ADAUSDT';
    const CANDLE_TIME = '15m';
    const PERCENT_BUY = '100%';
    const PERCENT_SELL = '100%';

    // stop-limit
    const PERCENT_TARGET_PROFIT = 1;
    const PERCENT_LIMIT_PROFIT = 0.6;
    const LIMITED_PERCENT = -1;

    const BUY  = 'buy';
    const SELL = 'sell';

    const SHOULD_SELL = -1;
    const SHOULD_BUY  = 1;

    const BLOCK = 0;
    const ACTIVE = 1;

    const LONG_TIME_STRING = 'd:m:Y H:i';

    private $botApiKey;
    private $botUserName;
    private $botChatId;
    private $binanceKey;
    private $binanceSecret;
    private $binance;
    public $helper;
    private $templateService;

    use CandlesTrait;
    use Strategies;
    use CustomStrategies;
    use ReportTrail;
    use ManagerLogic;
    use Date;

    public $buyConditions = [
//        ['phuongb_bowhead_macd', self::SHOULD_BUY, 'AND'],
//        ['phuongb_bowhead_macd', self::SHOULD_SELL, 'AND', 3, 'ALL'],
        ['phuongb_mfi', self::SHOULD_BUY, 'AND'],
        ['phuongb_buy_stop_limit', self::SHOULD_BUY, 'AND'],
    ];

    public $sellConditions = [
//        ['phuongb_bowhead_macd', self::SHOULD_SELL, 'AND'],
        ['phuongb_mfi', self::SHOULD_SELL, 'AND'],
        ['phuongb_sell_stop_limit', self::SHOULD_SELL, 'OR'],
        ['phuongb_sell_profit_stop_limit', self::SHOULD_SELL, 'OR']
    ];

    public function __construct(HelperService $helper, TemplateService $templateService)
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
        date_default_timezone_set('Asia/Ho_Chi_Minh');
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

    public function testWebsocket1()
    {
        ini_set('trader.real_precision', '8');
        $ex = $this->helper->getExchange('bn', 1);

//        $myTime = $this->changeTimeStringToMilliSecond('19:11:2018 12:45', self::LONG_TIME_STRING);
        $myTime = $this->changeTimeStringToMilliSecond('26:11:2018 17:15', self::LONG_TIME_STRING);
//        $currentTime = $this->changeTimeStringToMilliSecond(date(self::LONG_TIME_STRING), self::LONG_TIME_STRING);
        $candles = $this->binance->candlesticks(self::SYMBOL, '3m', $range = 50, null, $myTime);
//        $data = $this->changeCandlesToData($candles);
//        $openCandles = $data['open'];

        $text = '';

//        $activity = $this->helper->findActivityByOutcome(1, self::BUY);
        $sellResult = $this->processActions($candles, $this->sellConditions, $text);
//        $beforeData = json_decode($activity->getData(), true);
//        $data = ['before_buyer' => $beforeData['price'], 'current_price' => $ex->getCurrentPrice('ADAUSDT')];
//        $percent = $ex->percentIncreate($data['before_buyer'], $data['current_price']);
//        $data['percent'] = $percent . '%';
//        dump($this->helper->findLatestActivity());
        return new Response('ok123');
    }

    public function testWebsocket()
    {
        ini_set('trader.real_precision', '8');
        $api = $this->binance;
        //=============
        $minute = time();

        $api->chart([self::SYMBOL], self::CANDLE_TIME, function ($api, $symbol, $chart) use (&$minute) {
            $currentChart = array_pop($chart);
            $currentClose = $currentChart['close'];
            $preCurrentClose = array_pop($chart);
            $preCurrentClose = $preCurrentClose['close'];

            $currentTime = time();
            if ($currentTime < ($minute + self::TIME)) {
                return;
            }
            $minute = $currentTime;

            try {
                $candles = $this->binance->candlesticks(self::SYMBOL, self::CANDLE_TIME, 50);
                $text = '';

                $ex = $this->helper->getExchange('bn', 1);
                $candle = end($candles);

                $text = $this->reportPriceResultTime($candle['open'], $candle['openTime'], $text);
                $action = 0;

                // has buyer
                if ($activity = $this->helper->findActivityByOutcome(1, self::BUY)) {
                    $sellResult = $this->processActions($candles, $this->sellConditions, $text);

                    $beforeData = json_decode($activity->getData(), true);
                    $data = [
                        'before_buyer' => $beforeData['price'],
                        'time' => $beforeData['time'],
                        'current_price' => $ex->getCurrentPrice(self::SYMBOL),
                        'sell_time' =>  date(self::LONG_TIME_STRING)
                    ];
                    $percent = $ex->percentIncreate($data['before_buyer'], $data['current_price']);
                    $data['percent'] = $percent . '%';
                    // set logic at here
                    if ($sellResult) {
                        $activity->setOutcome(self::SELL);
                        $activity->setData(json_encode($data));
                        $this->helper->updateEntity($activity);

                        // sell symbol
                        $money = $this->helper->binanceSell(self::SYMBOL, 1, $data['current_price'], self::PERCENT_SELL);
                        $this->helper->calculatorProfit($uid = 1, date('d/m/Y'), $percent, $money);

                        $text .= ' ready for seller. Percent: ' . $data['percent'];
                        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                        $action = 1;
                    }
                    else {
                        $text .= ' current percent: ' . $data['percent'];
                    }
                }
                else
                {
                    $buyResult = $this->processActions($candles, $this->buyConditions, $text);
                    if ($buyResult) {
                        // buy at here
                        // $uuid, $uid, $class, $exchange, $outcome, $data
                        $data = ['price' => $ex->getCurrentPrice(self::SYMBOL), 'close' => $currentClose, 'prev' => $preCurrentClose, 'time' => date(self::LONG_TIME_STRING)];
                        $this->helper->insertActivity(Uuid::uuid4()->toString(), 1, 'App\Command\BuyCommand', 'bn', self::BUY, $data);
                        // buy symbol
                        $this->helper->binanceBuy(self::SYMBOL, 1, $data['price'], self::PERCENT_BUY);

                        $text .= ' ready for buyer';
                        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                        $action = 1;
                    }
                }


                if (0 === $action) {
                    $this->recheckActionTrading();

                    $activity = $this->helper->findLatestActivity();
                    if (self::SELL == $activity->getOutcome()) {
                        $currentPrice = $ex->getCurrentPrice(self::SYMBOL);
                        $beforeData = json_decode($activity->getData(), true);
                        $data = ['before_buyer' => $beforeData['current_price'], 'current_price' => $currentPrice];
                        $percent = $ex->percentIncreate($data['before_buyer'], $data['current_price']);
                        $data['percent'] = $percent . '%';
                        $text .= ' ==> percent: ' . $data['percent'];
                    }
                    Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                }
            }
            catch (\Exception $e) {
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $e->getMessage()]);
            }
        });

        return new Response('ok');
    }

    public function recheckActionTrading()
    {
        $uid = 1;
        $ex = $this->helper->getExchange('bn', $uid);

        $price = $ex->getCurrentPrice(self::SYMBOL);
        if ($activity = $this->helper->findActivityByOutcome($uid, self::BUY)) {
            if ($this->helper->checkQuantity(self::SYMBOL, $uid, self::BUY)) {
                $this->helper->binanceBuy(self::SYMBOL, $uid, $price, self::PERCENT_BUY);
            }
        }
        else {
            if ($this->helper->checkQuantity(self::SYMBOL, $uid, self::SELL)) {
                $this->helper->binanceSell(self::SYMBOL, $uid, $price, self::PERCENT_SELL);
            }
        }
    }

}

