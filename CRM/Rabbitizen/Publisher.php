<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class CRM_Rabbitizen_Publisher {

  /* Queue and exchange names, given at construction */
  private $queue = NULL;
  private $exchange = '';

  /**
   * @param $queue The queue to which messages should be published;
   * @param $exchange The exchange to which messages should be published
   *
   * See the RabbitMQ docs for details about how to specify queue and exchange
   *
   *           https://www.rabbitmq.com/tutorials/tutorial-three-php.html
   */
  public function __construct($queue = NULL, $exchange = '') {
    $this->queue = $queue;
    $this->exchange = $exchange;
  }

  /**
   * Connect to RabbitMQ and publish a message. Remember that you can use '' for exchange to publish directly to a queue.
   *
   **/
  public function publish($msg) {
      $connection = $this->connect();
      $channel = $connection->channel();
      $msg = new AMQPMessage($msg);
      if ($this->queue) {
        return $channel->basic_publish($msg, $this->exchange, $this->queue);
      }
      else {
        return $channel->basic_publish($msg, $this->exchange);
      }
  }


  protected function connect() {
    return new AMQPSSLConnection(
      CIVICRM_AMQP_HOST,
      CIVICRM_AMQP_PORT,
      CIVICRM_AMQP_USER,
      CIVICRM_AMQP_PASSWORD,
      CIVICRM_AMQP_VHOST,
      array(
        'local_cert' => CIVICRM_SSL_CERT,
        'local_pk' => CIVICRM_SSL_KEY,
      )
    );
  }
}
