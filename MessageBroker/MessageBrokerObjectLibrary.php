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

      if(!$this->connection->isConnected()) {
        throw new Exception('Connection failed.');
      }

    }

  /**
   * produceTransactional - called to trigger production of a transactional
   * entry in an exchange / queue.
   *
   * @param array $data
   *  Message data to be passed through broker
   * 
   */
  public function produceTransactional($data) {

$bla = TRUE;
if ($bla) {
  $bla = TRUE;
}

    $exchangeName = getenv("TRANSACTIONAL_EXCHANGE");

    // Collect RabbitMQ connection details
    $connection = $this->connection;
    $channel = $connection->channel();

    // Exchange
    $channel = getExchange($exchangeName, $channel);

    // Mark messages as persistent by setting the delivery_mode = 2 message property
    // Supported message properties: https://github.com/videlalvaro/php-amqplib/blob/master/doc/AMQPMessage.md
    $payload = new AMQPMessage($data, array('delivery_mode' => 2));

    // @todo: Exchange keys based on the transaction type:
    // - user.password_reset.transactional
    // - user.signup.transactional
    // - campaign.signup.transactional
    // - campaign.report_back.transactional
    // $keys = setKeys($data);
    $keys = 'campaign.signup.transactional';

    $channel->basic_publish($payload, $exchangeName, $keys);

    $channel->close();
    $connection->close();

  }

  /**
   * getExchange - common create exchange functionality used to ensure exchange
   * settings are the same for both producers and consumers. A producer will
   * never communicate with a queue directly, it's always through an exchange.
   *
   * @param string $exchangeName
   *  Name of the exchange that will bind to a queue
   *
   * @param object $channel
   *  The channel connection that the queue should use.
   *
   * @return object $channel
   *
   */
  protected function getExchange($exchangeName, $channel) {

    $exchange_options = array(
      'passive' => FALSE,
      'durable' => TRUE,
      'auto_delete' => FALSE,
      'nowait' => TRUE
    );

    // $ rabbitmqctl list_exchanges
    $channel->exchange_declare($exchangeName,
                              'direct',
                              $exchange_options['passive'],
                              $exchange_options['durable'],
                              $exchange_options['auto_delete']);

    return $channel;
  }

  /**
   * consumeTransactional - called to setup consumer script of transactional
   * message entries in the queue.
   */
  public function consumeTransactional() {

    // Stub out consume transactional

  }

  /**
   * getQueue - common create queue functionality used to ensure queue settings
   * are the same for both producers and consumers. If the queue already exsists
   * the details of the queue will be return.
   *
   * @param string $queueName
   *  Name of the queue
   *
   * @param object $channel
   *  The channel connection that the queue should use.
   *
   * @param array $param
   *  Future use, possible flag for different queues.
   *
   * @return object
   *
   */
  protected function getQueue(string $queueName, $channel, $param) {

    /*
     * passive:
     *
     * durable: Keep queue even if RabbitMQ  is shut down. Note that messages
     * must also be marked as durable in order for messages in a durable queue
     * to survive a queue restart.
     *
     * exclusive:
     *
     * auto_delete:
     */

    $queue_options = array(
      'passive'     => FALSE,
      'durable'     => TRUE,
      'exclusive'   => FALSE,
      'auto_delete' => FALSE,

      'nowait'      => 'false',
      'arguments'   => NULL,
      'ticket'      => NULL,
    );

    // Old produceTransactional call
    // Must be the same for both producer and consumer
    // $channel->queue_declare($queueName, false, true, false, false);

    $channel->queue_declare($queueName,
      $queue_options['passive'],
      $queue_options['durable'],
      $queue_options['exclusive'],
      $queue_options['auto_delete']);

    return $channel;
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