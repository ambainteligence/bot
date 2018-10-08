<?php

namespace App\Command;

class BuyCommand extends BaseCommand
{
  const COMMAND_NAME = 'Bnbuy';

  const TOTAL = 3;
  public $symbol;
  public $quantity;
  public $price;
  public $stop;
  public $limit;

  private $baseCoin = ['btc', 'eth', 'usdt'];

  public function isStopLoss($args) {
    if (in_array("sl", $args)) {
      return true;
    }
    return false;
  }

  public function validate($args) {
    if (!$this->isStopLoss($args) && count($args) != self::TOTAL) {
      $this->message->log('Not enough the arguments. Eg: Bnbuy adausdt 100 0.3');
      return false;
    }

    $symbol = array_shift($args);
    if (!is_string($symbol)) {
      $this->message->log('Symbol has to string. Current value is ' . $symbol);
      return false;
    }

    if (in_array($symbol, $this->baseCoin)) {
      $this->message->log('Please find a couple coin for trading. Current value is ' . $symbol);
      return false;
    }

    $quanlity = array_shift($args);
    if (!is_numeric($quanlity)) {
      $this->message->log('Quantity has to number. Current value is ' . $quanlity);
      return false;
    }

    $price = array_shift($args);
    if (!is_numeric($price)) {
      $this->message->log('Price has to number. Current value is ' . $price);
      return false;
    }

    if (!$this->isStopLoss($args)) {
      return true;
    }

    // extra logic
    if (count($args) != self::TOTAL) {
      $this->message->log('Not enough the arguments. Eg: Bnbuy adausdt 100 0.3 sl 0.00000700 0.00000650');
      return false;
    }

    $sl = array_shift($args);
    if ($sl != 'sl') {
      $this->message->log('Missing sl symbol. Eg:  Bnbuy adausdt 100 0.3 sl 0.00000700 0.00000650');
      return false;
    }

    $stop = array_shift($args);
    if (!is_numeric($price)) {
      $this->message->log('Stop price has to number. Current value is ' . $stop);
      return false;
    }

    $limit = array_shift($args);
    if (!is_numeric($limit)) {
      $this->message->log('Limit has to number. Current value is ' . $limit);
      return false;
    }

    if ($stop < $limit) {
      $this->message->log('Stop have to greater than limit, stop value is ' . $stop . ' limit value is ' . $limit);
      return false;
    }

    return true;
  }

  public function addOjbect($args)
  {
      $this->symbol = array_shift($args);
      $this->quantity = array_shift($args);
      $this->price = array_shift($args);
      $this->format();
      $this->symbol = strtoupper($this->symbol);
      // extra stop-loss
      if ($args) {
        array_shift($args);
        $this->stop =  array_shift($args) ?? null;
        $this->limit =  array_shift($args) ?? null;
      }
  }

  public function export()
  {
    return ['symbol' => $this->symbol, 'quantity' => $this->quantity, 'price' => $this->price];
  }

  public function import(array $data)
  {
    $this->symbol = $data['symbol'];
    $this->quantity = $data['quantity'];
    $this->price = $data['price'];
  }

  private function format()
  {
    $noBtc = (strpos($this->symbol, 'btc') == false);
    $noEth = (strpos($this->symbol, 'eth') == false);
    $noUsdt = (strpos($this->symbol, 'usdt') == false);

    if ($noBtc && $noEth && $noUsdt) {
      $this->symbol .= 'btc';
    }
  }

  public function confirmInfo($exchange) {
    $result = $exchange->getConfirmInformation($this->symbol, $this->quantity, $this->price);
    $result['stop'] = $this->stop;
    $result['limit'] = $this->limit;
    return $result;
  }

  public function process($exchange)
  {
    $result = $exchange->buy($this->symbol, $this->quantity, $this->price);
    return $result['clientOrderId'] ?? null;
  }
}
