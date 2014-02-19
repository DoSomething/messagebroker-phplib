<?php

/*
 * Message Broker class library
 */

// Load configuration settings - non-Drupal
// Load settings based on arguments passed to sript. It's possible to connect to
// Mandrill using a test key (default) that skips sending messages to actual
// users. The test key uses a test email address set via the Mandrill admin
// dashboard when in test mode. Use "php message-broker-consumer.php 1" to use
// the production key.
if (isset($argv)) {
  $useProductiontKey = $argv[1];
}
else {
  $useProductiontKey = NULL;
}
require_once(dirname(dirname(__FILE__)) . '/config.inc');

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
        $credentials['host'] = getenv("RABBITMQ_HOST");
        $credentials['port'] = getenv("RABBITMQ_PORT");
        $credentials['username'] = getenv("RABBITMQ_USERNAME");
        $credentials['password'] = getenv("RABBITMQ_PASSWORD");
        
        if (getenv("RABBITMQ_DOSOMETHING_VHOST") != FALSE) {
          $credentials['vhost'] = getenv("RABBITMQ_DOSOMETHING_VHOST");
        }
        else {
          $credentials['vhost'] = '';
        }
        
      }

      // Connect - AMQPConnection(HOST, PORT, USER, PASS, VHOST);
      if ($credentials['vhost'] != '') {
        $this->connection = new AMQPConnection(
          $credentials['host'],
          $credentials['port'],
          $credentials['username'],
          $credentials['password'],
          $credentials['vhost']);
      }
      else {
        $this->connection = new AMQPConnection(
          $credentials['host'],
          $credentials['port'],
          $credentials['username'],
          $credentials['password']);
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

    $exchangeName = getenv("TRANSACTIONAL_EXCHANGE");
    $queueName = getenv("TRANSACTIONAL_QUEUE");

    // Confirm config.inc values set
    if (!$exchangeName || !$queueName) {
      throw new Exception('config.inc settings missing, exchange and/or queue name not set.');
    }

    // Collect RabbitMQ connection details
    $connection = $this->connection;
    $channel = $connection->channel();

    // Queue
    $channel = $this->setupQueue($queueName, $channel, NULL);

    // Exchange
    $channel = $this->setupExchange($exchangeName, $channel);

    // Bind exchange to queue for 'transactional' key
    // queue_bind($queue, $exchange, $routing_key="", $nowait=false, $arguments=null, $ticket=null)
    $channel->queue_bind($queueName, $exchangeName, '*.*.transactional');

    // Mark messages as persistent by setting the delivery_mode = 2 message property
    // Supported message properties: https://github.com/videlalvaro/php-amqplib/blob/master/doc/AMQPMessage.md
    $payload = new AMQPMessage($data, array('delivery_mode' => 2));

    // @todo: Set keys based on the transaction type to direct message entries
    // in different queues based on values passed in $data:
    // - user.password_reset.transactional
    // - user.signup.transactional
    // - campaign.signup.transactional
    // - campaign.report_back.transactional
    $routingKeys = 'campaign.signup.transactional';

    // basic_publish($msg, $exchange="", $routing_key="", $mandatory=false, $immediate=false, $ticket=null)
    $channel->basic_publish($payload, $exchangeName, $routingKeys);

    $channel->close();
    $connection->close();
  }

  /**
   * setupExchange - common create exchange functionality used to ensure exchange
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
  public function setupExchange($exchangeName, $channel) {

    /*
     * passive: The exchange will survive server restarts
     *
     * durable: The exchange won't be deleted once the channel is closed
     *
     * auto_delete: The exchange won't be deleted once the channel is closed.
     *   The exchange will survive server restarts
     *
     * delete the exchange when something has bound to it and then
     * everything has unbound from it", which again only really makes sense in
     * the context of exchange to exchange bindings
     * https://groups.google.com/forum/#!topic/rabbitmq-discuss/YcM_zElQcq8
     */

    $exchange_options = array(
      'passive' => FALSE,
      'durable' => TRUE,
      'auto_delete' => FALSE
    );

    // $ rabbitmqctl list_exchanges
    $channel->exchange_declare($exchangeName,
                              'topic',
                              $exchange_options['passive'],
                              $exchange_options['durable'],
                              $exchange_options['auto_delete']);

    return $channel;
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
  public function setupQueue($queueName, $channel, $param = NULL) {

    /*
     * passive: If set, the server will reply with Declare-Ok if the queue
     * already exists with the same name, and raise an error if not. The client
     * can use this to check whether a queue exists without modifying the server
     * state. When set, all other method fields except name and no-wait are
     * ignored. A declare with both passive and no-wait has no effect.
     * Arguments are compared for semantic equivalence.
     *
     * durable: Keep queue even if RabbitMQ is shut down. Note that messages
     * must also be marked as durable in order for messages in a durable queue
     * to survive a queue restart.
     *
     * exclusive: Yhe queue can be accessed in other channels
     *
     * auto_delete: The queue won't be deleted once the channel is closed. If
     * set, the queue is deleted when all consumers have finished using it.
     */
    $queue_options = array(
      'passive'     => FALSE,
      'durable'     => TRUE,
      'exclusive'   => FALSE,
      'auto_delete' => FALSE,
    );

    $channel->queue_declare($queueName,
      $queue_options['passive'],
      $queue_options['durable'],
      $queue_options['exclusive'],
      $queue_options['auto_delete']);

    return $channel;
  }

}