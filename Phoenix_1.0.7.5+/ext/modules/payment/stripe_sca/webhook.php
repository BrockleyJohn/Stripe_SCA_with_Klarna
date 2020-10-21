<?php

chdir('../../../../');
require('includes/application_top.php');

require_once(DIR_FS_CATALOG . 'includes/modules/payment/stripe_sca.php');
require_once(DIR_FS_CATALOG . 'includes/languages/' . $language . '/modules/payment/stripe_sca.php');
require_once(DIR_FS_CATALOG . 'includes/languages/' . $language . '/checkout_process.php');


$endpoint_secret = MODULE_PAYMENT_STRIPE_SCA_TRANSACTION_SERVER == 'Live' ? MODULE_PAYMENT_STRIPE_SCA_LIVE_WEBHOOK_SECRET : MODULE_PAYMENT_STRIPE_SCA_TEST_WEBHOOK_SECRET;

$payload = @file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'];
$event = null;
$stripe_sca = new stripe_sca();

try {
    $event = \Stripe\Webhook::constructEvent(
                    $payload, $sig_header, $endpoint_secret
    );
    $stripe_sca->event_log($customer_id, "webhook", $payload, $event);
} catch (\UnexpectedValueException $e) {
    // Invalid payload
    echo MODULE_PAYMENT_STRIPE_SCA_WEBHOOK_PARAMETER;
    http_response_code(400); // PHP 5.4 or greater
    exit();
} catch (\Stripe\Error\SignatureVerification $e) {
    // Invalid signature
    echo MODULE_PAYMENT_STRIPE_SCA_SECRET_ERROR;
    http_response_code(400); // PHP 5.4 or greater
    exit();
}

if ($event->type == "payment_intent.succeeded") {
    $intent = $event->data->object;
    try {
        processPayment($intent, $stripe_sca, $currencies);
    } catch (Exception $e) {
        $stripe_sca->event_log($customer_id, "webhook error", $e->getMessage(), $e->getTraceAsString());
        echo MODULE_PAYMENT_STRIPE_SCA_WEBHOOK_SERVER;
        http_response_code(500);
        exit();
    }
    http_response_code(200);
    exit();
} elseif ($event->type == "payment_intent.payment_failed") {
    $intent = $event->data->object;
    $error_message = $intent->last_payment_error ? $intent->last_payment_error->message : "";
    processFailure($intent, $stripe_sca, $error_message);
    http_response_code(200);
    exit();
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
