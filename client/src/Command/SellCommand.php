<?php

namespace App\Command;

class SellCommand extends BaseCommand
{
  const COMMAND_NAME = 'Bnsell';

  const TOTAL = 3;
  public $symbol;
  public $quantity;
  public $price;
  public $stop;
  public $limit;
  public $args;

  private $baseCoin = ['btc', 'eth', 'usdt'];

  public function isStopLoss($args) {
    if (in_array("sl", $args)) {
      return true;
    }
    return false;
  }

  public function validate($args) {
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
    if (!is_numeric($quanlity) && !$this->isPercent($quanlity)) {
      $this->message->log('Quantity has to number or percent. Current value is ' . $quanlity);
      return false;
    }

    $price = array_shift($args);
    if (!is_numeric($price)) {
      $this->message->log('Price has to number. Current value is ' . $price);
      return false;
    }

    return true;
  }

  public function addOjbect(&$args, $exchange)
  {
    $this->args = $args;

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

    $this->quantity = $exchange->calculateQuantity($this->symbol, $this->quantity);
    $args = $this->args;
    $args[1] = $this->quantity;
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
    $result = $exchange->sell($this->symbol, $this->quantity, $this->price);
    return $result['clientOrderId'] ?? null;
  }
}
