<?php

namespace App\Tests\Helper;
use App\Command\BuyCommand;
use App\Helper\TradingHelper;
use PHPUnit\Framework\TestCase;

class TradingHelperTest extends TestCase
{
  private $trading;
  public function setUp()
  {
    $this->trading = new TradingHelper();
  }

  public function testValidateExchange()
  {
    $this->trading = new TradingHelper();
    $sign = 'something';
    $this->assertFalse($this->trading->validateExchange($sign));
    $messages = $this->trading->getMessage();
    $this->assertEquals("The exchange not found", reset($messages));

    $sign = 'Bn';
    $this->assertTrue($this->trading->validateExchange($sign));
  }


  public function testDetach()
  {
    $buy = $this->getMockBuilder(BuyCommand::class)
                 ->disableOriginalConstructor()
                 ->setMethods(['validate', 'process'])
                 ->getMock();

    $buy->method('validate')
        ->willReturn(true);

    $buy->method('process')
      ->willReturnCallback(function () {
        return true;
      });

    $trading = $this->getMockBuilder(TradingHelper::class)
                 ->disableOriginalConstructor()
                 ->setMethods(['validateExchange'])
                 ->getMock();

    // Configure the stub.
    $trading->method('validateExchange')
         ->willReturn(true);

    $message = $trading->detach('bnbuy', $buy);
    $this->assertContains('Completed', $message);
  }
}
