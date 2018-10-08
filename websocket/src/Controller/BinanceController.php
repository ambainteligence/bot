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
  const TIME = 15 * 60; // 3 minutues
  const PREVIOUS_CANDLES = 2; // recheck previous candles

  const BUY = 'buy';
  const SELL = 'sell';

  const SHOULD_SELL = -1;
  const SHOULD_BUY = 1;

  private $botApiKey;
  private $botUserName;
  private $botChatId;
  private $binanceKey;
  private $binanceSecret;
  private $binance;
  private $helper;
  private $templateService;

  use Strategies;

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
    ini_set('trader.real_precision', '8');
    $api = $this->binance;

    //=============
    $minute = time();


//    $candles = $this->binance->candlesticks('ADAUSDT', "1h", 50);
//    $data = $this->changeCandlesToData($candles);
//    $text = '';
//    $bb_rsi = $this->phuongb_bowhead_macd('', $data, false, $text);
////    dump($bb_rsi);
//    dump($this->getResultOfStrategy($candles, 'phuongb_bowhead_macd', 2));

    $api->chart(["ADAUSDT"], "1h", function($api, $symbol, $chart) use(&$minute) {
        $currentChart = array_pop($chart);
        $currentClose = $currentChart['close'];
        //      array_pop($chart);
        $preCurrentClose = array_pop($chart);
        $preCurrentClose = $preCurrentClose['close'];

      $currentTime = time();
      if ($currentTime < ($minute + self::TIME)) {
        return ;
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
      'low'   => $low
    ];
  }

  public function getCandleBySign($candles, $sign) {
    $results = [];
    foreach($candles as $candle) {
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
  public function checkSellRSIByTimes($candles, $times = 3) {
    $current = null;
    //
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

  // buy every
  //94 => 51.51665575
  //95 => 50.66986313
  //96 => 46.54981709
  //97 => 48.13925589
  //98 => 49.29901465
  //99 => 51.18019162
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
    $candles = $this->binance->candlesticks('ADAUSDT', "5m", 50);
    $close = $this->getCandleBySign($candles, 'close');
    $high = $this->getCandleBySign($candles, 'high');
    $low = $this->getCandleBySign($candles, 'low');

    $data = [
      'close' => $close,
      'high'  => $high,
      'low'   => $low
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
