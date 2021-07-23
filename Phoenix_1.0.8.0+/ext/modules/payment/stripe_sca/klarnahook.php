<?php
/**

  webhook to go with Stripe-Klarna payment module
  Klarna source / charge handling is async so lots of processing
  is handled here as a result of callbacks
 
  version 0.1 September 2020
  author: John Ferguson @BrockleyJohn oscommerce@sewebsites.net
  copyright (c) 2020 SEwebsites

* released under SE Websites Commercial licence
* without warranty express or implied
* DISTRIBUTION RESTRICTED see se-websites-commercial-licence.txt
*****************************************************************/

function jTraceEx($e, $seen=null) {
    $starter = $seen ? 'Caused by: ' : '';
    $result = array();
    if (!$seen) $seen = array();
    $trace  = $e->getTrace();
    $prev   = $e->getPrevious();
    $result[] = sprintf('%s%s: %s', $starter, get_class($e), $e->getMessage());
    $file = $e->getFile();
    $line = $e->getLine();
    while (true) {
        $current = "$file:$line";
        if (is_array($seen) && in_array($current, $seen)) {
            $result[] = sprintf(' ... %d more', count($trace)+1);
            break;
        }
        $result[] = sprintf(' at %s%s%s(%s%s%s)',
          count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
          count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
          count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
          $line === null ? $file : basename($file),
          $line === null ? '' : ':',
          $line === null ? '' : $line);
        if (is_array($seen))
            $seen[] = "$file:$line";
        if (!count($trace))
            break;
        $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
        $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
        array_shift($trace);
    }
    $result = join("\n", $result);
    if ($prev)
        $result  .= "\n" . jTraceEx($prev, $seen);

    return $result;
}

chdir('../../../../');
require('includes/application_top.php');

//include_once("includes/languages/{$language}/checkout_process.php");
include_once("includes/languages/{$language}/modules/notifications/n_checkout.php");
if (! class_exists('stripe_klarna')) {
  require_once("includes/languages/{$language}/modules/payment/stripe_klarna.php");
  require_once('includes/modules/payment/stripe_klarna.php');
}

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;
$stripe_klarna = new stripe_klarna();
$endpoint_secret = $stripe_klarna->webhook_secret;

try {
  $event = \Stripe\Webhook::constructEvent(
                  $payload, $sig_header, $endpoint_secret
  );
  $customer_id = isset($event->data->object->metadata['customer_id']) ? $event->data->object->metadata['customer_id'] : 0;
  $order_id = isset($event->data->object->metadata['order_id']) ? $event->data->object->metadata['order_id'] : 0;
  $stripe_klarna->event_log($customer_id, "webhook " . $event->type, $payload, $event);
} catch (\UnexpectedValueException $e) {
  // Invalid payload
  echo MODULE_PAYMENT_STRIPE_KLARNA_WEBHOOK_PARAMETER;
  http_response_code(400); // PHP 5.4 or greater
  exit();
} catch (\Stripe\Exception\SignatureVerificationException $e) {
  // Invalid signature
  echo MODULE_PAYMENT_STRIPE_KLARNA_SECRET_ERROR;
  http_response_code(400); // PHP 5.4 or greater
  exit();
} catch (\Exception $e) {
  // something else
  error_log('Exception: ' . $e->getMessage());
  echo MODULE_PAYMENT_STRIPE_KLARNA_WEBHOOK_SERVER . $e->getMessage();
  http_response_code(400); // PHP 5.4 or greater
  exit();
}

$stripe = new \Stripe\StripeClient($stripe_klarna->secret_key);

