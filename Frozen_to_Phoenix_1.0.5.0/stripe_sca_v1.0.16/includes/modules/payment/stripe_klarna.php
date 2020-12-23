<?php

/*
  Klarna via Stripe (payment sources)
  
  author: John Ferguson @BrockleyJohn oscommerce@sewebsites.net

  Copyright (c) 2020 SE websites

  Released under MIT License without warranty express or implied
 */

require_once dirname(__FILE__) . '/stripe_sca/init.php';

class stripe_klarna {

    var $code, $title, $description, $enabled, $intent;

    function __construct() {
        global $PHP_SELF, $order, $payment;

        $this->signature = 'stripe|stripe_klarna|1.0.16|2.3';
        $this->api_version = '7.51.0';

        $this->code = get_class($this);
        $this->title = MODULE_PAYMENT_STRIPE_KLARNA_TEXT_TITLE;
        $this->public_title = MODULE_PAYMENT_STRIPE_KLARNA_TEXT_PUBLIC_TITLE;
        $this->description = MODULE_PAYMENT_STRIPE_KLARNA_TEXT_DESCRIPTION;
        $this->sort_order = defined('MODULE_PAYMENT_STRIPE_KLARNA_SORT_ORDER') ? MODULE_PAYMENT_STRIPE_KLARNA_SORT_ORDER : 0;
        $this->enabled = defined('MODULE_PAYMENT_STRIPE_KLARNA_STATUS') && (MODULE_PAYMENT_STRIPE_KLARNA_STATUS == 'True') ? true : false;
        $this->order_status = defined('MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID') && ((int) MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID > 0) ? (int) MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID : 0;

        if (defined('MODULE_PAYMENT_STRIPE_KLARNA_STATUS')) {
            if (MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER == 'Test') {
                $this->title .= ' [Test]';
                $this->public_title .= ' (Test)';
            }

            $this->description .= $this->getTestLinkInfo();
        }

        if (!function_exists('curl_init')) {
            $this->description = '<div class="secWarning">' . MODULE_PAYMENT_STRIPE_KLARNA_ERROR_ADMIN_CURL . '</div>' . $this->description;

            $this->enabled = false;
        }

        if ($this->enabled === true) {
            if ((MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER == 'Live' && (!tep_not_null(MODULE_PAYMENT_STRIPE_KLARNA_LIVE_PUBLISHABLE_KEY) || !tep_not_null(MODULE_PAYMENT_STRIPE_KLARNA_LIVE_SECRET_KEY))) || (MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER == 'Test' && (!tep_not_null(MODULE_PAYMENT_STRIPE_KLARNA_TEST_PUBLISHABLE_KEY) || !tep_not_null(MODULE_PAYMENT_STRIPE_KLARNA_TEST_SECRET_KEY)))) {
                $this->description = '<div class="secWarning">' . MODULE_PAYMENT_STRIPE_KLARNA_ERROR_ADMIN_CONFIGURATION . '</div>' . $this->description;

                $this->enabled = false;
            } else {
              $this->publishable_key = MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER == 'Live' ? MODULE_PAYMENT_STRIPE_KLARNA_LIVE_PUBLISHABLE_KEY : MODULE_PAYMENT_STRIPE_KLARNA_TEST_PUBLISHABLE_KEY;
              $this->secret_key = MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER == 'Live' ? MODULE_PAYMENT_STRIPE_KLARNA_LIVE_SECRET_KEY : MODULE_PAYMENT_STRIPE_KLARNA_TEST_SECRET_KEY;
              $this->webhook_secret = MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER == 'Live' ? MODULE_PAYMENT_STRIPE_KLARNA_LIVE_WEBHOOK_SECRET : MODULE_PAYMENT_STRIPE_KLARNA_TEST_WEBHOOK_SECRET;
            }
        }

        if ($this->enabled === true) {
            if (isset($order) && is_object($order)) {
                $this->update_status();
            }
        }

        if ((basename($PHP_SELF) == 'modules.php') && isset($_GET['action']) && ($_GET['action'] == 'install') && isset($_GET['subaction']) && ($_GET['subaction'] == 'conntest')) {
            echo $this->getTestConnectionResult();
            exit;
        }
    }

    function update_status() {
        global $order;

        if (($this->enabled == true) && ((int) MODULE_PAYMENT_STRIPE_KLARNA_ZONE > 0)) {
            $check_flag = false;
            $check_query = tep_db_query("select zone_id from zones_to_geo_zones where geo_zone_id = '" . MODULE_PAYMENT_STRIPE_KLARNA_ZONE . "' and zone_country_id = '" . $order->delivery['country']['id'] . "' order by zone_id");
            while ($check = tep_db_fetch_array($check_query)) {
                if ($check['zone_id'] < 1) {
                    $check_flag = true;
                    break;
                } elseif ($check['zone_id'] == $order->delivery['zone_id']) {
                    $check_flag = true;
                    break;
                }
            }

            if ($check_flag == false) {
                $this->enabled = false;
            }
        }
    }

