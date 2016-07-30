messagebroker-phplib
====================

A PHP baed library that ascts as a wrapper around the `php-amqplib/php-amqplib` AMQP PHP library. 
The goal is to provide utility methods to simplify some of the more common activities of 
interacting with a AMQP based (RabbitMQ) server. 



 ####Usage
 
 Within the `composer.json` file of any PHP based application include this package with:
 ```
   "require": {
     "php": ">= 5.3.0",
     "DoSomething/messagebroker-phplib": "0.3.*",
     ...
 ```
 
###Create an instance of the class
```
// RabbitMQ
$rabbitCredentials = [
    'host' =>  getenv("RABBITMQ_HOST"),
    'port' => getenv("RABBITMQ_PORT"),
    'username' => getenv("RABBITMQ_USERNAME"),
    'password' => getenv("RABBITMQ_PASSWORD"),
    'vhost' => getenv("RABBITMQ_VHOST"),
];

$config['exchange'] = array(
  'name' => $exchangeSettings->name,
  'type' => $exchangeSettings->type,
  'passive' => $exchangeSettings->passive,
  'durable' => $exchangeSettings->durable,
  'auto_delete' => $exchangeSettings->auto_delete,
);
    
$config['queue'] = array(
  'name' => $queueSetting->name,
  'passive' => $queueSetting->passive,
  'durable' =>  $queueSetting->durable,
  'exclusive' =>  $queueSetting->exclusive,
  'auto_delete' =>  $queueSetting->auto_delete,
  'routingKey' =>  $queueSetting->routing_key,
  'bindingKey' => $bindingKey,
);

$mb = new MessageBroker($rabbitCredentials, $config));
```

###Publish a message
```
$this->messageBroker->publish($message, <routing key>);
```

###Consume a message

How a message will be consumed is defined in the connection to the queue.
```
$config['consume'] = array(
  'no_local' => $queueSetting->consume->no_local,
  'no_ack' => $queueSetting->consume->no_ack,
  'nowait' => $queueSetting->consume->nowait,
  'exclusive' => $queueSetting->consume->exclusive,
);
```

The number of messages for the consumer to reserve with each callback. This is Necessary for 
parallel processing when more than one consumer is running on the same queue.

```
define('QOS_SIZE', 1);

$mb->consumeMessage([new consumer class(), <consumer method], QOS_SIZE);
```

where the consumed message details will be sent to consumer method>

```
 public function <consumer method>($payload) {
```

####Gulp Support
Use a path directly to gulp `./node_modules/.bin/gulp` or add an alias to your system config (`.bash_profile`) as `alias gulp='./node_modules/.bin/gulp'`

###Linting
- `gulp lint`

See `gulpfile.js` for configuration and combinations of tasks.

##PHP CodeSniffer

- `php ./vendor/bin/phpcs --standard=./ruleset.xml --colors -s MessageBroker-Drupal.php src tests`
Listing of all coding volations by file.

- `php ./vendor/bin/phpcbf --standard=./ruleset.xml --colors MessageBroker-Drupal.php src tests`
Automated processing of files to adjust to meeting coding standards.

###Test Coverage
- `gulp test`

or

- `npm test`

##PHP Unit

- `$ ./vendor/bin/phpunit --verbose tests`
