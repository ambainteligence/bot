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


class BinanceControllerOlder extends Controller
{
  const BUY = 'buy';
  const SELL = 'sell';

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

  public function getCandleSMA($close = 0) {
    $candles = $this->binance->candlesticks('ADAUSDT', "1h", 100);
    $results = [];
    foreach($candles as $candle) {
      $results[] = $candle['close'];
    }

    for($i = 0; $i < $close; $i++) {
      array_pop($results);
    }

    $macd = trader_macd($results, 12, 26, 9);

    $macd_raw = $macd[0];
    $signal   = $macd[1];

    //Not enough Data
    if (!$macd_raw || !$signal) {
      return 0;
    }

    $macd_current  = number_format(array_pop($macd_raw) - array_pop($signal), 8);
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

  public function testWebsocket()
  {
    $api = $this->binance;

    $results = [];
    $candles = $this->binance->candlesticks('ADAUSDT', "5m", 50);
    foreach($candles as $candle) {
      $results[] = $candle['close'];
    }
    $rsiLines = trader_rsi($results , 14);
//    dump($this->checkBuyRSIByTimes($rsiLines, 3));
//    dump($this->checkSellRSIByTimes($rsiLines, 2));
//    dump($rsiLines);

    $api->chart(["ADAUSDT"], "5m", function($api, $symbol, $chart) use(&$minute) {
      try {
        $currentChart = array_pop($chart);
        $close = $currentChart['close'];

        $results = [];
        $candles = $this->binance->candlesticks('ADAUSDT', "5m", 50);
        foreach($candles as $candle) {
          $results[] = $candle['close'];
        }

        $rsiLines = trader_rsi($results , 14);
        $tempRsiLines = $rsiLines;

        $currentRSILine = array_pop($tempRsiLines);
        $prevRSILine = array_pop($tempRsiLines);
        $prevPrevRSILine = array_pop($tempRsiLines);
        $prevPrevPrevRSILine = array_pop($tempRsiLines);

        $text = ' Prev prev prev: ' . round($prevPrevPrevRSILine, 3);
        $text .= ' Prev prev: ' . round($prevPrevRSILine, 3);
        $text .= ' Prev: ' . round($prevRSILine, 3);
        $text .= 'Current: ' . round($currentRSILine, 3);

        $ex = $this->helper->getExchange('bn', 1);
        // has buyer
        if ($activity = $this->helper->findActivityByOutcome(1, self::BUY)) {
          if ($this->checkSellRSIByTimes($rsiLines, 2)) {
            $beforeData = json_decode($activity->getData(), true);
            if ($beforeData['close'] != $close) {
              $data = ['before_buyer' => $beforeData['price'], 'current_price' => $ex->getCurrentPrice('ADAUSDT')];
              $data['percent'] = $ex->percentIncreate($data['before_buyer'], $data['current_price']) . '%';
              $activity->setOutcome(self::SELL);
              $activity->setData(json_encode($data));
              $this->helper->updateActivityForSeller($activity);
              $text = ' ready for seller';
              Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
            }
          }
        }
        else {
          if ($this->checkBuyRSIByTimes($rsiLines, 3)) {
            // buy at here
            // $uuid, $uid, $class, $exchange, $outcome, $data
            $data = ['price' => $ex->getCurrentPrice('ADAUSDT'), 'close' => $close, 'RSI' => $currentRSILine];
            $this->helper->insertActivity(Uuid::uuid4()->toString(), 1, 'App\Command\BuyCommand', 'bn', self::BUY, $data);
            $text .= ' ready for buyer';
            Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
          }
        }

        $currentMinute = date('i');
        if ($minute != $currentMinute) {
          $minute = $currentMinute;
          Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $text]);
        }
      }
      catch (\Exception $e) {
        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $e->getMessage()]);
      }
    });


    return new Response('ok');
  }

  //Should be
  //45 => 33.45528781 = same
  //46 => 31.0530724 = same
  //47 => 32.30931073 = same
  //48 => 39.26151545 greatest
  //49 => 36.92756859 buy at here
  public function checkBuyRSI($prevPrevRSI, $preRSI, $RSI, &$message) {
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

    // Current RSI is greater than every prev RSIs
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
  public function checkSellRSI($prevPrevRSI, $preRSI, $RSI, &$message) {
    if (intval($prevPrevRSI) < 53) {
      return 0;
    }

    if (intval($preRSI) < 53) {
      return 0;
    }

    if ($prevPrevRSI < $preRSI) {
      $message .= ' Sell: Case 1 prevprev RSI '  . $prevPrevRSI .  ' <  prev RSI ' . $preRSI;
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
      $message .= ' Sell: Case 3' . ' RSI: ' . $RSI . ' > prevPrevRSI: ' . $prevPrevRSI . ' and RSI ' . $RSI .  ' > prevRSI: ' . $preRSI;
      return 0;
    }

    return 1;
  }

  // selll every
  //94 => 51.51665575
  //95 => 50.66986313
  //99 => 51.18019162
  //98 => 49.29901465
  //97 => 48.13925589
  //96 => 46.54981709
  //    `
  //   `
  //  `
  // `
  public function checkSellRSIByTimes($candles, $times = 3) {
    $current = null;
    //
    for($times; $times > 0; $times--) {
      $current = $current ??  array_pop($candles);
      $previous = array_pop($candles);
      if ($previous < $current) {
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
  // `
  //  `
  //   `
  //    `
  public function checkBuyRSIByTimes($candles, $times = 5) {
    $current = null;
    for($times; $times > 0; $times--) {
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
    $report = [
      "symbol" => "ADAUSDT",
      "side" => "BUY",
      "orderType" => "LIMIT",
      "quantity" => "100",
      "price" => "0.00175760",
      "executionType" => "TRADE",
      "orderStatus" => "FILLED",
      "rejectReason" => "NONE",
      "orderId" => 47356159,
      "clientOrderId" => "web_91e42de413cc4787a1f79390868567a7",
      "orderTime" => 1526095666390,
      "eventTime" => 1526095666393
    ];
    $price =  $report['price'];
    $quantity = $report['quantity'];
    $symbol = $report['symbol'];
    $side = $report['side'];
    $orderStatus = $report['orderStatus'];
    $orderType = $report['orderType'];
    $orderId = $report['orderId'];
    $orderTime = date('d/M/Y', $report['orderTime']);

    $activity = $this->helper->findActivityByOrderId($orderId = 'Bbq1qfInQCRb8DOinYMKmf');
    $className =  $activity->getClass();
    $command = new $className();
    $data = json_decode($activity->getData(), true);
    $command->addOjbect($data);
    $exchange = $this->helper->getExchange($activity->getExchange(), $activity->getUid());
    if (count($data) == 6) {
      $errorMessage = $exchange->stopLoss($symbol, $command->quantity, $command->limit, $command->stop);
      if (isset($errorMessage['code'])) {
        $s = "S: {$symbol} Q: {$quantity} L: {$command->limit} S: {$command->stop}";
        $errorMessage = 'Stop limit has error message: ' . $errorMessage['msg'] . ' info:  ' .  $s;
        dump($errorMessage);
//        Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $errorMessage]);
      }
    }
//    $symbolInfo = $exchange->getSymbolInfomation($symbol);
//    $symbol = $exchange->renderSymbolNamebySymbol($symbolInfo);
//    $message = $this->templateService->renderOrderInfo($symbol, $side, $orderStatus, $price, $quantity, $orderTime, $symbolInfo['baseAsset'], $symbolInfo['quoteAsset']);

//    $text = $this->getText();
//    $userName = $this->getUserName();

//    case 1
//    $text = '/bnBuy adausdt 100 0.32';
//    $userName = 'phuongbui1988';

//    case 2
//    $text = '/zHYzJcnp7SKU4Uz3e2beug';

//    case 3
//    $text = '/price ada';

//  case 4
//    $text = '/bl';

//    case 5
//    $text = 'bnbuy trx 200 0.00000774 sl 0.00000700 0.00000650';
//    $userName = 'phuongbui1988';

//  case 6
//    $text = '/LaMQmWcDf6EwhxJzbjfesb';
//    $userName = 'phuongbui1988';
//
//    $userId = $this->helper->findUserByUserName($userName)->getId();
//    $messages = $this->helper->detach($text, $userId);
//
//    $messages = !is_array($messages) ? $messages : json_encode($messages);
//    $result = Request::sendMessage(['chat_id' => $this->botChatId, 'text' => $messages]);

    return new Response('ok');
  }
}
