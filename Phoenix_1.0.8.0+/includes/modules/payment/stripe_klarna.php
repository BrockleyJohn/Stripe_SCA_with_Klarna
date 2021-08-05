<?php
/**
  This version: targets phoenix 1.0.8.0+ 
  v1.0.23 add webhook order updates notify module
  v1.0.22 add locales, sort options, use checkout & after pipelines
  v1.0.21 add authorize/capture option
  v1.0.20 use customer data modules for address
  
  Klarna via Stripe (payment sources)
  
  author: John Ferguson @BrockleyJohn phoenix@sewebsites.net

  Copyright (c) 2021 SE websites

* released under SE Websites Commercial licence
* without warranty express or implied
* DISTRIBUTION RESTRICTED see se-websites-commercial-licence.txt
*****************************************************************/

require_once DIR_FS_CATALOG . 'includes/apps/stripe_sca/init.php';

class stripe_klarna extends abstract_payment_module {

  use cartmart_cfg_helper, cartmart_cfg_modals, cartmart_stripe_helper;

  const CONFIG_KEY_BASE = 'MODULE_PAYMENT_STRIPE_KLARNA_';

  const REQUIRES = [
    'firstname',
    'lastname',
    'street_address',
    'city',
    'postcode',
    'country',
    'telephone',
    'email_address',
  ];

  protected $intent;
  public $signature = 'stripe|stripe_klarna|1.0.23|1.0.8.4';
  public $api_version = '2020-08-27';

  function __construct() {
    global $PHP_SELF, $order, $payment;

    parent::__construct();
    $this->public_title = MODULE_PAYMENT_STRIPE_KLARNA_TEXT_PUBLIC_TITLE;
    $this->sort_order = $this->sort_order ?? 0;

    if (defined(static::CONFIG_KEY_BASE . 'STATUS')) {
      if ($this->base_constant('TRANSACTION_SERVER') == 'Test') {
        $this->title .= ' [Test]';
        $this->public_title .= ' (Test)';
      }

      if (basename($PHP_SELF) == 'modules.php' && isset($_GET['module']) && $_GET['module'] == get_class($this)) {
        $this->description .= $this->getTestLinkInfo();
        if (!(defined('MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_STATUS') && MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_STATUS == 'True')) {
          $this->description = '<div class="alert alert-danger">' . $this->base_constant('ERROR_WEBHOOK_NOTIFICATIONS') . '</div>' . $this->description;
        }
      }

      $this->order_status = $this->base_constant('PREPARE_ORDER_STATUS_ID');
    }

    if (!function_exists('curl_init')) {
      $this->description = '<div class="alert alert-danger">' . $this->base_constant('ERROR_ADMIN_CURL') . '</div>' . $this->description;

      $this->enabled = false;
    }

    if ($this->enabled === true) {
      if (($this->base_constant('TRANSACTION_SERVER') == 'Live' && (!tep_not_null($this->base_constant('LIVE_PUBLISHABLE_KEY')) || !tep_not_null($this->base_constant('LIVE_SECRET_KEY')))) || ($this->base_constant('TRANSACTION_SERVER') == 'Test' && (!tep_not_null($this->base_constant('TEST_PUBLISHABLE_KEY')) || !tep_not_null($this->base_constant('TEST_SECRET_KEY'))))) {
        $this->description = '<div class="alert alert-danger">' . $this->base_constant('ERROR_ADMIN_CONFIGURATION') . '</div>' . $this->description;

        $this->enabled = false;
      } else {
        $this->publishable_key = $this->base_constant('TRANSACTION_SERVER') == 'Live' ? $this->base_constant('LIVE_PUBLISHABLE_KEY') : $this->base_constant('TEST_PUBLISHABLE_KEY');
        $this->secret_key = $this->base_constant('TRANSACTION_SERVER') == 'Live' ? $this->base_constant('LIVE_SECRET_KEY') : $this->base_constant('TEST_SECRET_KEY');
        $this->webhook_secret = $this->base_constant('TRANSACTION_SERVER') == 'Live' ? $this->base_constant('LIVE_WEBHOOK_SECRET') : $this->base_constant('TEST_WEBHOOK_SECRET');
      }
    }

    if ($this->enabled === true) {
      if (isset($order) && is_object($order)) {
        $this->update_status();
      }
    }

    if ((basename($PHP_SELF) == 'modules.php') && isset($_GET['action']) && ($_GET['action'] == 'install') && $_GET['module'] == get_class($this) && isset($_GET['subaction'])) {
      echo $this->getTestConnectionResult();
      exit;
    }
  }

  function selection() {
    if (isset($_SESSION['cart_Stripe_Klarna_ID'])) {
      $order_id = $this->extract_order_id();

      $check_query = tep_db_query('SELECT orders_id FROM orders_status_history WHERE orders_id = ' . (int)$order_id . ' LIMIT 1');

      if (tep_db_num_rows($check_query) < 1) {
        tep_delete_order($order_id);

        tep_delete_order($order_id);
        unset($_SESSION['cart_Stripe_Klarna_ID']);
      }
    }

    return [
      'id' => $this->code,
      'module' => $this->public_title
    ];
  }

