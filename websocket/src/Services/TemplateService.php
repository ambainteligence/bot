<?php

namespace App\Services;


use Twig\Environment as Twig;

class TemplateService
{
  private $twig;

  public function __construct(Twig $twig) {
    $this->twig = $twig;
  }

  public function renderConfirmTemplete($commandName, $exchange, $symbol, $quantity, $price, $diff, $currentPrice, $havingAsset, $targetAsset, $total, $uuid, $stop, $limit) {
    return $this->twig->render('confirm_infomation.html.twig', array(
      'command_name' => $commandName,
      'exchange' => $exchange,
      'symbol' => $symbol,
      'quantity' => $quantity,
      'price' => $price,
      'percent' => $diff,
      'current_price' => $currentPrice,
      'having_asset' => $havingAsset,
      'target_asset' => $targetAsset,
      'total' => $total,
      'uuid' => $uuid,
      'stop' => $stop,
      'limit' => $limit
    ));
  }

  public function renderPriceSymbol($commandName, $targetAsset, $currentPrice, $bid, $ask, $low, $high, $percent, $vol)
  {
    return $this->twig->render('price_symbol.html.twig', [
      'command_name'  => $commandName,
      'target_asset'  => $targetAsset,
      'current_price' => $currentPrice,
      'bid'           => $bid,
      'ask'           => $ask,
      'low'           => $low,
      'high'          => $high,
      'percent'       => $percent,
      'vol'           => $vol
    ]);
  }

  public function renderBalances($balances)
  {
    return $this->twig->render('balance_info.html.twig', [
      'balances' => $balances
    ]);
  }

  public function renderOrderInfo($symbol, $side, $orderStatus, $price, $quantity, $orderTime, $baseAsset, $quoteAsset)
  {
    return $this->twig->render('order_info.html.twig', [
      'symbol' => $symbol,
      'side' => $side,
      'order_status' => $orderStatus,
      'price' => $price,
      'quantity' => $quantity,
      'total' => $price * $quantity,
      'order_time' => $orderTime,
      'base_asset' => $baseAsset,
      'quote_asset' => $quoteAsset
    ]);
  }
}
