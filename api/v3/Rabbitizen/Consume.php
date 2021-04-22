<?php
use CRM_Rabbitizen_ExtensionUtil as E;

/**
 * Rabbitizen.Consume API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/api-architecture/
 */
function _civicrm_api3_rabbitizen_Consume_spec(&$spec) {
  $spec['processor']['api.required'] = 1;
  $spec['queue']['api.required'] = 1;
  $spec['error_queue']['api.required'] = 0;
  $spec['retry_exchange']['api.required'] = 0;
}

/**
 * Rabbitizen.Consume API
 * DOES NOT TERMINATE
 *
 * @param array $params
 */
function civicrm_api3_rabbitizen_Consume($params) {
  //User errors normally don't block the execution, but in this case we do want 
  //the event processing to fail completely so that it goes to the error queue
  //and we are aware of the problem
  set_error_handler(function ($err_severity, $err_msg, $err_file, $err_line, $err_context) {
    if (0 === error_reporting()) { return false; }
    switch ($err_severity) {
      case E_USER_ERROR:
        CRM_Core_Error::debug_log_message("Uncaught E_USER_ERROR during Rabbitizen consumption: forcing exception");
        throw new Exception($err_msg);
    }
    return false;
  });

  $error_queue = CRM_Utils_Array::value('error_queue', $params, NULL);
  $retry_exchange = CRM_Utils_Array::value('retry_exchange', $params, NULL);

  //Start consuming the queue
  $consumer = new CRM_Rabbitizen_Consumer($params['processor'], $params['queue'], $error_queue, $retry_exchange);
  $consumer->start();

  //If we reach this point, there was probably an error during consumption
  return civicrm_api3_create_error("Rabbitizen consumer interrupted");
}
