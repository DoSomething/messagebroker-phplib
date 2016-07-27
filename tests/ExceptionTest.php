<?php
/**
 * Test coverage for Exceptions in MessageBroker utility.
 */

namespace DoSomething\MessageBroker;

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
        $this->mb = new MessageBroker();
    }

    /**
     * @covers \DoSomething\MBC_UserImport\MBC_UserImport_Consumer::canProcess
     * @uses   \DoSomething\MBC_UserImport\MBC_UserImport_Consumer
     * @expectedException Exception
     */
    public function testException()
    {
        // Empty source
        $message = [];
        $this->mbcUserImportConsumer->canProcess($message);

        // Unsupported source
        $message['source'] = 'TeenLife';
        $this->mbcUserImportConsumer->canProcess($message);
    }
}
