<?php

/*
 * Message Broker class library
 */

// Use AMQP
use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;

class MessageBroker
{
  public $connection = NULL;

  /**
    * Constructor
    *
    * @param array $credentials
    *   RabbitMQ connection details
    *   
    * @return object
    */
    public function __construct($credentials = array()) {

      // Cannot continue if the library wasn't loaded.
      if (!class_exists('PhpAmqpLib\Connection\AMQPConnection') || !class_exists('PhpAmqpLib\Message\AMQPMessage')) {
        throw new Exception("Could not find php-amqplib. Please download and
          install from https://github.com/videlalvaro/php-amqplib/tree/v1.0. See
          rabbitmq INSTALL file for more details.");
      }

      // Use enviroment values set in config.inc if credentials not set
      if (empty($credentials['host']) || empty($credentials['port']) || empty($credentials['username']) || empty($credentials['password'])) {
        require_once(dirname(dirname(dirname(__FILE__))) . '/config.inc');
        $credentials['host'] = getenv("RABBITMQ_HOST");
        $credentials['port'] = getenv("RABBITMQ_PORT");
        $credentials['username'] = getenv("RABBITMQ_USERNAME");
        $credentials['password'] = getenv("RABBITMQ_PASSWORD");
      }

      // Connect
      $this->connection = new AMQPConnection($credentials['host'], $credentials['port'], $credentials['username'], $credentials['password']);
      
      $bla = TRUE;
      if ($bla) {
        $bla = FALSE;
      }

      if(!$this->connection->isConnected()) {
        throw new Exception('Connection failed.');
      }

    }

  /**
   * produceTransactional - called to trigger production of a transactional
   * entry in an exchange / queue.
   *
   * @param array $param
   *  Values used to generate a production entry
   * 
   */
  public function produceTransactional($param) {
    
$bla = TRUE;
if ($bla) {
  $bla = TRUE;
}

    $connection = $this->connection;
    $channel = $connection->channel();

    $queueName = getenv("TRANSACTIONAL_QUEUE");

    // Exchange
    // Use common function to share between producer and consumer
    // $channel->exchange_declare('direct_logs', 'direct', false, false, false);

    // @todo: Move to function to allow producers and consumers to create the
    // same queue. Declare queue as durable, pass third parameter to
    // queue_declare as true. Needs to be set to true to both the producer and
    // consumer queue_declare
    // If the queue name is not set (using temporary random queue name), use
    // list($queue_name, ,) = queue_declare(
    $channel->queue_declare($queueName, false, true, false, false);

    // https://www.rabbitmq.com/tutorials/tutorial-four-php.html
    // $channel->queue_bind($queue_name, 'direct_logs', $severity);

    // Mark messages as persistent by setting the delivery_mode = 2 message property
    // Supported message properties: https://github.com/videlalvaro/php-amqplib/blob/master/doc/AMQPMessage.md
    $payload = new AMQPMessage($param, array('delivery_mode' => 2));

    $channel->basic_publish($payload, '', $queueName);
    // Publish with exchange
    // $channel->basic_publish($msg, 'direct_logs', $severity);

    $channel->close();
    $connection->close();

  }
  
  /**
   * queueStatus - called to gather details on a specific queue status.
   *
   * @param array $queue
   *  Values used to generate a production entry
   * 
   */
  public function queueStatus($queue) {
    
    // list(name, jobs, consumers) = queue_declare(queue=queuename, passive=True)
    
  }
  
}