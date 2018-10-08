<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Longman\TelegramBot\Telegram;

class TelegramController extends Controller
{
  private $botApiKey;
  private $botUserName;

  public function __construct()
  {
      $this->botApiKey = ($_SERVER['BOT_API_KEY']) ?? null;
      $this->botUserName = ($_SERVER['BOT_USER_NAME']) ?? null;
  }

  public function setDomainName()
  {
    // Create Telegram API object
    $telegram = new Telegram($this->botApiKey, $this->botUserName);
    // Set webhook
    $result = $telegram->setWebhook('https://' . $_SERVER['HTTP_HOST']);
    if ($result->isOk()) {
      return new Response('Ok');
    }

    return new Response('False');
  }
}
