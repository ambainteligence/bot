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


class BinanceController extends Controller
{
  private $botApiKey;
  private $botUserName;
  private $botChatId;
  private $binanceKey;
  private $binanceSecret;
  private $binance;
  private $helper;
  private $templateService;

  public function __construct(HelperService $helper, TemplateService $templateService) {
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
//    [  "symbol" => "EOSBTC"
//  "side" => "BUY"
//  "orderType" => "LIMIT"
//  "quantity" => "1.00000000"
//  "price" => "0.00175760"
//  "executionType" => "TRADE"
//  "orderStatus" => "FILLED"
//  "rejectReason" => "NONE"
//  "orderId" => 47356159
//  "clientOrderId" => "web_91e42de413cc4787a1f79390868567a7"
//  "orderTime" => 1526095666390
//  "eventTime" => 1526095666393
//    ]
//    $api = $this->binance;
//
//    $balance_update = function($api, $balances) {
//    };
//    $order_update = function($api, $report) {
//      $price = $report['price'];
//      $quantity = $report['quantity'];
//      $symbol = $report['symbol'];
//      $side = $report['side'];
//      $orderType = $report['orderType'];
//      $orderId = $report['orderId'];
//
//      $executionType = $report['orderStatus'];
//      if (in_array($executionType, ["FILLED"])) {
//        $activity = $this->helper->findActivityByOrderId($orderId);
//        $className =  $activity->getClass();
//        $command = new $className();
//        $command->addOjbect(json_decode($activity->getData(), true));
//        $exchange = $this->helper->getExchange($activity->getExchange(), $activity->getUid());
//        $exchange->stopLoss($symbol, $quantity, $command->limit, $command->stop);
//        $message = "{$symbol} {$side} {$executionType} stop at {$command->stop} with limit at  $command->limit";
//        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $message]);
//      }
//    };
//
//    $api->chart(["ADAUSDT"], "5m", function($api, $symbol, $chart) {
//      $candles = $this->binance->candlesticks('ADAUSDT', "1h", 100);
//      $results = [];
//      foreach($candles as $candle) {
//        $results[] = $candle['close'];
//      }
//
//      $macd = trader_macd($results, 12, 26, 9);
//
//      $macd_raw = $macd[0];
//      $signal   = $macd[1];
//
//      //Not enough Data
//      if(!$macd_raw || !$signal){
//        return 0;
//      }
//
//      $macd_current  = number_format(array_pop($macd_raw) - array_pop($signal), 8);
//      $text = 'Macd: ' . array_pop($macd_raw) . '\n';
//      $text .= 'Sign: ' . array_pop($signal) . '\n';
//      $text .= 'Current Macd: ' . $macd_current . '\n';
//      if (-0.0001 <= $macd_current && $macd_current < 0) {
//        $text .= 'The macd signature is should buy';
//      }
//      if ($macd_current <= 0.0002) {
//        $text .= 'The macd signature is should sell';
//      }
//
//      Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
//    });

//    $api->cancel()

//    $api->keepAlive();
//    $api->userData($balance_update, $order_update);

//    $flags['stopPrice'] = '0.00000650';
//    $api->sell('TRXBTC', '200', 0.00000650, 'STOP_LOSS_LIMIT', $flags);

    $candles = $this->binance->candlesticks('ADAUSDT', "5m", 50);
    $results = [];
    foreach($candles as $candle) {
      $results[] = $candle['close'];
    }
    $bBand = trader_bbands( $results,
      25,
      TRADER_REAL_MIN,
      TRADER_REAL_MIN,
      TRADER_MA_TYPE_EMA
    );
    dump($bBand);
    return new Response('ok');
  }

  public function MA($closes)
  {
    $arg = 0;
    $sum = 0;
    foreach ($closes as $close) {
      $sum += $close;
    }
    return $sum / count($closes);
  }

