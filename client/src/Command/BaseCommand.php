<?php

namespace App\Command;

use App\Message\ErrorMessage;


class BaseCommand
{
  public $message;

  public function __construct()
  {
    $this->message = new ErrorMessage();
  }

  public static function isPercent($quantity) :?string {
    if (preg_match("/[0-9]+%/", $quantity, $matches)) {
      return rtrim($matches[0], '%') ;
    }
    return false;
  }


}
