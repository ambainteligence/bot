<?php

include_once 'public/index.php';

use Binance\API;
use Longman\TelegramBot\Telegram;
use Longman\TelegramBot\Request;

$binanceKey = ($_SERVER['BINANCE_KEY']) ?? null;
$binanceSecret = ($_SERVER['BINANCE_SECRET']) ?? null;
$binance = new API($binanceKey, $binanceSecret, ["useServerTime" => true]);

$botUserName = ($_SERVER['BOT_USER_NAME']) ?? null;
$botChatId = ($_SERVER['CHAT_ID']) ?? null;

$telegram = new Telegram($botApiKey, $botUserName);

$binance->chart(["BNBBTC"], "15m", function($binance, $symbol, $chart) {
  Request::sendMessage(['chat_id' => $this->botChatId, 'text' => 'ping me 2']);
});

