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


}
