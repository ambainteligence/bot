<?php

namespace App\Tests\Exchange;
use App\Command\BuyCommand;
use App\Exchange\BinanceExchange;
use PHPUnit\Framework\TestCase;

class BuyCommandTest extends TestCase
{

  public function testValidate()
  {
    $args = ['adausdt', 10];
    $buy = new BuyCommand();
    $this->assertFalse($buy->validate($args));
    $messages = $buy->message->getMessages();
    $this->assertEquals('Not enough the arguments. Eg: Bnbuy adausdt 100 0.3', reset($messages));

    $args = ['adausdt', 'a', 0.3];
    $buy = new BuyCommand();
    $this->assertFalse($buy->validate($args));
    $messages = $buy->message->getMessages();
    $this->assertEquals('Quantity has to number. Current value is a', reset($messages));

    $args = ['adausdt', 10, 'a'];
    $buy = new BuyCommand();
    $this->assertFalse($buy->validate($args));
    $messages = $buy->message->getMessages();
    $this->assertEquals('Price has to number. Current value is a', reset($messages));

    $args = ['usdt', 10, 0.3];
    $buy = new BuyCommand();
    $this->assertFalse($buy->validate($args));
    $messages = $buy->message->getMessages();
    $this->assertEquals('Please find a couple coin for trading. Current value is usdt', reset($messages));

    $args = ['ada', 10, 0.3];
    $buy = new BuyCommand();
    $this->assertTrue($buy->validate($args));

    $args = ['adausdt', 10, 0.3];
    $buy = new BuyCommand();
    $this->assertTrue($buy->validate($args));
  }


  public function testAddOjbect()
  {
    $args = ['adausdt', 10, 0.3];
    $buy = new BuyCommand();
    $buy->addOjbect($args);
    $this->assertEquals('ADAUSDT', $buy->symbol);
    $this->assertEquals(10, $buy->quantity);
    $this->assertEquals(0.3, $buy->price);

    $args = ['ada', 10, 0.3];
    $buy = new BuyCommand();
    $buy->addOjbect($args);
    $this->assertEquals('ADABTC', $buy->symbol);
  }

}
