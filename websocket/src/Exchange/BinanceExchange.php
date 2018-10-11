<?php

namespace App\Exchange;

use App\Command\BaseCommand;
use Binance\API;

class BinanceExchange
{
  private $api;
  private $expecials = ['BTC', 'ETH', 'USDT'];
  const LIMIT = 50;
  const EXCHANGE_FEE = 0.1;

  public function __construct($binanceKey, $binanceSecret)
  {
    $this->api = new API($binanceKey, $binanceSecret, ["useServerTime" => true]);
  }

  public function buy($symbol, $quantity, $price) {
    return $this->api->buy($symbol, $quantity, $price);
  }

  public function getCurrentQuantityBySymbol($baseAsset, $percent = 100) {
    $balances = $this->api->balances();
    $balance = $balances[$baseAsset];
    return ($balance['available'] * $percent) / 100;
  }

  public function calculateQuantity($symbolDetail = 'BTC', $quantity) {
    return ($percent = BaseCommand::isPercent($quantity))
      ? $this->getCurrentQuantityBySymbol($symbolDetail, $percent)
      : $quantity;
  }

  public function sell($symbol, $quantity, $price)
  {
    return $this->api->sell($symbol, $quantity, $price);
  }

  public function stopLoss($symbol, $quantity, $price, $stop) {
    $flags['stopPrice'] = $stop;
    return $this->api->sell($symbol, $quantity, $price, 'STOP_LOSS_LIMIT', $flags);
  }

  public function getSymbolInfomation($symbol)
  {
    $info = $this->api->exchangeInfo();
    foreach ($info['symbols'] as $symbolInfo) {
      if ($symbolInfo['symbol'] == $symbol) {
        return $symbolInfo;
      }
    }
  }

  public function getCurrentPrice($symbol)
  {
    $prices = $this->api->prices();
    return (isset($prices[$symbol])) ? $prices[$symbol] : 0;
  }

  public function renderSymbolNamebySymbol($symbol) {
    return $symbol['baseAsset'] . '-' . $symbol['quoteAsset'];
  }

  public function getPrevDay($symbol)
  {
    $symbolInfo = $this->getSymbolInfomation($symbol);
    $results = $this->api->prevDay($symbol);
    $results['commandName'] = $this->renderSymbolNamebySymbol($symbol);
    $results['quoteAsset'] = $symbolInfo['quoteAsset'];
    return $results;
  }

  public function setLog($current, $result, $symbol, $currentPrice) {
    if ($result['isBuyer'] === false) {
      return $current;
    }

    if (!$current) {
      return [
        'result' => $result,
        'symbol' => $symbol,
        'currentPrice' => $currentPrice
      ];
    }

    if ($current['result']['time'] < $result['time']) {
      return [
        'result' => $result,
        'symbol' => $symbol,
        'currentPrice' => $currentPrice
      ];
    }

    return $current;
  }

  public function getHistoryInfo($symbol) {
    $logs = [];
    foreach($this->expecials as $expecial) {
      // case BTC == BTC
      if ($symbol == $expecial) {
        continue;
      }

      $results = $this->api->history($symbol . $expecial, self::LIMIT);
      $currentPrice = $this->getCurrentPrice($symbol . $expecial);
      if (!isset($results['msg'])) {
        foreach ($results as $result) {
          $logs = $this->setLog($logs, $result, $symbol . $expecial, $currentPrice);
        }
      }
    }

    if (isset($logs['result']['price']) && $logs['currentPrice'] > 0) {
      $logs['lastestPrice'] = $logs['result']['price'];
      $logs['percent'] = $this->percentIncreate($logs['lastestPrice'], $logs['currentPrice']);
    }
    return $logs;
  }

  public function getBalance()
  {
    $balances = $this->api->balances();
    $results = [];
    foreach($balances as $symbol => $balance) {
      if (ceil($balance['available']) > 0) {
        $results[$symbol] = $balance;
        $results[$symbol]['detail'] = $this->getHistoryInfo($symbol);
      }
    }

    $results = $this->formatBalance($results);
    return $results;
  }

  public function cancelOrder($oderIndex)
  {
    $orders = $this->api->openOrders();
    $order = $orders[$oderIndex - 1];
    $this->api->cancel($order['symbol'], $order['orderId']);
    unset($orders[$oderIndex - 1]);
    return $this->formatOpenOrders($orders);
  }

  public function formatOpenOrders($orders) {
    $results = [];
    foreach($orders as $order) {
      $symbol = $this->getSymbolInfomation($order['symbol']);
      $results[] = [
        'side' => $order['side'],
        'type' => $order['type'],
        'symbolName' => $this->renderSymbolNamebySymbol($symbol),
        'price' => $order['price'],
        'stopPrice' => $order['stopPrice'] > 0 ? $order['stopPrice'] : null,
        'total' => $order['origQty'],
      ];
    }
    return $results;
  }

  public function getOpenOrders()
  {
    $orders = $this->api->openOrders();
    return $this->formatOpenOrders($orders);
  }

  public function formatBalance($balances) {
    $results = [];

    foreach($balances as $key => $balance) {
      if (!$balance['detail']) {
        continue;
      }

      $result = [];
      $commandName = str_replace($key, $key . '-', $balance['detail']['symbol']);
      list($havingAsset, $targetAsset) = explode("-", $commandName);
      $result['commandName'] = $commandName;
      $result['profit'] = $balance['detail']['percent'];
      $result['est'] = round($balance['available'] * $balance['detail']['currentPrice'], 5);
      $result['lastBoughtPrice'] = $balance['detail']['lastestPrice'];
      $result['currentPrice'] = $balance['detail']['currentPrice'];
      $result['total'] = $balance['available'];
      $result['available'] = $balance['onOrder'];
      $result['havingAsset'] = $havingAsset;
      $result['targetAsset'] = $targetAsset;

      $results[]= $result;
    }

    return $results;
  }

  public function percentIncreate($currentPrice, $targetPrice, $exchangeFee = self::EXCHANGE_FEE)
  {
    $increase = $targetPrice - $currentPrice;
    return round($increase / $currentPrice * 100, 2) - $exchangeFee;
  }

  public function getConfirmInformation($symbol, $quantity, $price)
  {
    $symbolInfo = $this->getSymbolInfomation($symbol);
    $quantity = $this->calculateQuantity($symbolInfo['baseAsset'], $quantity);

    $currentPrice = $this->getCurrentPrice($symbol);
    $diff = $this->percentIncreate($currentPrice, $price);
    return [
      'commandName' => $this->renderSymbolNamebySymbol($symbolInfo),
      'exchange' => 'binance',
      'symbol' => $symbol,
      'quantity' => $quantity,
      'price' => $price,
      'diff' => $diff,
      'currentPrice' => $currentPrice,
      'havingAsset' => $symbolInfo['quoteAsset'],
      'targetAsset' => $symbolInfo['baseAsset'],
      'total' => $quantity * $price,
    ];
  }
}