    function javascript_validation() {
        return false;
    }

    function selection() {
        return array('id' => $this->code,
            'module' => $this->public_title);
    }

    function pre_confirmation_check() {
        return false;
    }

    function confirmation() {
        global $oscTemplate, $cartID, $cart_Stripe_Klarna_ID, $customer_id, $languages_id, $order, $currencies, $currency, $stripe_source_id, $order_total_modules, $shipping, $insert_id, $klarna_error;

        if (tep_session_is_registered('cartID')) {
            $insert_order = false;

            if (tep_session_is_registered('cart_Stripe_Klarna_ID')) {
                $order_id = substr($cart_Stripe_Klarna_ID, strpos($cart_Stripe_Klarna_ID, '-') + 1);

                $curr_check = tep_db_query("select currency from orders where orders_id = '" . (int) $order_id . "'");
                $curr = tep_db_fetch_array($curr_check);

                if (($curr['currency'] != $order->info['currency']) || ($cartID != substr($cart_Stripe_Klarna_ID, 0, strpos($cart_Stripe_Klarna_ID, '-')))) {
                  
                  // can't update amount for klarna so force recreate
                  if (isset($stripe_source_id)) unset($stripe_source_id);
                  if (tep_session_is_registered('stripe_source_id')) {
                      tep_session_unregister('stripe_source_id');
                  }

                    $check_query = tep_db_query('select orders_id from orders_status_history where orders_id = "' . (int) $order_id . '" limit 1');

                    if (tep_db_num_rows($check_query) < 2) {
                        tep_db_query('delete from orders where orders_id = "' . (int) $order_id . '"');
                        tep_db_query('delete from orders_total where orders_id = "' . (int) $order_id . '"');
                        tep_db_query('delete from orders_status_history where orders_id = "' . (int) $order_id . '"');
                        tep_db_query('delete from orders_products where orders_id = "' . (int) $order_id . '"');
                        tep_db_query('delete from orders_products_attributes where orders_id = "' . (int) $order_id . '"');
                        tep_db_query('delete from orders_products_download where orders_id = "' . (int) $order_id . '"');
                    }

                    $insert_order = true;
                }
            } else {
                $insert_order = true;
            }

            if ($insert_order == true) {

                if (isset($order->info['payment_method_raw'])) {
                    $order->info['payment_method'] = $order->info['payment_method_raw'];
                    unset($order->info['payment_method_raw']);
                }

                $sql_data_array = array('customers_id' => $customer_id,
                    'customers_name' => $order->customer['firstname'] . ' ' . $order->customer['lastname'],
                    'customers_company' => $order->customer['company'],
                    'customers_street_address' => $order->customer['street_address'],
                    'customers_suburb' => $order->customer['suburb'],
                    'customers_city' => $order->customer['city'],
                    'customers_postcode' => $order->customer['postcode'],
                    'customers_state' => $order->customer['state'],
                    'customers_country' => $order->customer['country']['title'],
                    'customers_telephone' => $order->customer['telephone'],
                    'customers_email_address' => $order->customer['email_address'],
                    'customers_address_format_id' => $order->customer['format_id'],
                    'delivery_name' => $order->delivery['firstname'] . ' ' . $order->delivery['lastname'],
                    'delivery_company' => $order->delivery['company'],
                    'delivery_street_address' => $order->delivery['street_address'],
                    'delivery_suburb' => $order->delivery['suburb'],
                    'delivery_city' => $order->delivery['city'],
                    'delivery_postcode' => $order->delivery['postcode'],
                    'delivery_state' => $order->delivery['state'],
                    'delivery_country' => $order->delivery['country']['title'],
                    'delivery_address_format_id' => $order->delivery['format_id'],
                    'billing_name' => $order->billing['firstname'] . ' ' . $order->billing['lastname'],
                    'billing_company' => $order->billing['company'],
                    'billing_street_address' => $order->billing['street_address'],
                    'billing_suburb' => $order->billing['suburb'],
                    'billing_city' => $order->billing['city'],
                    'billing_postcode' => $order->billing['postcode'],
                    'billing_state' => $order->billing['state'],
                    'billing_country' => $order->billing['country']['title'],
                    'billing_address_format_id' => $order->billing['format_id'],
                    'payment_method' => $order->info['payment_method'],
                    'cc_type' => $order->info['cc_type'],
                    'cc_owner' => $order->info['cc_owner'],
                    'cc_number' => $order->info['cc_number'],
                    'cc_expires' => $order->info['cc_expires'],
                    'date_purchased' => 'now()',
                    'last_modified' => 'now()',
                    'orders_status' => $order->info['order_status'],
                    'currency' => $order->info['currency'],
                    'currency_value' => $order->info['currency_value']);

                tep_db_perform("orders", $sql_data_array);

                $insert_id = tep_db_insert_id();

                if (is_array($order_total_modules->modules)) {
                    foreach ($order_total_modules->modules as $value) {
                        $class = substr($value, 0, strrpos($value, '.'));
                        if ($GLOBALS[$class]->enabled) {
                            $size = sizeof($GLOBALS[$class]->output);
                            for ($i = 0; $i < $size; $i++) {
                                $sql_data_array = array('orders_id' => $insert_id,
                                    'title' => $GLOBALS[$class]->output[$i]['title'],
                                    'text' => $GLOBALS[$class]->output[$i]['text'],
                                    'value' => $GLOBALS[$class]->output[$i]['value'],
                                    'class' => $GLOBALS[$class]->code,
                                    'sort_order' => $GLOBALS[$class]->sort_order);

                                tep_db_perform("orders_total", $sql_data_array);
                            }
                        }
                    }
                }

                $sql_data_array = array('orders_id' => $insert_id,
                    'orders_status_id' => $order->info['order_status'],
                    'date_added' => 'now()',
                    'customer_notified' => '',
                    'comments' => $order->info['comments']);
                tep_db_perform('orders_status_history', $sql_data_array);
              
                for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                    $sql_data_array = array('orders_id' => $insert_id,
                        'products_id' => tep_get_prid($order->products[$i]['id']),
                        'products_model' => $order->products[$i]['model'],
                        'products_name' => $order->products[$i]['name'],
                        'products_price' => $order->products[$i]['price'],
                        'final_price' => $order->products[$i]['final_price'],
                        'products_tax' => $order->products[$i]['tax'],
                        'products_quantity' => $order->products[$i]['qty']);

                    tep_db_perform("orders_products", $sql_data_array);
                  
                    $order_products_id = tep_db_insert_id();

                    $attributes_exist = '0';
                    if (isset($order->products[$i]['attributes'])) {
                        $attributes_exist = '1';
                        for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                            if (DOWNLOAD_ENABLED == 'true') {
                                $attributes_query = "select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix, pad.products_attributes_maxdays, pad.products_attributes_maxcount , pad.products_attributes_filename
                                       from products_options popt, products_options_values poval, products_attributes pa
                                       left join products_attributes_download pad
                                       on pa.products_attributes_id=pad.products_attributes_id
                                       where pa.products_id = '" . (int) $order->products[$i]['id'] . "'
                                       and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "'
                                       and pa.options_id = popt.products_options_id
                                       and pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "'
                                       and pa.options_values_id = poval.products_options_values_id
                                       and popt.language_id = '" . (int) $languages_id . "'
                                       and poval.language_id = '" . (int) $languages_id . "'";
                                $attributes = tep_db_query($attributes_query);
                            } else {
                                $attributes = tep_db_query("select popt.products_options_name, poval.products_options_values_name, pa.options_values_price, pa.price_prefix from products_options popt, products_options_values poval, products_attributes pa where pa.products_id = '" . (int) $order->products[$i]['id'] . "' and pa.options_id = '" . (int) $order->products[$i]['attributes'][$j]['option_id'] . "' and pa.options_id = popt.products_options_id and pa.options_values_id = '" . (int) $order->products[$i]['attributes'][$j]['value_id'] . "' and pa.options_values_id = poval.products_options_values_id and popt.language_id = '" . (int) $languages_id . "' and poval.language_id = '" . (int) $languages_id . "'");
                            }
                            $attributes_values = tep_db_fetch_array($attributes);

                            $sql_data_array = array('orders_id' => $insert_id,
                                'orders_products_id' => $order_products_id,
                                'products_options' => $attributes_values['products_options_name'],
                                'products_options_values' => $attributes_values['products_options_values_name'],
                                'options_values_price' => $attributes_values['options_values_price'],
                                'price_prefix' => $attributes_values['price_prefix']);

                            tep_db_perform("orders_products_attributes", $sql_data_array);

                            if ((DOWNLOAD_ENABLED == 'true') && isset($attributes_values['products_attributes_filename']) && tep_not_null($attributes_values['products_attributes_filename'])) {
                                $sql_data_array = array('orders_id' => $insert_id,
                                    'orders_products_id' => $order_products_id,
                                    'orders_products_filename' => $attributes_values['products_attributes_filename'],
                                    'download_maxdays' => $attributes_values['products_attributes_maxdays'],
                                    'download_count' => $attributes_values['products_attributes_maxcount']);

                                tep_db_perform("orders_products_download", $sql_data_array);
                            }
                        }
                    }
                }

                $cart_Stripe_Klarna_ID = $cartID . '-' . $insert_id;
                tep_session_register('cart_Stripe_Klarna_ID');

                $order_id = $insert_id;
            }
      }

      $stripe = new \Stripe\StripeClient($this->secret_key);

      $metadata = [
        "customer_id" => tep_output_string($customer_id),
        "order_id" => tep_output_string($order_id),
        "company" => tep_output_string($order->customer['company'])
      ];

      $content = '<h4>' . MODULE_PAYMENT_STRIPE_KLARNA_OPTIONS . '</h4>' . PHP_EOL;

      // set up source parameters once - start with those that can be updated
      $params = [
        'owner' => [
          'email' => $order->customer['email_address'],
          'address' => [
            'line1' => $order->customer['street_address'],
            'line2' => $order->customer['suburb'],
            'city' => $order->customer['city'],
            'state' => $order->customer['state'],
            'postal_code' => $order->customer['postcode'],
            'country' => $order->customer['country']['iso_code_2'],
          ],
        ],
        'klarna' => [
          'first_name' => $order->customer['firstname'],
          'last_name' => $order->customer['lastname'],
          'shipping_first_name' => $order->delivery['firstname'],
          'shipping_last_name' => $order->delivery['lastname'],
        ],
        'source_order' => [
          'shipping' => [
            'address' => [
              'line1' => $order->delivery['street_address'],
              'line2' => $order->delivery['suburb'],
              'city' => $order->delivery['city'],
              'state' => $order->delivery['state'],
              'postal_code' => $order->delivery['postcode'],
              'country' => $order->delivery['country']['iso_code_2'],
            ],
            'phone' => $order->customer['telephone'],
          ]
        ],
        'metadata' => $metadata
      ];
      
      $source_items = [];

      for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
                  
        $source_items[] = [
          'type' => 'sku',
          'currency' => $currency,
//          'amount' => $this->format_raw($order->products[$i]['final_price']),
          'amount' => $this->format_raw($order->products[$i]['final_price'] * $order->products[$i]['qty']),
          'description' => $order->products[$i]['name'],
          'quantity' => $order->products[$i]['qty'],
        ];
      }
      
      $ots = $GLOBALS['order_total_modules']->process();
      $charges = 0;
      foreach ($ots as $ot) {
        if ($ot['code'] == 'ot_tax' || $ot['code'] == 'ot_shipping') {
          $source_items[] = [
            'type' => substr($ot['code'], 3),
            'currency' => $currency,
            'amount' => $this->format_raw($ot['value']),
            'description' => $ot['title']
          ];
          $charges += $ot['value'];
        } elseif ($ot['code'] == 'ot_subtotal') {
          $o_subtotal = $ot['value'];
        } elseif ($ot['code'] == 'ot_total') {
          $o_total = $this->format_raw($ot['value']);
        }
      }


      // have to create source before loading the javascript because it needs the source id - or make sure it's up to date if we already have one
      if (isset($stripe_source_id)) {
        try {
          $this->source = $stripe->sources->update($stripe_source_id, $params);
          $this->event_log($customer_id, "page update source", $stripe_source_id, $this->source);
        } catch (exception $err) {
          $this->event_log($customer_id, "page update source", $stripe_source_id, $err->getMessage());
          // failed to update existing source, so create new one
          unset($stripe_source_id);
          if (tep_session_is_registered('stripe_source_id')) {
            tep_session_unregister('stripe_source_id');
          }
        }
      }
      
      $caught_error = '';

      if (!isset($stripe_source_id)) {

        if (isset($source_items) && is_array($source_items) && count($source_items)) {
          $params['source_order']['items'] = $source_items;
        }
        $params['type'] = 'klarna';
        $params['amount'] = $this->format_raw($o_subtotal + $charges);
        // need order total including shipping tax, so recalculate...
        //$params['amount'] = $this->format_raw($order->info['total']);
        
        $params['currency'] = $currency;
        $params['metadata'] = $metadata;
        $params['klarna']['product'] = 'payment';
        $params['klarna']['purchase_country'] = $order->customer['country']['iso_code_2'];
    //  $params['klarna']['custom_payment_methods'] =  // reqd for US payin4 / installments / payin4,installments (for Pay later and Slice it & both)

        try {
          $this->source = $stripe->sources->create($params);
          $this->event_log($customer_id, "page create source", json_encode($params), $this->source);
          $stripe_source_id = $this->source->id;
          tep_session_register('stripe_source_id');
        } catch (exception $err) {
          $this->event_log($customer_id, "page create source", json_encode($params), $err->getMessage() . ":\n" . $err->getTraceAsString());
          $caught_error = $err->getMessage();
          /*
          // failed to create source - this is fatal, so set error and go back to checkout_payment
          $klarna_error = [
            'type' => "page create source",
            'code' => get_class($err),
            'msg' => $err->getMessage(),
          ];
          tep_session_register('klarna_error');
          $page = tep_href_link('checkout_payment.php', 'error=error');
          $content = <<<EOS
<script>window.location.replace("{$page}");</script>
EOS;
          return array('title' => $content);
          */
        }
      } 
 
      // exit(print_r($this->source, true));
      
      $content .= '<input type="hidden" id="source_id" value="' . tep_output_string($stripe_source_id) . '" />' . '<input type="hidden" id="secret" value="' . tep_output_string($this->source->client_secret) . '" />';
      
      $karna_divs = '';
      $k_opts = [
        'pay_later' => MODULE_PAYMENT_STRIPE_KLARNA_PAY_LATER,
        'pay_now' => MODULE_PAYMENT_STRIPE_KLARNA_PAY_NOW,
        'pay_over_time' => MODULE_PAYMENT_STRIPE_KLARNA_PAY_OVER_TIME
      ];
      $count = 0;
      if (isset($this->source->klarna->payment_method_categories)) {
        $cats = explode(',', $this->source->klarna->payment_method_categories);
        $count = count($cats);
        if ($count) {
          foreach ($cats as $cat) {
            // frozen version:
            if ($count > 2) { $class = 'col-sm-6 col-lg-4'; }
            elseif ($count == 2) { $class = 'col-sm-6'; }
            else { $class = 'col-12'; }
            $karna_divs .= '<div class="' . $class . '"><div class="klarna-option"><div class="klarna-option-hdr">';
            if (array_key_exists($cat, $k_opts)) {
              $karna_divs .= ($count > 1 ? tep_draw_radio_field('klarna_option', $cat, false, 'id="klarna_' . $cat . '_option" required="required"') : tep_draw_hidden_field('klarna_option', $cat, 'id="klarna_' . $cat . '_option"')) . ' ' . $k_opts[$cat];
            }
            $karna_divs .= '</div><div id="klarna_' . $cat . '_container"></div></div></div>';
          }
        }
      }

      $content .= '<div id="stripe_karna" class="row">' . $karna_divs . '</div><div id="klarna-errors" role="alert" class="messageStackError payment-errors">' . $caught_error . '</div>';
      
      // frozen version:
      $content .= <<<EOS
