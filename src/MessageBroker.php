<?php
/*
 * Message Broker (Quicksilver) class library. Used as wrapper of php-amqplib to abstract some of the implimentation
 * requiremenrts.
 */

namespace DoSomething\MessageBroker;

// Use AMQP
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use \Exception;

class MessageBroker
{

    /**
     * AMQPConnection
     *
     * @var object
     */
    public $connection;

    /**
     * Collection of consume options.
     *
     * @var array
     */
    private $consumeOptions;

    /**
     * Collection of exchange options.
     *
     * @var array
     */
    private $exchangeOptions;

    /**
     * Collection of queue options.
     *
     * @var array
     */
    private $queueOptions;

    /**
     * Current channel to consume.
     *
     * @var AMQPChannel
     */
    private $channel;

    /**
     * Constructor
     *
     * @param array $credentials
     *   RabbitMQ connection details
     *
     * @return object
     */
    public function __construct($credentials = array(), $config = [])
    {

        // Cannot continue if the library wasn't loaded.
        if (!class_exists(AMQPStreamConnection::class) || !class_exists(AMQPMessage::class)) {
            throw new Exception("Could not find required php-amqplib library. Please download and
                install from https://github.com/php-amqplib/php-amqplib.");
        }

        // Connect - AMQPConnection(HOST, PORT, USER, PASS, VHOST);
        if ($credentials['vhost'] != '') {
            $this->connection = new AMQPStreamConnection(
                $credentials['host'],
                $credentials['port'],
                $credentials['username'],
                $credentials['password'],
                $credentials['vhost']
            );
        } else {
            $this->connection = new AMQPStreamConnection(
                $credentials['host'],
                $credentials['port'],
                $credentials['username'],
                $credentials['password']
            );
        }
        $this->consumeOptions = [
            'consumer_tag' => isset($config['consume']['consumer_tag']) ? $config['consume']['consumer_tag'] : '',
            'no_local' => isset($config['consume']['no_local']) ? $config['consume']['no_local'] : false,
            'no_ack' => isset($config['consume']['no_ack']) ? $config['consume']['no_ack'] : true,
            'exclusive' => isset($config['consume']['exclusive']) ? $config['consume']['exclusive'] : false,
            'nowait' => isset($config['consume']['nowait']) ? $config['consume']['nowait'] : false,
        ];

        $this->exchangeOptions = [
            'name' => isset($config['exchange']['name']) ? $config['exchange']['name'] : '',
            'type' => isset($config['exchange']['type']) ? $config['exchange']['type'] : '',
            'passive' => isset($config['exchange']['passive']) ? $config['exchange']['passive'] : false,
            'durable' => isset($config['exchange']['durable']) ? $config['exchange']['durable'] : false,
            'auto_delete' => isset($config['exchange']['auto_delete']) ? $config['exchange']['auto_delete'] : false,
        ];

        // Create as many queues as defined in $config
        $queueOptions = [];
        foreach ($config['queue'] as $queueType => $queueDetails) {
            $queueOptions[$queueType] = [
                'name' => $queueDetails['name'],
                'passive' => $queueDetails['passive'],
                'durable' => $queueDetails['durable'],
                'exclusive' => $queueDetails['exclusive'],
                'auto_delete' => $queueDetails['auto_delete'],
                'bindingKey' => isset($queueDetails['bindingKey']) ? $queueDetails['bindingKey'] : '',
            ];
        }
        $this->queueOptions = $queueOptions;

        /**
         * Routing Key is the routing value for topic exchanges, example:
         * '*.*.transactional'
         * $channel->queue_bind($transactionalQueue, $exchangeName, '*.*.transactional');
         * This value may not be relevant for non "topic" exchanges??
         *
         * There might be some confusion between using this setting for queue_bind
         * and basic_publish. The confusion is between routingKey and bindingKey,
         * topic vs direct exchanges.
         *
         * -> queue_bind routing keys define the combination of keys
         * of messages that get routed to certain queues.
         * -> basic_publish sets the keys assigned to a message
         *
         * An exchange with a routing key binding of *.*.transactional will get an
         * entry for all messages sent with a routing key of:
         * user.registration.transactional
         *
         * In the case of "direct" exchanges, the routing key must be a exact match
         * with the routing key assigned to the message. This value can be blank but
         * it must be the same for both the queue and message value.
         */
         $this->routingKey = isset($config['routingKey']) ? $config['routingKey'] : '';
    }

    /**
     * Destructor
     *
     * Clean up connections.
     */
    public function __destruct()
    {

        $this->connection->close();
    }

    /**
     * Publish a message to the message broker system.
     *
     * @param string $payload
     *  Data to wrap in the message.
     * @param string $routingKey
     *  The key or pattern the message will use for submission to the exchange for distribution to the
     *  attached queues. Depending on the exchange type the key defines what queues get a copy of
     *  the message.
     * @param int $deliveryMode
     *  1: non-persistent, faster but no logging to disk, ~ 3x
     *  2: persistent, write a copy of the message to disk
     *
     * The related queue must also be set to durable for the setting
     * to work. If the server crashes, persistent messages will be recovered.
     * Crash tolerance comes at a price.
     */
    public function publish($payload, $routingKey = null, $deliveryMode = 1)
    {

        if (empty($payload)) {
            throw new Exception('publish: payload not set.');
        }

        $channel = $this->connection->channel();

        // Exchange setup
        $this->setupExchange($this->exchangeOptions['name'], $this->exchangeOptions['type'], $channel);

        foreach ($this->queueOptions as $queueOption) {
            // Queue setup
            list($channel, ) = $this->setupQueue($queueOption['name'], $channel);

            // Bind the queue to the exchange
            $channel->queue_bind($queueOption['name'], $this->exchangeOptions['name'], $queueOption['bindingKey']);
        }

        // Routing key value can be a parameter to publishMessage() or a setting in the settings array sent
        // to the instantiation of the Message Broker object.
        $routingKey = isset($routingKey) ? $routingKey : $this->routingKey;
        $messageProperties = [
            'delivery_mode' => $deliveryMode,
        ];
        $message = new AMQPMessage($payload, $messageProperties);
        $channel->basic_publish($message, $this->exchangeOptions['name'], $routingKey);

        $channel->close();
    }

    /**
     * Consume messages from the message broker queue.
     *
     * @param $callback
     *  Callback to handle messages the consumer receives.
     * @param integer $consumeAmount
     *  The number of message to set as unacked and reserve when consumer is sent messages.
     */
    public function consume($callback, $consumeAmount = null)
    {

        $channel = $this->connection->channel();

        // Exchange setup
        $this->setupExchange($this->exchangeOptions['name'], $this->exchangeOptions['type'], $channel);

        foreach ($this->queueOptions as $queueOption) {
            // Queue setup
            list($channel, ) = $this->setupQueue($queueOption['name'], $channel);

            // Bind the queue to the exchange
            $channel->queue_bind($queueOption['name'], $this->exchangeOptions['name'], $queueOption['bindingKey']);

            if (!$consumeAmount == null) {
                // @todo: Investigate if large unack amounts result in multi consumers being able to process large queues.
                // This currently set the unacked limit to a single message resulting in every consumer getting access to
                // the next message in the queue.
                // $channel->qos(NULL, $consumeAmount, null);
                $channel->basic_qos(null, 1, null);
            }

            // Start the consumer
            $channel->basic_consume(
                $queueOption['name'],
                $this->consumeOptions['consumer_tag'],
                $this->consumeOptions['no_local'],
                $this->consumeOptions['no_ack'],
                $this->consumeOptions['exclusive'],
                $this->consumeOptions['nowait'],
                $callback
            );
        }

        // Wait for messages on the channel
        echo ' [*] Waiting for messages = ' . date('D M j G:i:s T Y') . '. To exit press CTRL+C', PHP_EOL;
        $this->channel = $channel;
        while (count($this->channel->callbacks)) {
            $this->channel->wait();
        }

        $this->channel->close();
    }

    /**
     * Consume all messages from the message broker queue.
     *
     * @param $callback
     *  Callback to handle messages the consumer receives.
     */
    public function getAllMessages($callback)
    {

        $channel = $this->connection->channel();

        // Exchange setup
        $this->setupExchange($this->exchangeOptions['name'], $this->exchangeOptions['type'], $channel);

        foreach ($this->queueOptions as $queueOption) {
            // Queue setup
            list($channel, ) = $this->setupQueue($queueOption['name'], $channel);

            // Bind the queue to the exchange
            $channel->queue_bind($queueOption['name'], $this->exchangeOptions['name'], $queueOption['bindingKey']);
        }

        $this->channel = $channel;
        $data = [];
        while ($message = $this->channel->basic_get($queueOption['name'])) {
            if ($message->delivery_info) {
                $message->delivery_info['channel'] = $this->channel;
                $data[] = $message;
            }
        }

        call_user_func($callback, $data);
        $this->channel->close();
    }

    /**
     * Sends an acknowledgement back to the message broker so the message can be
     * removed from the queue.
     *
     * @param $payload
     *   The payload received in the consume callback.
     */
    public function sendAck($payload)
    {

        if (empty($payload->delivery_info['delivery_tag'])) {
            throw new Exception('sendAck: delivery_tag not set.');
        }

        $payload->delivery_info['channel']->basic_ack($payload->delivery_info['delivery_tag']);
    }

    /**
     * Sends an non acknowledgement back to the message broker so the message can be
     * rejected or returned to the queue.
     *
     * https://www.rabbitmq.com/nack.html
     *
     * @param $payload
     *   The payload received in the consume callback.
     * @param $purge
     *   Reject messages in bulk - all unacked messages up to the current message defined in $payload
     * @param $requeue
     *   Reject and requeue (to potentially allow other consumer to process)
     */
    public function sendNack($payload, $purge = false, $requeue = true)
    {

        if (empty($payload->delivery_info['delivery_tag'])) {
            throw new Exception('sendNack: delivery_tag not set.');
        }

        $payload->delivery_info['channel']->basic_nack($payload->delivery_info['delivery_tag'], $purge, $requeue);
    }

    /**
     * setupExchange - common create exchange functionality used to ensure exchange
     * settings are the same for both producers and consumers. A producer will
     * never communicate with a queue directly, it's always through an exchange.
     *
     * @param string $exchangeName
     *  Name of the exchange that will bind to a queue
     *
     * @param string $exchangeType
     *  The exchange type
     *
     * @param object $channel
     *  The channel connection that the queue should use.
     *
     * @return object $channel
     *
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
     *
     * Debugging from command line:
     * $ rabbitmqctl list_exchanges
     */
    public function setupExchange($exchangeName, $exchangeType, $channel)
    {

        $channel->exchange_declare(
            $exchangeName,
            $exchangeType,
            $this->exchangeOptions['passive'],
            $this->exchangeOptions['durable'],
            $this->exchangeOptions['auto_delete']
        );

        return $channel;
    }

    /**
     * getQueue - common create queue functionality used to ensure queue settings
     * are the same for both producers and consumers. If the queue already exists
     * the details of the queue will be return.
     *
     * @param string $queueName
     *  Name of the queue
     *
     * @param object $channel
     *  The channel connection that the queue should use.
     *
     * @return object $channel
     *   The updated channel object with the new queue.
     *
     * @return array $status
     *   When a queue is already setup a queue_declare() will return details
     *   about the existing queue.
     *     status[1] - message count
     *     status[2] - unacknowledged count
     *
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
     * exclusive: The queue can be accessed in other channels
     *
     * auto_delete: The queue won't be deleted once the channel is closed. If
     * set, the queue is deleted when all consumers have finished using it.
     */
    public function setupQueue($queueName, $channel)
    {

        foreach ($this->queueOptions as $queue => $queueOption) {
            if ($queueOption['name'] == $queueName) {
                $status = $channel->queue_declare(
                    $queueName,
                    $queueOption['passive'],
                    $queueOption['durable'],
                    $queueOption['exclusive'],
                    $queueOption['auto_delete']
                );
                return array($channel, $status);
            }
        }

        // Error as queue has not been setup
        throw new Exception($queueName . ' options not found in $this->queueOptions.');
    }

    /**
     * Close the channel to the server by removing channel callbacks.
     *
     * @see MessageBroker::consume()  The while loop.
     *
     * @return  bool Operation result.
     */
    public function stop()
    {
        if (!empty($this->channel)) {
            echo 'Stopping consumer by removing callbacks.' . PHP_EOL;
            $this->channel->callbacks = null;
            return true;
        } else {
            return false;
        }
    }

}
