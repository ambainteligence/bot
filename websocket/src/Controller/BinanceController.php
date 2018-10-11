<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Exchange\BinanceExchange;
use App\Services\HelperService;
use App\Services\TemplateService;
use Longman\TelegramBot\Entities\InlineKeyboard;
use Longman\TelegramBot\Entities\InlineKeyboardButton;
use Longman\TelegramBot\Entities\Keyboard;
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

class BinanceController extends Controller
{
    // config
    const TIME             = 15 * 60; // 3 minutues
    const PREVIOUS_CANDLES = 2; // recheck previous candles

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

    public function testWebsocket()
    {
        ini_set('trader.real_precision', '8');
        //        $api = $this->binance;
        //        $helper = $this->helper->getExchange('bn', 1);

        //        $this->helper->binanceBuy('ONTUSDT', 1, $price = 2, '100%');
        //        $this->helper->binanceSell('ONTUSDT', 1, $price = 3, '100%');
        return new Response('ok');
    }

    public function testWebsocket1()
    {
        ini_set('trader.real_precision', '8');
        $api = $this->binance;

        //=============
        $minute = time();

        $api->chart(["ADAUSDT"], "1h", function ($api, $symbol, $chart) use (&$minute) {
            $currentChart = array_pop($chart);
            $currentClose = $currentChart['close'];
            //      array_pop($chart);
            $preCurrentClose = array_pop($chart);
            $preCurrentClose = $preCurrentClose['close'];

            $currentTime = time();
            if ($currentTime < ($minute + self::TIME)) {
                return;
            }
            $minute = $currentTime;

            try {
                $candles = $this->binance->candlesticks('ADAUSDT', "1h", 50);
                $data = $this->changeCandlesToData($candles);

                $text = '';
                //         case 1
                //        $bb_rsi = $this->bowhead_bband_rsi('', $data, false, $text);
                //        $text .= ' result: ' . $bb_rsi;
                //         case 2
                //        $double_vol = $this->bowhead_double_volatility('', $data, false, $text);
                //        case 3 // should try again
                //        $fiveElement = $this->bowhead_5th_element('', $data, false, $text);
                //        $text .= ' result: ' . $fiveElement;
                //        case 4
                $macd = $this->phuongb_bowhead_macd('', $data, false, $text);
                $text .= ' prev: ' . $preCurrentClose;
                //      case 5
                //        $stoch = $this->phuongb_bowhead_macd('', $data, false, $text);
                //        $text .= ' result: ' . $stoch;

                $ex = $this->helper->getExchange('bn', 1);

                // has buyer
                if ($activity = $this->helper->findActivityByOutcome(1, self::BUY)) {
                    //            case 1
                    //            if ($bb_rsi === -1) {
                    //            case 2
                    //            if ($double_vol === 1) {
                    //            case 3
                    //          if ($fiveElement === -1) {
                    //          case 4
                    if ($macd === self::SHOULD_SELL) {
                        //          case 5
                        //          if ($stoch === -1) {
                        $beforeData = json_decode($activity->getData(), true);
                        if ($beforeData['prev'] != $preCurrentClose) {
                            $data = ['before_buyer' => $beforeData['price'], 'current_price' => $ex->getCurrentPrice('ADAUSDT')];
                            $percent = $ex->percentIncreate($data['before_buyer'], $data['current_price']);
                            $data['percent'] = $percent . '%';
                            $activity->setOutcome(self::SELL);
                            $activity->setData(json_encode($data));
                            $this->helper->updateActivityForSeller($activity);
                            $this->helper->calculatorProfit($uid = 1, date('d/m/Y'), $percent);
                            $text .= ' ready for seller';
                            Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                        }
                    }
                }
                else {
                    //          case 1
                    //          if ($bb_rsi === 1) {
                    //          case 2
                    //          if ($double_vol === -1) {
                    //          case 3
                    //          if ($fiveElement === 1) {
                    //          case 4
                    if ($macd === self::SHOULD_BUY
                        && $this->getResultOfStrategy($candles, 'phuongb_bowhead_macd', self::PREVIOUS_CANDLES) === self::SHOULD_SELL
                    ) {
                        // buy at here
                        // $uuid, $uid, $class, $exchange, $outcome, $data
                        $data = ['price' => $ex->getCurrentPrice('ADAUSDT'), 'close' => $currentClose, 'prev' => $preCurrentClose];
                        $this->helper->insertActivity(Uuid::uuid4()->toString(), 1, 'App\Command\BuyCommand', 'bn', self::BUY, $data);
                        $text .= ' ready for buyer';
                        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                    }
                }
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
            }
            catch (\Exception $e) {
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $e->getMessage()]);
            }
        });

        return new Response('ok');
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

    public function getResultOfStrategy($candles, $strategyName = 'phuongb_bowhead_stoch', $previousTimes = 0)
    {
        // get previous data
        for ($time = 0; $time < $previousTimes; $time++) {
            array_pop($candles);
        }
        $data = $this->changeCandlesToData($candles);

        return $this->{$strategyName}('', $data, false);
    }

}