  function confirmation() {
    global $order, $customer_id, $languages_id, $currencies, $currency, $stripe_source_id, $order_total_modules, $shipping, $insert_id, $klarna_error;

    if (isset($_SESSION['cartID'])) {

      try {
        $stripe = new \Stripe\StripeClient($this->secret_key);
      } catch (Exception $err) {
        return ['title' => MODULE_PAYMENT_STRIPE_KLARNA_CLIENT_CREATE_ERROR];
      }

      $insert_order = false;

      if (isset($_SESSION['cart_Stripe_Klarna_ID'])) {
        $order_id = $this->extract_order_id();

        $curr_check = tep_db_query("select currency from orders where orders_id = '" . (int)$order_id . "'");
        $curr = tep_db_fetch_array($curr_check);

        if ($curr['currency'] != $GLOBALS['order']->info['currency'] || $_SESSION['cartID'] != $this->extract_cart_id()) {

          // can't update amount for klarna so force recreate
          if (isset($_SESSION['stripe_source_id'])) unset($_SESSION['stripe_source_id']);

          $check_query = tep_db_query('select orders_id from orders_status_history where orders_id = "' . (int)$order_id . '" limit 1');

          if (tep_db_num_rows($check_query) < 2) {
            tep_db_query('delete from orders where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from orders_total where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from orders_status_history where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from orders_products where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from orders_products_attributes where orders_id = "' . (int)$order_id . '"');
            tep_db_query('delete from orders_products_download where orders_id = "' . (int)$order_id . '"');
          }

          $insert_order = true;
        }
      } else {
        $insert_order = true;
      }

      if ($insert_order == true) {

        if (isset($GLOBALS['order']->info['payment_method_raw'])) {
          $GLOBALS['order']->info['payment_method'] = $GLOBALS['order']->info['payment_method_raw'];
          unset($GLOBALS['order']->info['payment_method_raw']);
        }
        
        $GLOBALS['customer_notification'] = 0;

        require 'includes/system/segments/checkout/build_order_totals.php';
        require 'includes/system/segments/checkout/insert_order.php';
        require 'includes/system/segments/checkout/insert_history.php';

        $_SESSION['cart_Stripe_Klarna_ID'] = $_SESSION['cartID'] . '-' . $order_id;
      }

      $metadata = [
        "customer_id" => tep_output_string($_SESSION['customer_id']),
        "order_id" => tep_output_string($order_id),
        "company" => tep_output_string($GLOBALS['order']->customer['company'] ?? '')
      ];

      $content = '<h4>' . MODULE_PAYMENT_STRIPE_KLARNA_OPTIONS . '</h4>' . PHP_EOL;

      // set up source parameters once - start with those that can be updated
      $params = [
        'owner' => [
          'email' => $GLOBALS['order']->customer['email_address'],
          'address' => [
            'line1' => $GLOBALS['order']->customer['street_address'],
            'line2' => $GLOBALS['order']->customer['suburb'] ?? '',
            'city' => $GLOBALS['order']->customer['city'],
            'state' => $GLOBALS['order']->customer['state'],
            'postal_code' => $GLOBALS['order']->customer['postcode'],
            'country' => $GLOBALS['order']->customer['country']['iso_code_2'],
          ],
        ],
        'klarna' => [
          'first_name' => $GLOBALS['order']->customer['firstname'],
          'last_name' => $GLOBALS['order']->customer['lastname'],
          'shipping_first_name' => $GLOBALS['order']->delivery['firstname'],
          'shipping_last_name' => $GLOBALS['order']->delivery['lastname'],
        ],
        'source_order' => [
          'shipping' => [
            'address' => [
              'line1' => $GLOBALS['order']->delivery['street_address'],
              'line2' => $GLOBALS['order']->delivery['suburb'] ?? '',
              'city' => $GLOBALS['order']->delivery['city'],
              'state' => $GLOBALS['order']->delivery['state'],
              'postal_code' => $GLOBALS['order']->delivery['postcode'],
              'country' => $GLOBALS['order']->delivery['country']['iso_code_2'],
            ],
            'phone' => $GLOBALS['order']->customer['telephone'],
          ]
        ],
        'metadata' => $metadata
      ];

      $source_items = [];
      $charges = 0;

    for ($i = 0, $n = sizeof($GLOBALS['order']->products); $i < $n; $i++) {

      $source_items[] = [
        'type' => 'sku',
        'currency' => $_SESSION['currency'],
        'amount' => $this->format_raw((DISPLAY_PRICE_WITH_TAX == 'true' ? tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']) : $GLOBALS['order']->products[$i]['final_price']) * $GLOBALS['order']->products[$i]['qty']),
        'description' => $GLOBALS['order']->products[$i]['name'],
        'quantity' => $GLOBALS['order']->products[$i]['qty'],
      ];
      $charges += (DISPLAY_PRICE_WITH_TAX == 'true' ? tep_add_tax($order->products[$i]['final_price'], $order->products[$i]['tax']) : $GLOBALS['order']->products[$i]['final_price']) * $order->products[$i]['qty'];
    }
    if (is_array($GLOBALS['order_total_modules']->modules)) {
      $discounts = [];
      if (defined('MODULE_PAYMENT_STRIPE_KLARNA_DISCOUNT_MODULES') && strlen(MODULE_PAYMENT_STRIPE_KLARNA_DISCOUNT_MODULES)) {
        $ds = explode(',', MODULE_PAYMENT_STRIPE_KLARNA_DISCOUNT_MODULES);
        foreach ($ds as $d) {
          $discounts[] = trim($d);
        }
      }
      foreach ($GLOBALS['order_total_modules']->modules as $value) {
          $class = substr($value, 0, strrpos($value, '.'));
          if ($GLOBALS[$class]->enabled) {
            $size = sizeof($GLOBALS[$class]->output);
            for ($i = 0; $i < $size; $i++) {
//              if ($GLOBALS[$class]->code == 'ot_tax' || $GLOBALS[$class]->code == 'ot_shipping' || $GLOBALS[$class]->code == 'ot_subtotal') {
              if ($GLOBALS[$class]->code == 'ot_tax' && DISPLAY_PRICE_WITH_TAX == 'false') {
                $source_items[] = [
                  'type' => substr($GLOBALS[$class]->code, 3),
                  'currency' => $currency,
                  'amount' => $this->format_raw($GLOBALS[$class]->output[$i]['value']),
                  'description' => $GLOBALS[$class]->output[$i]['title']
                ];
                $charges += $GLOBALS[$class]->output[$i]['value'];
              } elseif ($GLOBALS[$class]->code == 'ot_shipping' || in_array($GLOBALS[$class]->code, $discounts)) {
              //  $ot_val = $GLOBALS['shipping']['cost'];
                $ot_val = $GLOBALS[$class]->output[$i]['value'];
                $source_items[] = [
                  'type' => substr($GLOBALS[$class]->code, 3),
                  'currency' => $currency,
                  'amount' => $this->format_raw($ot_val),
                  'description' => $GLOBALS[$class]->output[$i]['title']
                ];
                $charges += $ot_val;
              }
            }
          }
        }
      }

    // have to create source before loading the javascript because it needs the source id - first make sure it's up to date if we already have one
    if (isset($_SESSION['stripe_source_id'])) {
      try {
        $this->source = $stripe->sources->update($_SESSION['stripe_source_id'], $params);
        $this->event_log($_SESSION['customer_id'], "page update source", $_SESSION['stripe_source_id'], $this->source);
      } catch (Exception $err) {
        $this->event_log($_SESSION['customer_id'], "page update source", $_SESSION['stripe_source_id'], $err->getMessage());
        // failed to update existing source, so create new one
        unset($_SESSION['stripe_source_id']);
      }
    }

    if (abs($charges - $GLOBALS['order']->info['total']) > 0.01) {
      $caught_error = sprintf(MODULE_PAYMENT_STRIPE_KLARNA_ERROR_TOTAL, $charges, $GLOBALS['order']->info['total']);
    } else {
      $caught_error = '';
    }

    if (!isset($_SESSION['stripe_source_id'])) {

      if (isset($source_items) && is_array($source_items) && count($source_items)) {
        $params['source_order']['items'] = $source_items;
      }
      $params['type'] = 'klarna';
//        $params['amount'] = $this->format_raw($order->info['total']);
      $params['amount'] = $this->format_raw($charges);
      $params['currency'] = $_SESSION['currency'];
      $params['metadata'] = $metadata;
      $params['klarna']['product'] = 'payment';
      $params['klarna']['locale'] = $this->ensureLocale();
      $params['klarna']['purchase_country'] = $GLOBALS['order']->customer['country']['iso_code_2'];
  //  $params['klarna']['custom_payment_methods'] =  // reqd for US payin4 / installments / payin4,installments (for Pay later and Slice it & both)

      try {
        $this->source = $stripe->sources->create($params);
        $this->event_log($_SESSION['customer_id'], "page create source", json_encode($params), $this->source);
        $_SESSION['stripe_source_id'] = $this->source->id;
      } catch (Exception $err) {
        $this->event_log($_SESSION['customer_id'], "page create source", json_encode($params), $err->getMessage() . ":\n" . $err->getTraceAsString());
        $caught_error = $err->getMessage();
        /*
        // failed to create source - this is fatal, so set error and go back to checkout_payment
        $klarna_error = [
          'type' => "page create source",
          'code' => get_class($err),
          'msg' => $caught_error,
        ];
        tep_session_register('klarna_error');
        $page = tep_href_link('checkout_payment.php', 'payment_error='.$this->code.'&error=error');
        $content = <<<EOS
<script>window.location.replace("{$page}");</script>
EOS;
        return array('title' => $content);
        */
      }
    } 

    // exit(print_r($this->source, true));
    if (! strlen($caught_error)) {
      $content .= '<input type="hidden" id="source_id" value="' . tep_output_string($_SESSION['stripe_source_id']) . '" />' . '<input type="hidden" id="secret" value="' . tep_output_string($this->source->client_secret) . '" />';
    }
    $klarna_divs = '';
    $k_opts = [
      'pay_later' => MODULE_PAYMENT_STRIPE_KLARNA_PAY_LATER,
      'pay_now' => MODULE_PAYMENT_STRIPE_KLARNA_PAY_NOW,
      'pay_over_time' => MODULE_PAYMENT_STRIPE_KLARNA_PAY_OVER_TIME
    ];
    $count = 0;
    //error_log(print_r($this->source, true));
    if (isset($this->source->klarna->payment_method_categories)) {
      $cats = explode(',', $this->source->klarna->payment_method_categories);
      $ex = self::unsep($this->base_constant('EXCLUDE_OPTIONS'));
      $cats = array_diff($cats, $ex);
      $count = count($cats);
      if ($count) {
        asort($cats);
        if (MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API == 'Europe') {
          foreach ($cats as $cat) {
            // frozen version:
            if ($count > 2) { $class = 'col-sm-6 col-lg-4'; }
            elseif ($count == 2) { $class = 'col-sm-6'; }
            else { $class = 'col-12'; }
            $klarna_divs .= '<div class="' . $class . '"><div class="klarna-option"><div class="klarna-option-hdr">';
            if (array_key_exists($cat, $k_opts)) {
              $klarna_divs .= ($count > 1 ? tep_draw_radio_field('klarna_option', $cat, false, 'id="klarna_' . $cat . '_option" required="required" class="form-control-inline"') : tep_draw_hidden_field('klarna_option', $cat, 'id="klarna_' . $cat . '_option"')) . ' ' . $k_opts[$cat];
            }
            $klarna_divs .= '</div><div id="klarna_' . $cat . '_container"></div></div></div>';
          }
        } else { // US API has a combined container
          $klarna_divs .= '<div class="col-12" id="klarna-combined-container"></div>';
        }
      }
    }

    $content .= '<div id="stripe_karna" class="row">' . $klarna_divs . '</div><div id="klarna-errors" role="alert" class="text-danger payment-errors">' . $caught_error . '</div>';

    // frozen version:
    $script = <<<EOS
<script>
 let paydiv = document.querySelector('#stripe_karna').parentElement.parentElement;
 if (paydiv.classList.contains('col-sm-6')) {
  paydiv.classList.remove('col-sm-6');
  paydiv.classList.add('col-sm-12');
 }
</script>
EOS;
    if (strlen($caught_error)) {
      // error thrown so don't try to load klarna just disable button
      $script .= <<<EOS
<script>
  document.querySelector('form[name="checkout_confirmation"] .btn-success').disabled = true;
</script>
EOS;
    } else {
  
      $matc = MODULE_PAYMENT_STRIPE_KLARNA_MATC_CONTINUE;
      
      $formcats = implode(',',$cats);
      $param = (MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API == 'Europe' ? 'container: "#klarna_" + category + "_container",
        payment_method_category: category,' : 'container: "#klarna-combined-container",
      payment_method_categories: available_categories,
      instance_id : "klarna-payments-instance-id"');
      $no_choice = MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API == 'US' ? "return true;\n" : '';
      $script .= <<<EOS
<script>
let confirmBtn = document.querySelector('form[name="checkout_confirmation"] .btn-success');
let MATC = document.getElementById('inputMATC');
  
// need to keep track of available options in global scope
var option_count = 0;
// Load the Klarna JavaScript SDK
window.klarnaAsyncCallback = function () {

  // Initialize the SDK
  Klarna.Payments.init({
    client_token: '{$this->source->klarna->client_token}',
  });

  // Load the widget for each payment method category:
  // - pay_later
  // - pay_over_time
  // - pay_now
  var catstring = '{$formcats}';
  var available_categories = catstring.split(',');
  option_count = available_categories.length;
  if (option_count < 1) {
    confirmBtn.disabled = true;
  } else {
    available_categories.forEach(function (category) {
      Klarna.Payments.load({
        {$param}
      }, function(res) {
        if (res.show_form) {
          /*
          * this payment method category can be used, allow the customer
          * to choose it in your interface.
          */
        } else {
          // this payment method category is not available
          var choice = document.getElementById("klarna_" + category + "_option");
          if (typeof choice !== 'undefined') {
            choice.parentNode.removeChild(choice);
          }
          option_count--;
          if (option_count < 1) {
            confirmBtn.disabled = true;
          }
        }
      });
    });
  }
};

function getSelectedCategory() {
  {$no_choice}var choices = document.getElementsByName('klarna_option');
  var chosen;
  for (var i = 0; i < choices.length; i++) {
    if (choices.length == 1 || choices[i].checked) {
      chosen = choices[i];
      break;
    }
  }
  if (chosen) {
    return chosen.value;
  }
  return false;
}
EOS;

      $param = (MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API == 'Europe' ? 'payment_method_category: selectedCategory' : 'instance_id : "klarna-payments-instance-id"');
      $script .= <<<EOS

confirmBtn.addEventListener("click", function(e){

  // stop the form submitting unless it gets approved
  e.preventDefault();
  
  if (null !== MATC && MATC.checked == false) {
    alert('$matc');
    return;
  }

  // get the category the customer chose(using your own code)
  var selectedCategory = getSelectedCategory();
  // Submit the payment for authorization with the selected category
  if (selectedCategory) {
    Klarna.Payments.authorize({
      {$param}
    }, function(res) {
      if (res.approved) {
        // Payment has been authorized - submit the form
        document.querySelector('form[name="checkout_confirmation"]').requestSubmit();
      } else {
        // Payment not authorized or an error has occurred
        if (res.error) {
        } else {
          // handle other states
          if (res.show_form == false) {
            var choice = document.getElementById("klarna_" + selectedCategory + "_option");
            if (typeof choice !== 'undefined') {
              choice.parentNode.removeChild(choice);
            }
            option_count--;
            if (option_count < 1) {
              confirmBtn.disabled = true;
            }
          }
        }
      }
    })
  }
});

</script>

<script src="https://x.klarnacdn.net/kp/lib/v1/api.js" async></script>
EOS;
      }

      if ($this->oscTemplateClassExists()) {
        $GLOBALS['oscTemplate']->addBlock($script, 'footer_scripts');
      } else {
        $content .= $script;
      }

      $confirmation = array('title' => $content);

      return $confirmation;
    }
  }

  private function extract_cart_id() {
    return substr($_SESSION['cart_Stripe_Klarna_ID'], 0, strpos($_SESSION['cart_Stripe_Klarna_ID'], '-'));
  }

  private function extract_order_id() {
    return substr($_SESSION['cart_Stripe_Klarna_ID'], strpos($_SESSION['cart_Stripe_Klarna_ID'], '-') + 1);
  }

    function process_button() {
      return false;
    }

    function before_process() {

      $this->after_process();
    }

    function after_process() {
      global $cart, $order, $order_totals, $currencies, $OSCOM_Hooks, $insert_id, $products_ordered, $cart_Stripe_Klarna_ID, $stripe_source_id;

      if (isset($_SESSION['cart_Stripe_Klarna_ID'])) {
        $order_id = $this->extract_order_id();
        
        // klarna works async so the order may just be submitted, successful or failed - act accordingly
        // 
        $o_check_q = tep_db_query('select orders_id, orders_status from orders where orders_id = ' . (int)$order_id);
        if (tep_db_num_rows($o_check_q)) {
          $o_check = tep_db_fetch_array($o_check_q);
          switch($o_check['orders_status']) {
            case MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID :
              // get the error - where from? - and go back to payment
              $error = $this->get_last_event_error($order_id, $_SESSION['customer_id']);
              tep_redirect(tep_href_link('checkout_payment.php', 'error='.$error));
              break;
            case MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID :
              // set to application status and go on to checkout success
              $sql_data_array = ['orders_status' => MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID];
              tep_db_perform('orders', $sql_data_array, 'update', 'orders_id = ' . (int)$order_id);
              $sql_data_array = [
                'orders_id' => $order_id,
                'orders_status_id' => MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID,
                'comments' => MODULE_PAYMENT_STRIPE_KLARNA_TRAN_INCOMPLETE,
                'date_added' => 'now()',
              ];
              tep_db_perform('orders_status_history', $sql_data_array);
              break;
            default :
              // successful? drop through to checkout success
          }
          tep_redirect(tep_href_link('checkout_success.php', '', 'SSL'));
        }
        
      }
      // if you're here you don't want to go through to checkout success
      tep_redirect(tep_href_link('index.php'));
    }
  
    function get_last_event_error($order_id, $customer_id) {
      // check if we can find a recent error event for this customer
      // if so set a detailed error in session variable, otherwise type is general
      global $klarna_error;
      if (!tep_session_is_registered('klarna_error')) {
        tep_session_register('klarna_error');
      }
      try {
        $stripe = new \Stripe\StripeClient($this->secret_key);
        if ((int)MODULE_PAYMENT_STRIPE_KLARNA_EVENT_NUMBER > 0) {
          $limit = (int)MODULE_PAYMENT_STRIPE_KLARNA_EVENT_NUMBER;
        } else {
          $limit = 5;
        }
        $elist = $stripe->events->all(['limit' => $limit]);
        
        if (is_array($elist->data) && count($elist->data)) {
          
          // lets hope they're LIFO
          foreach ($elist->data as $event) {
            if (isset($event->data->object->metadata['customer_id']) && $event->data->object->metadata['customer_id'] == $customer_id && isset($event->data->object->metadata['order_id']) && $event->data->object->metadata['order_id']) {
              $klarna_error = [
                'type' => $event->type,
                'code' => $event->data->object->failure_code,
                'msg' => $event->data->object->failure_message,
              ];
              return 'error';
            }
          }
        }
        $klarna_error = ['type' => 'notfound'];
        
      } catch (Exception $e) {
        $klarna_error = ['type' => 'general'];
      }
      return 'error';
    }
  
    function post_process() {
      // deferred from after_process to as late as possible - run from a hook in checkout success
      // redirect to checkout if it failed after checkout process or do session cleanup otherwise 
      global $cart, $order, $order_totals, $currencies, $OSCOM_Hooks, $insert_id, $products_ordered, $cart_Stripe_Klarna_ID, $stripe_source_id, $messageStack, $klarna_error;
      
      $return = '';

      if (isset($_SESSION['cart_Stripe_Klarna_ID'])) {
        $chk_order_id = $this->extract_order_id();
        
        if ($chk_order_id == $GLOBALS['order_id']) {
          
          $chk_q = tep_db_query('select orders_status from orders where orders_id = ' . (int)$GLOBALS['order_id']);
          if (tep_db_num_rows($chk_q)) {
            $chk_o = tep_db_fetch_array($chk_q);
            if ($chk_o['orders_status'] == MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID) {

              $error = $this->get_last_event_error($GLOBALS['order_id'], $_SESSION['customer_id']);
              tep_redirect(tep_href_link('checkout_payment.php', 'error=' . $error, 'SSL'));
            }
            
            if ($chk_o['orders_status'] == MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID) {
              // application has not yet been accepted - order may not be complete
              $messageStack->add('header', MODULE_PAYMENT_STRIPE_KLARNA_TRAN_INCOMPLETE, 'warning');
            }

            $GLOBALS['hooks']->register_pipeline('reset');

            unset($_SESSION['stripe_source_id']);
            unset($_SESSION['cart_Stripe_Klarna_ID']);
            
          }
        }
      }
    }
  
    function complete_order_email($order_id, $customer_id) {
      // this is run from webhook (so not customer session)
      global $currencies, $customer, $order;
      
      if ((int)$order_id > 0 && tep_db_num_rows($oq = tep_db_query('select orders_status from orders where orders_id = ' . (int)$order_id . ' and customers_id = ' . (int)$customer_id))) {
        
        $o = $oq->fetch_assoc();

        if ($o['orders_status'] == $this->base_constant('APPLICATION_ORDER_STATUS_ID')) {

          $o_status = $this->base_constant('ORDER_STATUS_ID') ? $this->base_constant('ORDER_STATUS_ID'): DEFAULT_ORDERS_STATUS_ID;
          
          $sql_data_array = ['orders_status' => $o_status];
          tep_db_perform('orders', $sql_data_array, 'update', 'orders_id = ' . (int)$order_id);

          $order = new order((int)$order_id);
          $customer = new customer((int)$customer_id);

          $customer_comment = '';
          $com_q = tep_db_query(sprintf('SELECT comments FROM orders_status_history WHERE orders_id = %d ORDER BY orders_status_history_id LIMIT 1', (int)$order_id));
          if ($com = $com_q->fetch_assoc()) {
            $customer_comment = $com['comments'];
          }
          
          $data = [
            'orders_id' => (int)$order_id,
            'customers_name' => $order->customer['name'],
            'customers_email_address' => $order->customer['email_address'],
            'notify_comments' => MODULE_PAYMENT_STRIPE_KLARNA_CONFIRMED . (strlen($customer_comment) ? sprintf(MODULE_PAYMENT_STRIPE_KLARNA_CUSTOMER_COMMENT, $customer_comment) : ''),
            'date_purchased' => $order->info['date_purchased'],
            'status_name' => $order->info['orders_status']
          ];
          if (class_exists('Notifications')) {
            $notified = Notifications::notify('update_order_webhook', $data) ? 1 : 0;
          } else {
            $notified = tep_notify('update_order_webhook', $data) ? 1 : 0;
          }

          $sql_data_array = [
            'orders_id' => $order_id,
            'orders_status_id' => $o_status,
            'customer_notified' => $notified,
            'comments' => MODULE_PAYMENT_STRIPE_KLARNA_CONFIRMED,
            'date_added' => 'now()',
          ];
          tep_db_perform('orders_status_history', $sql_data_array);

          $GLOBALS['customer_notification'] = 0; // suppress email in after process (just do the stock bit)

          $GLOBALS['hooks']->register_pipeline('after');

        } else {
                
          error_log( sprintf(MODULE_PAYMENT_STRIPE_KLARNA_MISMATCH_ORDER_STATUS, $order_id, $o['orders_status']));
              
        }

      } else {
        
        error_log( sprintf(MODULE_PAYMENT_STRIPE_KLARNA_MISMATCH_ORDER_CUSTOMER, $order_id, $customer_id));
       
      }
      
    }

    function get_error() {

      $message = MODULE_PAYMENT_STRIPE_KLARNA_ERROR_GENERAL;
      if (isset($_GET['error']) && $_GET['error'] == 'error' && isset($_SESSION['klarna_error']) && is_array($_SESSION['klarna_error'])) {
        if (isset($_SESSION['klarna_error']['type'])) {
          $type = $_SESSION['klarna_error']['type'];
          switch ($type) {
            case 'general' :
              break;
            case 'notfound' :
              $message = MODULE_PAYMENT_STRIPE_KLARNA_ERROR_NOT_FOUND;
              break;
            default : // should relate to a specific event
              if (strpos('.', $type)) {
                $obj = substr($type, 0, strpos('.', $type));
                switch ($obj) {
                  case 'source' :
                    $message = sprintf(MODULE_PAYMENT_STRIPE_KLARNA_ERROR_SOURCE, $_SESSION['klarna_error']['code'], $_SESSION['klarna_error']['msg']);
                    break;
                  case 'charge' :
                    $message = sprintf(MODULE_PAYMENT_STRIPE_KLARNA_ERROR_CHARGE, $_SESSION['klarna_error']['code'], $_SESSION['klarna_error']['msg']);
                    break;
                  default :
                    $message = sprintf(MODULE_PAYMENT_STRIPE_KLARNA_ERROR_UNKOWN, $type);
                }
              }
          }
        }
      }

      $error = [
        'title' => MODULE_PAYMENT_STRIPE_KLARNA_ERROR_TITLE,
        'error' => $message
      ];

      return $error;
    }

 /*   function check() {
      if (!isset($this->_check)) {
        $check_query = tep_db_query("select configuration_value from configuration where configuration_key = 'MODULE_PAYMENT_STRIPE_KLARNA_STATUS'");
        $this->_check = tep_db_num_rows($check_query);
      }
      return $this->_check;
    }

    function install($parameter = null) {
      $params = $this->getParams();

      if (isset($parameter)) {
        if (isset($params[$parameter])) {
          $params = array($parameter => $params[$parameter]);
        } else {
          $params = array();
        }
      }

      foreach ($params as $key => $data) {
        $sql_data_array = [
          'configuration_title' => $data['title'],
          'configuration_key' => $key,
          'configuration_value' => (isset($data['value']) ? $data['value'] : ''),
          'configuration_description' => $data['desc'],
          'configuration_group_id' => '6',
          'sort_order' => '0',
          'date_added' => 'now()'
        ];

        if (isset($data['set_func'])) {
            $sql_data_array['set_function'] = $data['set_func'];
        }

        if (isset($data['use_func'])) {
            $sql_data_array['use_function'] = $data['use_func'];
        }

        tep_db_perform("configuration", $sql_data_array);
      }
    } */

    function event_log($customer_id, $action, $request, $response) {
      if (MODULE_PAYMENT_STRIPE_KLARNA_LOG == "True") {
        tep_db_query("insert into stripe_event_log (customer_id, action, request, response, date_added) values ('" . $customer_id . "', '" . $action . "', '" . tep_db_input($request) . "', '" . tep_db_input($response) . "', now())");
      }
    }
/*  
    // not needed from phoenix 1.0.7.8 when flags added to abstract_payment_module
    public static function ensure_order_status($constant_name, $order_status_name, $public_flag = 0, $downloads_flag = 0) {
      if (defined($constant_name)) {
        return constant($constant_name);
      }

      $check_sql = "SELECT orders_status_id FROM orders_status WHERE orders_status_name = '" . tep_db_input($order_status_name) . "' LIMIT 1";
      $check_query = tep_db_query($check_sql);

      if (tep_db_num_rows($check_query) < 1) {
        $next_id = tep_db_fetch_array(tep_db_query("SELECT MAX(orders_status_id) + 1 AS next_id FROM orders_status"))['next_id'] ?? 1;

        tep_db_query(sprintf(<<<'EOSQL'
INSERT INTO orders_status (orders_status_id, language_id, orders_status_name, public_flag, downloads_flag)
 SELECT
   %d AS orders_status_id,
   l.languages_id AS language_id,
   '%s' AS orders_status_name,
   %d AS public_flag,
   %d AS downloads_flag
 FROM languages l
 ORDER BY l.sort_order
EOSQL
          , (int)$next_id, tep_db_input($order_status_name), (int)$public_flag, (int)$downloads_flag));

        $check_query = tep_db_query($check_sql);
      }

      $check = tep_db_fetch_array($check_query);
      return $check['orders_status_id'];
    }
*/
    function get_parameters() {
      if (tep_db_num_rows(tep_db_query("show tables like 'stripe_event_log'")) != 1) {
        $sql = <<<EOD
CREATE TABLE IF NOT EXISTS stripe_event_log (
  id int NOT NULL auto_increment,
  customer_id int NOT NULL,
  action varchar(255) NOT NULL,
  request TEXT NOT NULL,
  response TEXT NOT NULL,
  date_added datetime NOT NULL,
  PRIMARY KEY (id)
);
EOD;

        tep_db_query($sql);
      } else {
        $chk_q = tep_db_query("show columns from stripe_event_log where Field in ('request', 'response') and Type like 'varchar%'");
        if (tep_db_num_rows($chk_q)) {
          while ($col = tep_db_fetch_array($chk_q)) {
            tep_db_query('alter table stripe_event_log modify `' . $col['Field'] . '` text');
          }
        }
      }

      $params = [
        'MODULE_PAYMENT_STRIPE_KLARNA_STATUS' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_STATUS_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_STATUS_DESC,
          'value' => 'True',
          'set_func' => 'tep_cfg_select_option([\'True\', \'False\'], '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API_DESC,
          'value' => 'Europe',
          'set_func' => 'tep_cfg_select_option([\'Europe\', \'US\'], '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SERVER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SERVER_DESC,
          'value' => 'Live',
          'set_func' => 'tep_cfg_select_option([\'Live\', \'Test\'], '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LIVE_PUBLISHABLE_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_PUB_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_PUB_DESC,
          'use_func' => 'stripe_klarna::disp_key',
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LIVE_SECRET_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_SECRET_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_SECRET_DESC,
          'use_func' => 'stripe_klarna::disp_key',
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LIVE_WEBHOOK_SECRET' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_WEBHOOK_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_WEBHOOK_DESC,
          'use_func' => 'stripe_klarna::disp_key',
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TEST_PUBLISHABLE_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_PUB_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_PUB_DESC,
          'use_func' => 'stripe_klarna::disp_key',
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TEST_SECRET_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_SECRET_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_SECRET_DESC,
          'use_func' => 'stripe_klarna::disp_key',
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TEST_WEBHOOK_SECRET' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_WEBHOOK_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_WEBHOOK_DESC,
          'use_func' => 'stripe_klarna::disp_key',
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LOG' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LOG_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LOG_DESC,
          'value' => 'True',
          'set_func' => 'tep_cfg_select_option([\'True\', \'False\'], '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_NEW_ORDER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_NEW_ORDER_DESC,
          'value' => self::ensure_order_status('MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID', MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_TEXT),
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_APP_ORDER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_APP_ORDER_DESC,
          'value' => self::ensure_order_status('MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID', MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_TEXT, 1),
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROCESSED_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROCESSED_DESC,
          'value' => '0',
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_FAIL_ORDER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_FAIL_ORDER_DESC,
          'value' => self::ensure_order_status('MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID', MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_TEXT, 1),
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TRANSACTION_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TRANSACTION_DESC,
          'value' => self::ensure_order_status('MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_ID', MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_TEXT),
          'set_func' => 'tep_cfg_pull_down_order_statuses(',
          'use_func' => 'tep_get_order_status_name'
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_ZONE' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_ZONE_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_ZONE_DESC,
          'value' => '0',
          'use_func' => 'tep_get_zone_class_title',
          'set_func' => 'tep_cfg_pull_down_zone_classes('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_DISCOUNT_MODULES' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_DISCOUNT_MOD_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_DISCOUNT_MOD_DESC,
          'value' => '',
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_AUTH_CAPTURE' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_AUTH_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_AUTH_DESC,
          'value' => 'Capture',
          'set_func' => 'tep_cfg_select_option([\'Authorise\', \'Capture\'], '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_EXCLUDE_OPTIONS' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EXCLUDE_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EXCLUDE_DESC,
          'value' => '',
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_EVENT_NUMBER' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EVENT_NUM_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EVENT_NUM_DESC,
          'value' => '5',
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_PROXY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROXY_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROXY_DESC
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_DEBUG_EMAIL' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EMAIL_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EMAIL_DESC
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_SORT_ORDER' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SORT_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SORT_DESC,
          'value' => '0'
        ]
      ];

      return $params;
    }
/*
    function sendTransactionToGateway($url, $parameters = null, $curl_opts = array()) {
        $server = parse_url($url);

        if (isset($server['port']) === false) {
            $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
        }

        if (isset($server['path']) === false) {
            $server['path'] = '/';
        }

        $header = array('Stripe-Version: ' . $this->api_version,
            'User-Agent: OSCOM ' . tep_get_version());

        if (is_array($parameters) && !empty($parameters)) {
            $post_string = '';

            foreach ($parameters as $key => $value) {
                $post_string .= $key . '=' . urlencode(utf8_encode(trim($value))) . '&';
            }

            $post_string = substr($post_string, 0, -1);

            $parameters = $post_string;
        }

        $curl = curl_init($server['scheme'] . '://' . $server['host'] . $server['path'] . (isset($server['query']) ? '?' . $server['query'] : ''));
        curl_setopt($curl, CURLOPT_PORT, $server['port']);
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_USERPWD, $this->secret_key . ':');
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        if (!empty($parameters)) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $parameters);
        }

        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        if (tep_not_null(MODULE_PAYMENT_STRIPE_KLARNA_PROXY)) {
            curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, true);
            curl_setopt($curl, CURLOPT_PROXY, MODULE_PAYMENT_STRIPE_KLARNA_PROXY);
        }

        if (!empty($curl_opts)) {
            foreach ($curl_opts as $key => $value) {
                curl_setopt($curl, $key, $value);
            }
        }

        $result = curl_exec($curl);

        curl_close($curl);

        return $result;
    }

    function getTestLinkInfo() {
        $dialog_title = MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_TITLE;
        $dialog_button_close = MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_BUTTON_CLOSE;
        $dialog_success = MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_SUCCESS;
        $dialog_failed = MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_FAILED;
        $dialog_error = MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_ERROR;
        $dialog_connection_time = MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_TIME;

        $test_url = tep_href_link('modules.php', 'set=payment&module=' . $this->code . '&action=install&subaction=conntest');

        $js = <<<EOD
<script>
$(function() {
  $('#tcdprogressbar').progressbar({
    value: false
  });
});

function openTestConnectionDialog() {
  var d = $('<div>').html($('#testConnectionDialog').html()).dialog({
    modal: true,
    title: '{$dialog_title}',
    buttons: {
      '{$dialog_button_close}': function () {
        $(this).dialog('destroy');
      }
    }
  });

  var timeStart = new Date().getTime();

  $.ajax({
    url: '{$test_url}'
  }).done(function(data) {
    if ( data == '1' ) {
      d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: green;">{$dialog_success}</p>');
    } else {
      d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: red;">{$dialog_failed}</p>');
    }
  }).fail(function() {
    d.find('#testConnectionDialogProgress').html('<p style="font-weight: bold; color: red;">{$dialog_error}</p>');
  }).always(function() {
    var timeEnd = new Date().getTime();
    var timeTook = new Date(0, 0, 0, 0, 0, 0, timeEnd-timeStart);

    d.find('#testConnectionDialogProgress').append('<p>{$dialog_connection_time} ' + timeTook.getSeconds() + '.' + timeTook.getMilliseconds() + 's</p>');
  });
}
</script>
EOD;

        $info = '<p><img src="images/icons/locked.gif" border="0">&nbsp;<a href="javascript:openTestConnectionDialog();" style="text-decoration: underline; font-weight: bold;">' . MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_LINK_TITLE . '</a></p>' .
                '<div id="testConnectionDialog" style="display: none;"><p>Server:<br />https://api.stripe.com/v3/</p><div id="testConnectionDialogProgress"><p>' . MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_GENERAL_TEXT . '</p><div id="tcdprogressbar"></div></div></div>' .
                $js;

        return $info;
    }

    function getTestConnectionResult() {
        $stripe_result = json_decode($this->sendTransactionToGateway('https://api.stripe.com/v3/charges/oscommerce_connection_test'), true);

        if (is_array($stripe_result) && !empty($stripe_result) && isset($stripe_result['error'])) {
            return 1;
        }

        return -1;
    }

    function format_raw($number, $currency_code = '', $currency_value = '') {
        global $currencies, $currency;

        if (empty($currency_code) || !$currencies->is_set($currency_code)) {
            $currency_code = $currency;
        }

        if (empty($currency_value) || !is_numeric($currency_value)) {
            $currency_value = $currencies->currencies[$currency_code]['value'];
        }

        return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']) * 100, 0, '', '');
    }
*/
    function oscTemplateClassExists() {
//      return class_exists('oscTemplate') && isset($GLOBALS['oscTemplate']) && is_object($GLOBALS['oscTemplate']) && (get_class($GLOBALS['oscTemplate']) == 'oscTemplate');
      return isset($GLOBALS['oscTemplate']) && is_object($GLOBALS['oscTemplate']) && (get_class($GLOBALS['oscTemplate']) == 'Template' || get_class($GLOBALS['oscTemplate']) == 'oscTemplate');
    }

    function sendDebugEmail($response = array()) {
        if (tep_not_null(MODULE_PAYMENT_STRIPE_KLARNA_DEBUG_EMAIL)) {
            $email_body = '';

            if (!empty($response)) {
                $email_body .= 'RESPONSE:' . "\n\n" . print_r($response, true) . "\n\n";
            }

            if (!empty($_POST)) {
                $email_body .= '$_POST:' . "\n\n" . print_r($_POST, true) . "\n\n";
            }

            if (!empty($_GET)) {
                $email_body .= '$_GET:' . "\n\n" . print_r($_GET, true) . "\n\n";
            }

            if (!empty($email_body)) {
                tep_mail('', MODULE_PAYMENT_STRIPE_KLARNA_DEBUG_EMAIL, 'Stripe-Klarna Debug E-Mail', trim($email_body), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
            }
        }
    }

}
/*
// middling phoenix 1.7. versions aren't all there
if (! function_exists('tep_address_format')) {
////
// Return a formatted address
// TABLES: address_format
  function tep_address_format($address_format_id, $address, $html, $boln, $eoln) {
    $address_format_query = tep_db_query("select address_format as format from address_format where address_format_id = '" . (int)$address_format_id . "'");
    $address_format = tep_db_fetch_array($address_format_query);

    $company = htmlspecialchars($address['company']);
    if (isset($address['firstname']) && tep_not_null($address['firstname'])) {
      $firstname = htmlspecialchars($address['firstname']);
      $lastname = htmlspecialchars($address['lastname']);
    } elseif (isset($address['name']) && tep_not_null($address['name'])) {
      $firstname = htmlspecialchars($address['name']);
      $lastname = '';
    } else {
      $firstname = '';
      $lastname = '';
    }
    $street = htmlspecialchars($address['street_address']);
    $suburb = htmlspecialchars($address['suburb']);
    $city = htmlspecialchars($address['city']);
    $state = htmlspecialchars($address['state']);
    if (isset($address['country_id']) && tep_not_null($address['country_id'])) {
      $country = tep_get_country_name($address['country_id']);

      if (isset($address['zone_id']) && tep_not_null($address['zone_id'])) {
        $state = tep_get_zone_code($address['country_id'], $address['zone_id'], $state);
      }
    } elseif (isset($address['country']) && tep_not_null($address['country'])) {
      $country = htmlspecialchars($address['country']['title']);
    } else {
      $country = '';
    }
    $postcode = htmlspecialchars($address['postcode']);
    $zip = $postcode;

    if ($html) {
// HTML Mode
      $HR = '<hr />';
      $hr = '<hr />';
      if ( ($boln == '') && ($eoln == "\n") ) { // Values not specified, use rational defaults
        $CR = '<br />';
        $cr = '<br />';
        $eoln = $cr;
      } else { // Use values supplied
        $CR = $eoln . $boln;
        $cr = $CR;
      }
    } else {
// Text Mode
      $CR = $eoln;
      $cr = $CR;
      $HR = '----------------------------------------';
      $hr = '----------------------------------------';
    }

    $statecomma = '';
    $streets = $street;
    if ($suburb != '') $streets = $street . $cr . $suburb;
    if ($state != '') $statecomma = $state . ', ';

    $fmt = $address_format['format'];
    eval("\$address = \"$fmt\";");

    if ( (ACCOUNT_COMPANY == 'true') && (tep_not_null($company)) ) {
      $address = $company . $cr . $address;
    }

    return $address;
  }
}
*/