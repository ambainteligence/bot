<?php

namespace App\Services;


use Twig\Environment as Twig;

class TemplateService
{
  private $twig;

  public function __construct(Twig $twig) {
    $this->twig = $twig;
  }

  public function renderConfirmTemplete($commandClass, $commandName, $exchange, $symbol, $quantity, $price, $diff, $currentPrice, $havingAsset, $targetAsset, $total, $uuid, $stop, $limit) {
    $type = ($commandClass == 'BuyCommand') ? 'buy' : 'sell';
    return $this->twig->render('confirm_infomation.html.twig', array(
      'type' => $type,
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

  public function renderOpenOrders($orders)
  {
    return $this->twig->render('open_orders.html.twig', [
      'orders' => $orders
    ]);
  }
}
