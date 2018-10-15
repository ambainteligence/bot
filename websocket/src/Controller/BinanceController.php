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
    const TIME             = 5 * 60; // 5 minutues
    const PREVIOUS_CANDLES = 5; // recheck previous candles
    const SYMBOL = 'ADAETH';
    const CANDLE_TIME = '15m';
    const PERCENT_BUY = '100%';
    const PERCENT_SELL = '100%';

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

    use CandlesTrait;
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

    public function testWebsocket1()
    {
        ini_set('trader.real_precision', '8');

        //        $api = $this->binance;
        //        $helper = $this->helper->getExchange('bn', 1);

        //        $this->helper->binanceBuy('ONTUSDT', 1, $price = 2, '100%');
//        $this->helper->binanceSell('ONTUSDT', 1, $price = 3, '100%');
//        $money = $this->helper->binanceSell(self::SYMBOL, 1, '0.08381000', '100%');
//        $this->helper->calculatorProfit($uid = 1, date('d/m/Y'), $percent = '-1.21', $money);
//        dump($money);
//        $this->helper->binanceBuy(self::SYMBOL, 1, '0.06372000', self::PERCENT_BUY);
        return new Response('ok');
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
                $macd = $this->getResultOfStrategy($candles, 'phuongb_bowhead_macd', 0, $text);

                // has buyer
                if ($activity = $this->helper->findActivityByOutcome(1, self::BUY)) {
                    if ($macd === self::SHOULD_SELL) {
                        $beforeData = json_decode($activity->getData(), true);
                        if ($beforeData['prev'] != $preCurrentClose) {
                            $data = ['before_buyer' => $beforeData['price'], 'current_price' => $ex->getCurrentPrice('ADAUSDT')];
                            $percent = $ex->percentIncreate($data['before_buyer'], $data['current_price']);
                            $data['percent'] = $percent . '%';
                            $activity->setOutcome(self::SELL);
                            $activity->setData(json_encode($data));
                            $this->helper->updateActivityForSeller($activity);

                            // sell symbol
                            $money = $this->helper->binanceSell(self::SYMBOL, 1, $data['current_price'], self::PERCENT_SELL);
                            $this->helper->calculatorProfit($uid = 1, date('d/m/Y'), $percent, $money);

                            $text .= ' ready for seller';
                            Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
                        }
                    }
                }
                else {
                    if ($macd === self::SHOULD_BUY
                        && $this->getResultOfStrategy($candles, 'phuongb_bowhead_macd', self::PREVIOUS_CANDLES) === self::SHOULD_SELL
                    ) {
                        // buy at here
                        // $uuid, $uid, $class, $exchange, $outcome, $data
                        $data = ['price' => $ex->getCurrentPrice('ADAUSDT'), 'close' => $currentClose, 'prev' => $preCurrentClose];
                        $this->helper->insertActivity(Uuid::uuid4()->toString(), 1, 'App\Command\BuyCommand', 'bn', self::BUY, $data);
                        $text .= ' ready for buyer';
                        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);

                        // buy symbol
                        $this->helper->binanceBuy(self::SYMBOL, 1, $data['price'], self::PERCENT_BUY);
                    }
                }
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text . ' result1: ' . $macd]);
            }
            catch (\Exception $e) {
                Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $e->getMessage()]);
            }
        });

        return new Response('ok');
    }

}

