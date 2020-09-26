<?php

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

require_once(DIR_FS_CATALOG . "includes/languages/{$language}/modules/payment/stripe_klarna.php");
require_once(DIR_FS_CATALOG . "includes/languages/{$language}/checkout_process.php");
if (! class_exists('stripe_klarna')) {
  require_once(DIR_FS_CATALOG . 'includes/modules/payment/stripe_klarna.php');
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
      $email_text = sprintf(MODULE_PAYMENT_STRIPE_KLARNA_FAIL_EMAIL_TEXT, $chk_o['customers_name'], $order_id) . "\n\n" . EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link('account_history_info.php', 'order_id=' . $order_id, 'SSL', false) . "\n";
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

function processPayment($intent, $stripe_sca, $currencies) {

    $secret_key = MODULE_PAYMENT_STRIPE_SCA_TRANSACTION_SERVER == 'Live' ? MODULE_PAYMENT_STRIPE_SCA_LIVE_SECRET_KEY : MODULE_PAYMENT_STRIPE_SCA_TEST_SECRET_KEY;
    \Stripe\Stripe::setApiKey($secret_key);
    \Stripe\Stripe::setApiVersion($stripe_sca->apiVersion);
    $stripe_sca->event_log($customer_id, "webhook process payment", "", "");

    if ($intent->status == 'succeeded') {

        saveCard($intent, $stripe_sca);

        processOrder($intent, $stripe_sca, $currencies);

        exit;
    }

    if (isset($intent->last_payment_error['message'])) {
        tep_session_register('stripe_error');

        $stripe_error = $intent->status . ", " . $intent->last_payment_error['message'];
    }

    sendDebugEmail($intent);
}

function saveCard($intent, $stripe_sca) {

    $stripe_token = $intent->customer . ":" . $intent->payment_method;
    $cc_save = $intent->metadata['cc_save'];
    $customer_id = $intent->metadata['customer_id'];

    $stripe_sca->event_log($customer_id, "webhook saveCard", $stripe_token, $cc_save);

    if ((MODULE_PAYMENT_STRIPE_SCA_TOKENS == 'True') && isset($cc_save) && ($cc_save == 'true')) {
        $stripe_customer_id = getStripeCustomerID($customer_id, $stripe_sca);
        $stripe_card_id = false;

        if ($stripe_customer_id === false) {
            $stripe_customer_array = createCustomer($intent, $customer_id, $stripe_sca);
        } else {
            $stripe_card_id = addCard($intent, $stripe_customer_id, $customer_id, $stripe_sca);
        }
    }
}

function processFailure($intent, $stripe_sca, $error_message) {

    $cc_save = $intent->metadata['cc_save'];
    $order_id = $intent->metadata['order_id'];
    $stripe_card = $intent->metadata['stripe_card'];

    $status_comment = array('Transaction ID: ' . $intent->id,
        'Error:' . $error_message);

    if (!empty($intent->charges->data[0]->payment_method_details->card->brand)) {
        $status_comment[] = 'Brand: ' . $intent->charges->data[0]->payment_method_details->card->brand;
    }

    if (!empty($intent->charges->data[0]->payment_method_details->card->last4)) {
        $status_comment[] = 'Last 4: ' . $intent->charges->data[0]->payment_method_details->card->last4;
    }

    if (!empty($intent->charges->data[0]->payment_method_details->card->checks->cvc_check)) {
        $status_comment[] = 'CVC: ' . $intent->charges->data[0]->payment_method_details->card->checks->cvc_check;
    }

    if (!empty($intent->charges->data[0]->payment_method_details->card->checks->address_line1_check)) {
        $status_comment[] = 'Address Check: ' . $intent->charges->data[0]->payment_method_details->card->checks->address_line1_check;
    }

    if (!empty($intent->charges->data[0]->payment_method_details->card->checks->address_postal_code_check)) {
        $status_comment[] = 'Postal Code Check: ' . $intent->charges->data[0]->payment_method_details->card->checks->address_postal_code_check;
    }

    if (!empty($intent->charges->data[0]->payment_method_details->card->three_d_secure->authenticated)) {
        $status_comment[] = '3d Secure: ' . ($intent->charges->data[0]->payment_method_details->card->three_d_secure->authenticated == 1 ? 'true' : 'false');
    }

    if (MODULE_PAYMENT_STRIPE_SCA_TOKENS == 'True') {
        if (isset($cc_save) && ($cc_save == 'true')) {
            $status_comment[] = 'Token Saved: Yes';
        } elseif (isset($stripe_card) && is_numeric($stripe_card) && ($stripe_card > 0)) {
            $status_comment[] = 'Token Used: Yes';
        }
    }

    $sql_data_array = array('orders_id' => $order_id,
        'orders_status_id' => MODULE_PAYMENT_STRIPE_SCA_TRANSACTION_ORDER_STATUS_ID,
        'date_added' => 'now()',
        'customer_notified' => '0',
        'comments' => implode("\n", $status_comment));

    tep_db_perform("orders_status_history", $sql_data_array);
}

function processOrder($intent, $stripe_sca, $currencies) {

    $order_id = $intent->metadata['order_id'];
    $customer_id = $intent->metadata['customer_id'];
    $stripe_sca->event_log($customer_id, "webhook processOrder", $order_id, "");

    $check_query = tep_db_query("select orders_status from orders where orders_id = '" . (int) $order_id . "' and customers_id = '" . (int) $customer_id . "'");

    if (tep_db_num_rows($check_query)) {
        $check = tep_db_fetch_array($check_query);

        if ($check['orders_status'] == MODULE_PAYMENT_STRIPE_SCA_PREPARE_ORDER_STATUS_ID) {
            $new_order_status = DEFAULT_ORDERS_STATUS_ID;

            if (MODULE_PAYMENT_STRIPE_SCA_ORDER_STATUS_ID > 0) {
                $new_order_status = MODULE_PAYMENT_STRIPE_SCA_ORDER_STATUS_ID;
            }

            tep_db_query("update orders set orders_status = '" . (int) $new_order_status . "', last_modified = now() where orders_id = '" . (int) $order_id . "'");

            $sql_data_array = array('orders_id' => $order_id,
                'orders_status_id' => (int) $new_order_status,
                'date_added' => 'now()',
                'customer_notified' => (SEND_EMAILS == 'true') ? '1' : '0',
                'comments' => '');

            tep_db_perform("orders_status_history", $sql_data_array);

            $stripe_sca->event_log($customer_id, "webhook updateOrderStatusHistory", $intent->metadata['order_id'], "");
            $cc_save = $intent->metadata['cc_save'];
            $order_id = $intent->metadata['order_id'];
            $stripe_card = $intent->metadata['stripe_card'];

            $status_comment = array('Transaction ID: ' . $intent->id);

            if (!empty($intent->charges->data[0]->payment_method_details->card->brand)) {
                $status_comment[] = 'Brand: ' . $intent->charges->data[0]->payment_method_details->card->brand;
            }

            if (!empty($intent->charges->data[0]->payment_method_details->card->last4)) {
                $status_comment[] = 'Last 4: ' . $intent->charges->data[0]->payment_method_details->card->last4;
            }

            if (!empty($intent->charges->data[0]->payment_method_details->card->checks->cvc_check)) {
                $status_comment[] = 'CVC: ' . $intent->charges->data[0]->payment_method_details->card->checks->cvc_check;
            }

            if (!empty($intent->charges->data[0]->payment_method_details->card->checks->address_line1_check)) {
                $status_comment[] = 'Address Check: ' . $intent->charges->data[0]->payment_method_details->card->checks->address_line1_check;
            }

            if (!empty($intent->charges->data[0]->payment_method_details->card->checks->address_postal_code_check)) {
                $status_comment[] = 'Postal Code Check: ' . $intent->charges->data[0]->payment_method_details->card->checks->address_postal_code_check;
            }

            if (!empty($intent->charges->data[0]->payment_method_details->card->three_d_secure->authenticated)) {
                $status_comment[] = '3d Secure: ' . ($intent->charges->data[0]->payment_method_details->card->three_d_secure->authenticated == 1 ? 'true' : 'false');
            }

            if (MODULE_PAYMENT_STRIPE_SCA_TOKENS == 'True') {
                if (isset($cc_save) && ($cc_save == 'true')) {
                    $status_comment[] = 'Token Saved: Yes';
                } elseif (isset($stripe_card) && is_numeric($stripe_card) && ($stripe_card > 0)) {
                    $status_comment[] = 'Token Used: Yes';
                }
            }

            $sql_data_array = array('orders_id' => $order_id,
                'orders_status_id' => MODULE_PAYMENT_STRIPE_SCA_TRANSACTION_ORDER_STATUS_ID,
                'date_added' => 'now()',
                'customer_notified' => '0',
                'comments' => implode("\n", $status_comment));

            tep_db_perform("orders_status_history", $sql_data_array);
        }
    }
}

function getStripeCustomerID($customer_id, $stripe_sca) {

    $token_check_query = tep_db_query("select stripe_token from customers_stripe_tokens where customers_id = '" . (int) $customer_id . "' limit 1");

    if (tep_db_num_rows($token_check_query) === 1) {
        $token_check = tep_db_fetch_array($token_check_query);

        $stripe_token_array = explode(':|:', $token_check['stripe_token'], 2);

        $stripe_sca->event_log($customer_id, "webhook getStripeCustomerID", $customer_id, $stripe_token_array[0]);

        return $stripe_token_array[0];
    }

    return false;
}

function createCustomer($intent, $customer_id, $stripe_sca) {

    $charge = $intent->charges->data[0];
    $params = array('payment_method' => $intent->payment_method,
        'name' => !empty($intent->metadata['company']) ? $intent->metadata['company'] : $charge->billing_details['name'],
        'email' => $charge->billing_details['email'],
        'metadata' => array('customer_id' => $customer_id));
    $customer = \Stripe\Customer::create($params);
    $stripe_sca->event_log($customer_id, "webhook createCustomer", $intent->payment_method, $customer);

    insertCustomerToken($customer_id, $customer->id, $intent, null);

    return false;
}

function addCard($intent, $stripe_customer_id, $customer_id, $stripe_sca) {

    $payment_method = \Stripe\PaymentMethod::retrieve($intent->payment_method);
    $stripe_sca->event_log($customer_id, "webhook addCard", $intent->payment_method, $payment_method);
    if (is_object($payment_method) && !empty($payment_method) && isset($payment_method->object) && ($payment_method->object == 'payment_method')) {

        $result = $payment_method->attach(['customer' => $stripe_customer_id]);
        if (is_object($result) && !empty($result) && isset($result->object) && ($result->object == 'payment_method')) {

            insertCustomerToken($customer_id, $stripe_customer_id, $intent, $payment_method);

            return $payment_method['id'];
        }
    }

    $stripe_sca->sendDebugEmail($payment_method);

    return false;
}

function insertCustomerToken($customer_id, $stripe_customer_id, $intent, $payment_method = null) {

    if (!isset($payment_method)) {
        $payment_method = \Stripe\PaymentMethod::retrieve($intent->payment_method);
    }
    $token = tep_db_prepare_input($stripe_customer_id . ':|:' . $intent->payment_method);
    $type = tep_db_prepare_input($payment_method->card->brand);
    $number = tep_db_prepare_input($payment_method->card->last4);
    $expiry = tep_db_prepare_input(str_pad($payment_method->card->exp_month, 2, '0', STR_PAD_LEFT) . $payment_method->card->exp_year);

    $sql_data_array = array('customers_id' => (int) $customer_id,
        'stripe_token' => $token,
        'card_type' => $type,
        'number_filtered' => $number,
        'expiry_date' => $expiry,
        'date_added' => 'now()');

    tep_db_perform('customers_stripe_tokens', $sql_data_array);
}

tep_session_destroy();

require('includes/application_bottom.php');
