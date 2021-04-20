<?php

require_once __DIR__ . '/../../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPSSLConnection;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class CRM_Rabbitizen_Consumer {
  /* Public config, overridable by caller */
  public $retryDelay = 60000; //Milliseconds
  public $dieOnError = TRUE;

  /* Queue and exchange names, given at construction */
  private $queue = NULL;
  private $error_queue = NULL;
  private $retry_exchange = NULL;

  /**
   * @param $message_processor The API function that should process the received messages, in the format <Entity>.<Function>
   * @param $queue The queue from which messages should be read
   * @param $error_queue Where to push messages that failed to be processed
   * @param $retry_exchange Where to push messages that should be tried again after a failure
   */
  public function __construct($message_processor, $queue, $error_queue = NULL, $retry_exchange = NULL) {
    $this->processor = explode('.', $message_processor);
    $this->queue = $queue;
    $this->error_queue = $error_queue;
    $this->retry_exchange = $retry_exchange;
  }

  /**
   * Callback that processes each RabbitMQ message.
   * It extracts the JSON event and gives it to the configured API function.
   * Depending on the result, acknowledge the processed message or handle appropriately the error.
   * @param $msg - AMQPMessage instance
   */
  public function processMessage($msg) {
    try {
      $result = civicrm_api3($this->processor[0], $this->processor[1], ['message' => $msg->body]);
      if ($result['is_error']) {
        $this->handleError($msg, $result["error_message"], $result["retry_later"]);
      }
      else {
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
      }
    }
    catch (Exception $ex) {
      $this->handleException($msg, $ex);
    }
  }

  /**
   * Connect to RabbitMQ and enters an infinite loop waiting for incoming messages.
   * Regularly check the server load, and pauses the consumption when the load is too high
   */
  public function start($callbackFunction = 'processMessage') {
    try {
      $connection = $this->connect();
      $channel = $connection->channel();
      $channel->basic_qos(NULL, $this->loadCheckPeriod, NULL);
      $cb_name = $channel->basic_consume($this->queue, '', FALSE, FALSE, FALSE, FALSE, array($this, $callbackFunction));
      while (TRUE) {
        $channel->wait();
      }
    } catch (Exception $ex) {
      // If an exception occurs while waiting for a message, the CMS custom error handler will catch it and the process will exit with status 0,
      // which would prevent the systemd service from automatically restarting. Using handleError prevents this behaviour.
      $this->handleError(NULL, CRM_Core_Error::formatTextException($ex));
    }
  }

  /**
   * Handle an exception thrown while processing an incoming message.
   * Depending on the exception type and message, and depending on the runtime
   * configuration, the incoming message is published to the error queue,
   * retry exchange, or simpled NACKed.
   */
  protected function handleException($amqp_msg, $ex) {
    $retry = FALSE;
    if ($ex instanceof CiviCRM_API3_Exception) {
      $retry = $this->retry($ex->getExtraParams());
    }
    $this->handleError($amqp_msg, CRM_Core_Error::formatTextException($ex), $retry);
  }

  /**
   * If $retry is trueish, nack the message without re-queue and send it to the retry exchange.
   * Otherwise if an error queue is defined, send it to that queue through the direct exchange.
   * Otherwise nack and re-deliver the message to the originating queue.
   * If no message is provided, simply log the error and die.
   */
  protected function handleError($msg, $error, $retry = FALSE) {
    CRM_Core_Error::debug_var("Rabbitizen error", $error, TRUE, TRUE);

    if ($msg) {
      $channel = $msg->delivery_info['channel'];
      if ($retry && $this->retry_exchange != NULL) {
        $channel->basic_nack($msg->delivery_info['delivery_tag']);
        $new_msg = new AMQPMessage($msg->body);
        $headers = new AMQPTable(array('x-delay' => $this->retryDelay));
        $new_msg->set('application_headers', $headers);
        $channel->basic_publish($new_msg, $this->retry_exchange, $msg->delivery_info['routing_key']);
      }
      elseif ($this->error_queue != NULL) {
        $channel->basic_nack($msg->delivery_info['delivery_tag']);
        $channel->basic_publish($msg, '', $this->error_queue);
      }
      else {
        $channel->basic_nack($msg->delivery_info['delivery_tag'], FALSE, TRUE);
      }
    }

    //In some cases (e.g. a lost connection), dying and respawning can solve the problem
    if ($this->dieOnError) {
      die(1);
    }
  }

  /**
   * Check for known bug.
   *
   * @param $extraInfo
   *
   * @return bool
   */
  protected function retry($extraInfo) {
    if ($extraInfo['retry_later']) {
      return TRUE;
    }

    $debugInformation = [
      'debug_information' => 'try restarting transaction',
      'error_message' => 'DB Error: no database selected',
    ];
    foreach ($debugInformation as $key => $information) {
      if (strpos(CRM_Utils_Array::value($key, $extraInfo), $information) !== FALSE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  protected function connect() {
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

