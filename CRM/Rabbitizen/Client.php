<?php

require_once __DIR__ . '/../../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class CRM_Rabbitizen_Client {

  public function connect() {
    return new AMQPSSLConnection(
      CIVICRM_AMQP_HOST, CIVICRM_AMQP_PORT,
      CIVICRM_AMQP_USER, CIVICRM_AMQP_PASSWORD, CIVICRM_AMQP_VHOST,
      array(
        'local_cert' => CIVICRM_SSL_CERT,
        'local_pk' => CIVICRM_SSL_KEY,
      )
    );
  }

}
