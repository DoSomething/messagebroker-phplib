<?php
/**
 * Test coverage for the MessageBroker untility class.
 */

namespace DoSomething\MessageBroker;

use DoSomething\MB_Toolbox\MB_Configuration;
use DoSomething\MessageBroker\MessageBroker;

/**
 * Class MessageBroker
 *
 * @package DoSomething\MessageBroker
 */
class MessageBroker extends \PHPUnit_Framework_TestCase
{
    
    /**
     * @var object MessageBroker.
     */
    public $mb;
    
    /**
     * Common functionality to all tests. Load configuration settings and properties.
     */
    public function setUp()
    {

        require_once __DIR__ . '/MessageBrokerTest.config.inc';
        $this->mb = new MessageBroker();
    }

    /**
     *
     */
    public function testPublish()
    {
    }

    /**
     *
     */
    public function testConsume()
    {
    }

    /**
     *
     */
    public function testSendAck()
    {
    }

    /**
     *
     */
    public function testSendNack()
    {
    }
    
    /**
     *
     */
    public function testSetupExchange()
    {
    }
    
    /**
     *
     */
    public function testSetupQueue()
    {
    }
}
