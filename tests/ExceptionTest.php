<?php
/**
 * Test coverage for Exceptions in MessageBroker utility.
 */

namespace DoSomething\MessageBroker;

use DoSomething\MessageBroker\MessageBroker;

/**
 * Class ExceptionTest
 *
 * @package DoSomething\MessageBroker
 */
class ExceptionTest extends \PHPUnit_Framework_TestCase
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
        // $this->mb = new MessageBroker($credentials);
    }

    /**
     * @covers \DoSomething\MessageBroker\MessageBroker::__construct
     * @uses   \DoSomething\MessageBroker\MessageBroker
     * @expectedException Exception
     */
    public function testException()
    {
        // AMQPStreamConnection and AMQPMessage not loading
        $credentials = [];
        $config = [];
        // $this->mb->__construct($credentials, $config);
    
        // Throw Exception when payload is undefined
        $payload = null;
        // $mb = new MessageBroker($payload);
        // $mb->publish($payload);
    }
    
    
}