<script>
$('#stripe_karna').parent().parent().removeClass('col-sm-6').addClass('col-12');
</script>
EOS;
      if (strlen($caught_error)) {
        // error thrown so don't try to load klarna just disable button
        $script = <<<EOS
<script>
$('form[name="checkout_confirmation"] .btn-success').attr('disabled', true);
</script>
EOS;
      } else {
  
      $script = <<<EOS
<script>
$('form[name="checkout_confirmation"] .btn-success').attr('id', 'pay-button');

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
  var catstring = '{$this->source->klarna->payment_method_categories}';
  var available_categories = catstring.split(',');
  option_count = available_categories.length;
  if (option_count < 1) {
    document.getElementById('pay-button').disabled = true;
  } else {
    available_categories.forEach(function (category) {
      Klarna.Payments.load({
        container: "#klarna_" + category + "_container",
        payment_method_category: category,
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
            document.getElementById('pay-button').disabled = true;
          }
        }
      });
    });
  }
};

function getSelectedCategory() {
  var choices = document.getElementsByName('klarna_option');
  var chosen;
  for (var i = 0; i < choices.length; i++) {
    if (choices[i].checked) {
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

      $script .= <<<EOS

document.getElementById("pay-button").addEventListener("click", function(e){

  // stop the form submitting unless it gets approved
  e.preventDefault();
  
  // get the category the customer chose(using your own code)
  var selectedCategory = getSelectedCategory();
  // Submit the payment for authorization with the selected category
  if (selectedCategory) {
    Klarna.Payments.authorize({
      payment_method_category: selectedCategory
    }, function(res) {
      if (res.approved) {
        // Payment has been authorized - submit the form
        $('form[name="checkout_confirmation"]').submit();
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
              document.getElementById('pay-button').disabled = true;
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
      
      if ($this->templateClassExists()) {
        $oscTemplate->addBlock($script, 'footer_scripts');
      } else {
        $content .= $script;
      }

      $confirmation = array('title' => $content);

      return $confirmation;
    }

    function process_button() {
      return false;
    }

    function before_process() {
      global $stripe_source_id;

      $this->after_process();
    }

    function after_process() {
      global $cart, $order, $order_totals, $currencies, $OSCOM_Hooks, $oscTemplate, $insert_id, $products_ordered, $cart_Stripe_Klarna_ID, $stripe_source_id;

      if (tep_session_is_registered('cart_Stripe_Klarna_ID')) {
        $order_id = substr($cart_Stripe_Klarna_ID, strpos($cart_Stripe_Klarna_ID, '-') + 1);
        
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
      global $order_id, $cart, $order, $order_totals, $currencies, $OSCOM_Hooks, $oscTemplate, $insert_id, $products_ordered, $cart_Stripe_Klarna_ID, $stripe_source_id, $messageStack, $klarna_error;
      
      $return = '';

      if (tep_session_is_registered('cart_Stripe_Klarna_ID')) {
        $chk_order_id = substr($cart_Stripe_Klarna_ID, strpos($cart_Stripe_Klarna_ID, '-') + 1);
        
        if ($chk_order_id == $order_id) {
          
          $chk_q = tep_db_query('select orders_status from orders where orders_id = ' . (int)$order_id);
          if (tep_db_num_rows($chk_q)) {
            $chk_o = tep_db_fetch_array($chk_q);
            if ($chk_o['orders_status'] == MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID) {

              $error = $this->get_last_event_error($order_id, $customer_id);
              tep_redirect(tep_href_link('checkout_payment.php', 'error=' . $error, 'SSL'));
            }
            
            if ($chk_o['orders_status'] == MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID) {
              // application has not yet been accepted - order may not be complete
              $messageStack->add('header', MODULE_PAYMENT_STRIPE_KLARNA_TRAN_INCOMPLETE, 'warning');
            }

            tep_db_query("delete from customers_basket where customers_id = '" . (int) $customer_id . "'");
            tep_db_query("delete from customers_basket_attributes where customers_id = '" . (int) $customer_id . "'");

            if (tep_session_is_registered('klarna_error')) {
                tep_session_unregister('klarna_error');
            }
            if (tep_session_is_registered('stripe_source_id')) {
                tep_session_unregister('stripe_source_id');
            }

            $cart->reset(true);

    // unregister session variables used during checkout
            tep_session_unregister('sendto');
            tep_session_unregister('billto');
            tep_session_unregister('shipping');
            tep_session_unregister('payment');
            tep_session_unregister('comments');

            tep_session_unregister('cart_Stripe_Klarna_ID');
            
          }
        }
      }
    }
  
    function complete_order_email($order_id, $customer_id) {
      // this is run from webhook (so not customer session)
      global $currencies;
      
      if ((int)$order_id > 0 && tep_db_num_rows(tep_db_query('select 1 from orders where orders_id = ' . (int)$order_id . ' and customers_id = ' . (int)$customer_id))) {
        
        $order = new order((int)$order_id);
        
        $insert_id = $order_id;

        if (DOWNLOAD_ENABLED == 'true') {
          for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            $downloads_query = tep_db_query("select opd.orders_products_filename from orders o, orders_products op, orders_products_download opd where o.orders_id = '" . (int)$order_id . "' and o.customers_id = '" . (int)$customer_id . "' and o.orders_id = op.orders_id and op.orders_products_id = opd.orders_products_id and opd.orders_products_filename != ''");

            if (tep_db_num_rows($downloads_query)) {
                if ($order->content_type == 'physical') {
                    $order->content_type = 'mixed';

                    break;
                } else {
                    $order->content_type = 'virtual';
                }
            } else {
                if ($order->content_type == 'virtual') {
                    $order->content_type = 'mixed';

                    break;
                } else {
                    $order->content_type = 'physical';
                }
            }
          }
        } else {
            $order->content_type = 'physical';
        }

// initialized for the email confirmation
        $products_ordered = '';

        for ($i = 0, $n = sizeof($order->products); $i < $n; $i++) {
            if (STOCK_LIMITED == 'true') {
                $stock_query = tep_db_query("select products_quantity from products where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                $stock_values = tep_db_fetch_array($stock_query);

                $stock_left = $stock_values['products_quantity'] - $order->products[$i]['qty'];

                if (DOWNLOAD_ENABLED == 'true') {
                    $downloads_query = tep_db_query("select opd.orders_products_filename from orders o, orders_products op, orders_products_download opd where o.orders_id = '" . (int) $order_id . "' and o.customers_id = '" . (int) $customer_id . "' and o.orders_id = op.orders_id and op.orders_products_id = opd.orders_products_id and opd.orders_products_filename != ''");
                    $downloads_values = tep_db_fetch_array($downloads_query);

                    if (tep_db_num_rows($downloads_query)) {
                        $stock_left = $stock_values['products_quantity'];
                    }
                }

                if ($stock_values['products_quantity'] != $stock_left) {
                    tep_db_query("update products set products_quantity = '" . (int) $stock_left . "' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

                    if (($stock_left < 1) && (STOCK_ALLOW_CHECKOUT == 'false')) {
                        tep_db_query("update products set products_status = '0' where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");
                    }
                }
            }

// Update products_ordered (for bestsellers list)
            tep_db_query("update products set products_ordered = products_ordered + " . sprintf('%d', $order->products[$i]['qty']) . " where products_id = '" . tep_get_prid($order->products[$i]['id']) . "'");

            $products_ordered_attributes = null;
            if (isset($order->products[$i]['attributes'])) {
                for ($j = 0, $n2 = sizeof($order->products[$i]['attributes']); $j < $n2; $j++) {
                    $products_ordered_attributes .= "\n\t" . $order->products[$i]['attributes'][$j]['option'] . ' ' . $order->products[$i]['attributes'][$j]['value'];
                }
            }

//------insert customer choosen option eof ----
            $products_ordered .= $order->products[$i]['qty'] . ' x ' . $order->products[$i]['name'] . ' (' . $order->products[$i]['model'] . ') = ' . $currencies->display_price($order->products[$i]['final_price'], $order->products[$i]['tax'], $order->products[$i]['qty']) . $products_ordered_attributes . "\n";
        }

// lets start with the email confirmation
        $email_order = STORE_NAME . "\n" .
                EMAIL_SEPARATOR . "\n" .
                EMAIL_TEXT_ORDER_NUMBER . ' ' . $order_id . "\n" .
                EMAIL_TEXT_INVOICE_URL . ' ' . tep_href_link('account_history_info.php', 'order_id=' . $order_id, 'SSL', false) . "\n" .
                EMAIL_TEXT_DATE_ORDERED . ' ' . strftime(DATE_FORMAT_LONG) . "\n\n";
        if ($order->info['comments']) {
            $email_order .= tep_db_output($order->info['comments']) . "\n\n";
        }
        $email_order .= EMAIL_TEXT_PRODUCTS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                $products_ordered .
                EMAIL_SEPARATOR . "\n";

        for ($i = 0, $n = sizeof($order_totals); $i < $n; $i++) {
            $email_order .= strip_tags($order_totals[$i]['title']) . ' ' . strip_tags($order_totals[$i]['text']) . "\n";
        }

        if ($order->content_type != 'virtual') {
            $email_order .= "\n" . EMAIL_TEXT_DELIVERY_ADDRESS . "\n" .
                    EMAIL_SEPARATOR . "\n" .
                    tep_address_format($order->delivery['format_id'], $order->delivery, false, '', "\n") . "\n";
        }

        $email_order .= "\n" . EMAIL_TEXT_BILLING_ADDRESS . "\n" .
                EMAIL_SEPARATOR . "\n" .
                tep_address_format($order->billing['format_id'], $order->billing, false, '', "\n") . "\n\n";

        $email_order .= EMAIL_TEXT_PAYMENT_METHOD . "\n" .
                EMAIL_SEPARATOR . "\n";
        $email_order .= $this->title . "\n\n";
        if ($this->email_footer) {
            $email_order .= $this->email_footer . "\n\n";
        }

        tep_mail($order->customer['name'], $order->customer['email_address'], EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);

// send emails to other people
        if (SEND_EXTRA_ORDER_EMAILS_TO != '') {
            tep_mail('', SEND_EXTRA_ORDER_EMAILS_TO, EMAIL_TEXT_SUBJECT, $email_order, STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
        }
        
        $o_status = DEFAULT_ORDERS_STATUS_ID;
        
        if (MODULE_PAYMENT_STRIPE_KLARNA_ORDER_STATUS_ID > 0) {

          $o_status = MODULE_PAYMENT_STRIPE_KLARNA_ORDER_STATUS_ID;
          
        }

        $sql_data_array = ['orders_status' => $o_status];
        tep_db_perform('orders', $sql_data_array, 'update', 'orders_id = ' . (int)$order_id);
        $sql_data_array = [
          'orders_id' => $order_id,
          'orders_status_id' => $o_status,
          'date_added' => 'now()',
        ];
        tep_db_perform('orders_status_history', $sql_data_array);

      } else {
        
        error_log(sprintf(MODULE_PAYMENT_STRIPE_KLARNA_MISMATCH_ORDER_CUSTOMER, $order_id, $customer_id));
       
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

    function check() {
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
    }

    function remove() {
      tep_db_query("delete from configuration where configuration_key in ('" . implode("', '", $this->keys()) . "')");
    }

    function keys() {
      $keys = array_keys($this->getParams());

      if ($this->check()) {
        foreach ($keys as $key) {
          if (!defined($key)) {
            $this->install($key);
          }
        }
      }

      return $keys;
    }

    function event_log($customer_id, $action, $request, $response) {
      if (MODULE_PAYMENT_STRIPE_KLARNA_LOG == "True") {
        tep_db_query("insert into stripe_event_log (customer_id, action, request, response, date_added) values ('" . $customer_id . "', '" . $action . "', '" . tep_db_input($request) . "', '" . tep_db_input($response) . "', now())");
      }
    }
  
    function checkOrderStatus($status_name, $public_flag = 0) {
      $check_query = tep_db_query("select orders_status_id from orders_status where orders_status_name = '{$status_name}' limit 1");

      if (tep_db_num_rows($check_query) < 1) {
        $status_query = tep_db_query("select max(orders_status_id) as status_id from orders_status");
        $status = tep_db_fetch_array($status_query);

        $status_id = $status['status_id'] + 1;

        $languages = tep_get_languages();

        foreach ($languages as $lang) {
          tep_db_query("insert into orders_status (orders_status_id, language_id, orders_status_name) values ('" . $status_id . "', '" . $lang['id'] . "', '{$status_name}')");
        }

        $flags_query = tep_db_query("describe orders_status public_flag");
        if (tep_db_num_rows($flags_query) == 1) {
          tep_db_query("update orders_status set public_flag = {$public_flag} and downloads_flag = 0 where orders_status_id = '{$status_id}'");
        }
      } else {
        $check = tep_db_fetch_array($check_query);

        $status_id = $check['orders_status_id'];
      }
      return $status_id;
    }

    function getParams() {
      if (tep_db_num_rows(tep_db_query("show tables like 'stripe_event_log'")) != 1) {
        $sql = <<<EOD
CREATE TABLE stripe_event_log (
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
            tep_db_query('alter stripe_event_log modify ' . $col['Field'] . ' text');
          }
        }
      }

      if (!defined('MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID')) {

        $prepare_status_id = $this->checkOrderStatus(MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_TEXT);

      } else {

        $prepare_status_id = MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID;
      }

      if (!defined('MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_ID')) {

        $status_id = $this->checkOrderStatus(MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_TEXT);

      } else {

        $status_id = MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_ID;
      }

      if (!defined('MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID')) {

        $app_status_id = $this->checkOrderStatus(MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_TEXT, 1);

      } else {

        $app_status_id = MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID;
      }

      if (!defined('MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID')) {

        $fail_status_id = $this->checkOrderStatus(MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_TEXT, 1);

      } else {

        $fail_status_id = MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_ID;
      }

      $params = [
        'MODULE_PAYMENT_STRIPE_KLARNA_STATUS' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_STATUS_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_STATUS_DESC,
          'value' => 'True',
          'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_SERVER' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SERVER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SERVER_DESC,
          'value' => 'Live',
          'set_func' => 'tep_cfg_select_option(array(\'Live\', \'Test\'), '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LIVE_PUBLISHABLE_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_PUB_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_PUB_DESC,
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LIVE_SECRET_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_SECRET_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_SECRET_DESC,
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LIVE_WEBHOOK_SECRET' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_WEBHOOK_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_WEBHOOK_DESC,
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TEST_PUBLISHABLE_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_PUB_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_PUB_DESC,
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TEST_SECRET_KEY' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_SECRET_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_SECRET_DESC,
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TEST_WEBHOOK_SECRET' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_WEBHOOK_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_WEBHOOK_DESC,
          'value' => ''
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_LOG' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LOG_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LOG_DESC,
          'value' => 'True',
          'set_func' => 'tep_cfg_select_option(array(\'True\', \'False\'), '
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_NEW_ORDER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_NEW_ORDER_DESC,
          'value' => $prepare_status_id,
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_APP_ORDER_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_APP_ORDER_DESC,
          'value' => $app_status_id,
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
          'value' => $fail_status_id,
          'use_func' => 'tep_get_order_status_name',
          'set_func' => 'tep_cfg_pull_down_order_statuses('
        ],
        'MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_ID' => [
          'title' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TRANSACTION_TITLE,
          'desc' => MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TRANSACTION_DESC,
          'value' => $status_id,
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

    function templateClassExists() {
        return class_exists('oscTemplate') && isset($GLOBALS['oscTemplate']) && is_object($GLOBALS['oscTemplate']) && (get_class($GLOBALS['oscTemplate']) == 'oscTemplate');
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
                tep_mail('', MODULE_PAYMENT_STRIPE_KLARNA_DEBUG_EMAIL, 'Stripe Debug E-Mail', trim($email_body), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
            }
        }
    }

}