  public function test()
  {
//
//    $balances = $this->binance->balances();
//    $balance = $balances[$symbol = 'OMG'];
//    dump($balance['available']);
//    exit;



//    $ticks = $api->candlesticks("BNBBTC", "5m");
    $long =
//    $candles = $this->binance->candlesticks('BTCUSDT', "1h", 7);
//    $results = [];
//    foreach($candles as $candle) {
//      $results[] = $candle['close'];
//    }
//
//    dump(trader_ma ( $results , 7 , TRADER_MA_TYPE_SMA ));

    $candles = $this->binance->candlesticks('ADAUSDT', "1h", 100);
    $results = [];
    foreach($candles as $candle) {
      $results[] = $candle['close'];
    }

//    dump(trader_ma ( $results , 25 , TRADER_MA_TYPE_SMA ));
    array_pop($results);
    array_pop($results);
//    array_pop($results);
    $macd = trader_macd($results, 12, 26, 9);
//    dump($macd);
//    dump(trader_ema($results, 9));

//    $ema  = trader_ema($candles, 20); // get the ema
//    $ema  = @array_pop($ema) ?? 0; // get the current ema value
//    dump($ema);
//    return new Response('ok');

    $macd_raw = $macd[0];
    $signal   = $macd[1];

    //Not enough Data
    if(!$macd_raw || !$signal){
      return 0;
    }

    $macd_current  = number_format(array_pop($macd_raw) - array_pop($signal), 8);
    dump('Macd: ' . array_pop($macd_raw));
    dump('Sign: ' . array_pop($signal));
    dump('Current Macd: ' . $macd_current);
    if (-0.0001 <= $macd_current && $macd_current < 0) {
      dump('The macd signature is should buy');
    }
    if ($macd_current <= 0.0002) {
      dump('The macd signature is should sell');
    }

      $rsi = trader_rsi ($results, $period = 14);
      $rsi = array_pop($rsi);
      dump('stock rsi');
      dump($rsi);
//    $stochrsi = array_pop($stochrsi);


//    array_pop($results);
//    $macd = trader_macd($results, 12, 26, 9);
//    $macd_raw = $macd[0];
//    $signal   = $macd[1];
//
//    //Not enough Data
//    if(!$macd_raw || !$signal){
//      return 0;
//    }
//
//    $macd_current  = (array_pop($macd_raw) - array_pop($signal));
//    dump('Macd: ' . array_pop($macd_raw));
//    dump('sign: ' . array_pop($signal));
//    dump('Current Macd: ' . $macd_current);
//    if (-0.0001 <= $macd_current && $macd_current < 0) {
//      dump('The macd signature is should buy');
//    }
//    if ($macd_current <= 0.0002) {
//      dump('The macd signature is should sell');
//    }

//
//
//    $macd_current1  = (array_pop($macd_raw) - array_pop($signal));
//    dump('Macd: ' . array_pop($macd_raw));
//    dump('sign: ' . array_pop($signal));
//    dump('Current Macd: ' . $macd_current);
//    if (-0.0001 <= $macd_current1 && $macd_current1 < 0) {
//      dump('The macd signature is should buy');
//    }
//    if ($macd_current <= 0.0002) {
//      dump('The macd signature is should sell');
//    }


    return new Response('ok');
  }

  public function webHookTelegram()
  {
    $text = $this->getText();
    $userName = $this->getUserName();

//    $text = 'bnBuy adausdt 55 0.1963  sl 0.1961 0.1960';
////    case 1
////    $text = 'bnBuy adausdt 100 0.32';
////    $userName = 'phuongbui1988';
//
////    case 2
////    $text = '/zHYzJcnp7SKU4Uz3e2beug';
//
////    case 3
//     $text = '/ada';

////  case 4
////    $text = '/bl';
//
////    case 5
////    $text = 'bnbuy trx 200 0.00000774 sl 0.00000700 0.00000650';
////    $userName = 'phuongbui1988';
//
////  case 6
//    $text = '/LaMQmWcDf6EwhxJzbjfesb';
//    $userName = 'phuongbui1988';
//

//    case 7
//    $text = '/o';
//    $userName = 'phuongbui1988';

//    case 8
//    $text = '/cancel 1';
//    $userName = 'phuongbui1988';

//    case 9
//    $text = '/bnsell ada 100% 0.000028';
//    $userName = 'phuongbui1988';

//  case 9 continue
//    $text = '/H5fhcn3PEah9qukiRZeGoE';
//    $userName = 'phuongbui1988';

    $userId = $this->helper->findUserByUserName($userName)->getId();
    $messages = $this->helper->detach($text, $userId);

    $messages = !is_array($messages) ? $messages : json_encode($messages);
    $result = Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $messages]);
    return new Response('ok');
  }
}
