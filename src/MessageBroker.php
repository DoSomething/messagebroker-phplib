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
  public $transactionalExchange;
  public $transactionalQueue;
  public $userRegistrationQueue;

  /**
    * Constructor
    *
    * @param array $credentials
    *   RabbitMQ connection details
    *   
    * @return object
    */
    public function __construct($credentials = array(), $config = array()) {

      // Cannot continue if the library wasn't loaded.
      if (!class_exists('PhpAmqpLib\Connection\AMQPConnection') || !class_exists('PhpAmqpLib\Message\AMQPMessage')) {
        throw new Exception("Could not find php-amqplib. Please download and
          install from https://github.com/videlalvaro/php-amqplib/tree/v1.0. See
          rabbitmq INSTALL file for more details.");
      }

      // Use enviroment values set in config.inc if credentials not set
      if (empty($credentials['host']) || empty($credentials['port']) || empty($credentials['username']) || empty($credentials['password'])) {
        if (!file_exists(dirname(dirname(__FILE__)) . '/config.inc')) {
          throw new Exception("Could not find config.inc. Please make a copy of config.inc.example
            and add the appropreate settings defined within the file.");
        }
        else {
          require_once(dirname(dirname(__FILE__)) . '/config.inc');
        }
        $credentials['host'] = getenv("RABBITMQ_HOST");
        $credentials['port'] = getenv("RABBITMQ_PORT");
        $credentials['username'] = getenv("RABBITMQ_USERNAME");
        $credentials['password'] = getenv("RABBITMQ_PASSWORD");
        
        if (getenv("RABBITMQ_VHOST") != FALSE) {
          $credentials['vhost'] = getenv("RABBITMQ_VHOST");
        }
        else {
          $credentials['vhost'] = '';
        }

        // Set config vars
        $config['transactionalExchange'] = getenv("TRANSACTIONAL_EXCHANGE");

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

      // Set config vars for use in methods
      $this->transactionalExchange = $config['transactionalExchange'];

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

    $exchangeName = $this->transactionalExchange;
    $transactionalQueue = $this->transactionalQueue = 'transactionalQueue';
    $userRegistrationQueue = $this->userRegistrationQueue = 'userRegistrationQueue';

    // Confirm config.inc values set
    if (!$exchangeName) {
      throw new Exception('config.inc settings missing, exchange and/or
        queue name not set. If this is on a Drupal website check the settings
        at admin/config/services/message-broker-producer/mq-settings');
    }

    // Collect RabbitMQ connection details
    $connection = $this->connection;
    $channel = $connection->channel();

    // Exchange
    $channel = $this->setupExchange($exchangeName, $channel);

    // Queues
    $channel = $this->setupQueue($transactionalQueue, $channel, NULL);
    $channel = $this->setupQueue($userRegistrationQueue, $channel, NULL);

    // Bind exchange to queue for 'transactional' key
    // queue_bind($queue, $exchange, $routing_key="", $nowait=false, $arguments=null, $ticket=null)
    $channel->queue_bind($transactionalQueue, $exchangeName, '*.*.transactional');
    $channel->queue_bind($userRegistrationQueue, $exchangeName, 'user.registration.*');

    // Mark messages as persistent by setting the delivery_mode = 2 message property
    // Supported message properties: https://github.com/videlalvaro/php-amqplib/blob/master/doc/AMQPMessage.md
    $payload = new AMQPMessage($data, array('delivery_mode' => 2));

    // Routing
    $payload_values = json_decode($data);
    switch ($payload_values->activity) {
      case 'campaign_signup':
      case 'campaign-signup':
        $routingKeys = 'campaign.signup.transactional';
        break;
      case 'campaign_reportback':
      case 'campaign-reportback':
        $routingKeys = 'campaign.campaign_reportback.transactional';
        break;
      case 'user_password':
      case 'user-password':
        $routingKeys = 'user.password_reset.transactional';
        break;
      case 'user_register':
      case 'user-register':
        $routingKeys = 'user.registration.transactional';
        break;

      default:
        throw new Exception('Undefined activity "' . $payload_values->activity .
          '" sent to produceTransactional in messagebroker-phplib.');
    }

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