<?php
/**
 * Entry point for Message Broker PHP Library
 */

// Setup autoloader - loads AMPQ and Mandrill classes
require_once __DIR__ . '/vendor/autoload.php';

// Load library class
require(dirname(__FILE__) . '/MessageBroker/MessageBrokerObjectLibrary.php');