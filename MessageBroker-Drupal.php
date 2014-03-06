<?php
/**
 * Drupal entry point for Message Broker PHP Library. This is solution for the
 * rather half baked Libraries solution common in Drupal installs that does
 * not use autoload resulting in libraries dependencies (php-amqplib) not being
 * loaded.
 *
 * As a work around, define hook_libraries_info() to point to this file rather
 * than MessageBrokerObjectLibrary.php. The composer.json config file ignores
 * this file allowing for autoload calls.
 *
 *   $libraries['messagebroker-phplib'] = array(
 *    'name' => 'Message Broker PHP Library',
 *    'vendor url' => 'https://github.com/DoSomething/messagebroker-phplib',
 *    'download url' => 'https://github.com/DoSomething/messagebroker-phplib',
 *    'version' => '1.0',
 *    'files' => array(
 *      'php' => array('MessageBroker.php'),
 *    ),
 * );
 */

// Setup autoloader - loads AMPQ and Mandrill classes
require_once __DIR__ . '/vendor/autoload.php';

// Load library class
require(dirname(__FILE__) . '/src/MessageBroker.php');