if ($event->type == "source.chargeable") {
  $source = $event->data->object;
  error_log('source: ' . print_r($source, true));
  $params = [
    'amount' => $source->amount,
    'currency' => $source->currency,
    'source' => $source->id,
    'metadata' => [
      'customer_id' => $source->metadata->customer_id,
      'order_id' => $source->metadata->order_id,
    ],
  ];
  if (defined('MODULE_PAYMENT_STRIPE_KLARNA_AUTH_CAPTURE') && MODULE_PAYMENT_STRIPE_KLARNA_AUTH_CAPTURE != 'Capture') {
    $params['capture'] = false;
  }
  error_log('charge params: ' . print_r($params, true));
  try {
    $stripe->charges->create($params);  
  } catch (Exception $e) {
    error_log('Exception thrown: ' . jTraceEx($e));
    $stripe_klarna->event_log($customer_id, "webhook error", $e->getMessage(), $e->getTraceAsString());
    echo MODULE_PAYMENT_STRIPE_KLARNA_WEBHOOK_SERVER;
    http_response_code(500);
    exit();
  }
  http_response_code(200);
  exit();
} elseif ($event->type == "source.failed" || $event->type == "source.canceled") {
  $source = $event->data->object;
  processSourceFailure($source, $stripe_klarna, $event, $customer_id);
  http_response_code(200);
  exit();
} elseif ($event->type == "charge.succeeded") {
  $charge = $event->data->object;
  processCharge($charge, $stripe_klarna, $order_id, $customer_id);
  http_response_code(200);
  exit();
} elseif ($event->type == "charge.failed") {
  $charge = $event->data->object;
  processChargeFailure($charge, $stripe_klarna, $event, $order_id, $customer_id);
  http_response_code(200);
  exit();
}

function processCharge($charge, $stripe_klarna, $order_id, $customer_id) {
  // so after a successful charge we need to finish the order...
  if (! class_exists('order')) {
    require('includes/classes/order.php');
  }
  $stripe_klarna->complete_order_email($order_id, $customer_id);
}

function processChargeFailure($charge, $stripe_klarna, $event, $order_id, $customer_id) {
  // log it and mark the order as failed
  $stripe_klarna->event_log($customer_id, $event->type, $event->request, $charge->failure_message);
  if ($customer_id != 0 && $order_id != 0) {
    $chk_q = tep_db_query('select orders_status, customers_name, customers_email_address from orders where orders_id = ' . (int)$order_id . ' and customers_id = ' . (int)$customer_id);
    if (tep_db_num_rows($chk_q)) {
      $chk_o = tep_db_fetch_array($chk_q);
      if ($chk_o['orders_status'] != MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID) {
        $sql_data = ['orders_status' => (int)MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID];
        tep_db_perform('orders', $sql_data, 'update', 'orders_id = ' . (int)$order_id);
      }
      $sql_data = [
        'orders_id' => $order_id,
        'orders_status_id' => MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_ID,
        'date_added' => 'now()',
        'customer_notified' => '',
        'comments' => sprintf(MODULE_PAYMENT_STRIPE_KLARNA_TRAN_CHARGE_FAIL, $charge->failure_code, $charge->failure_message),
      ];
      tep_db_perform('orders_status_history', $sql_data);

      // let the customer know it failed
      $email_text = sprintf(MODULE_PAYMENT_STRIPE_KLARNA_FAIL_EMAIL_TEXT, $chk_o['customers_name'], $order_id) . "\n\n" . MODULE_PAYMENT_STRIPE_KLARNA_EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link('account_history_info.php', 'order_id=' . $order_id, 'SSL', false) . "\n";
      tep_mail($order->customer['name'], $order->customer['email_address'], MODULE_PAYMENT_STRIPE_KLARNA_FAIL_EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
      // send emails to other people
      if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
          tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, MODULE_PAYMENT_STRIPE_KLARNA_FAIL_EMAIL_SUBJECT, $email_text, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
      }
        
    }
  }
  $stripe_klarna->sendDebugEmail($event);  
}

function processSourceFailure($source, $stripe_klarna, $event, $customer_id) {
  // just log it - should still be in checkout
  $stripe_klarna->event_log($customer_id, $event->type, $event->request, $source->status);
}

tep_session_destroy();

require('includes/application_bottom.php');
