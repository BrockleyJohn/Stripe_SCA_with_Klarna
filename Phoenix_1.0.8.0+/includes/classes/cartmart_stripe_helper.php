<?php
/**
-
  stripe helper trait used across stripe addons
  
  author: John Ferguson @BrockleyJohn phoenix@sewebsites.net
  date: March 2021
  copyright (c) 2021 SE Websites
  
* released under SE Websites Commercial licence
* without warranty express or implied
* DISTRIBUTION RESTRICTED see se-websites-commercial-licence.txt
*****************************************************************/

trait cartmart_stripe_helper {
  
  static $LOCALES = [
    'ar',
    'bg',
    'cs',
    'da',
    'de',
    'el',
    'en',
    'en-GB',
    'es',
    'es-419',
    'et',
    'fi',
    'fr',
    'fr-CA',
    'he',
    'hu',
    'id',
    'it',
    'ja',
    'lt',
    'lv',
    'ms',
    'mt',
    'nb',
    'nl',
    'pl',
    'pt',
    'pt-BR',
    'ro',
    'ru',
    'sk',
    'sl',
    'sv',
    'th',
    'tr',
    'zh',
    'zh-HK',
    'zh-TW'
  ];
  
  public static function disp_key($val)
  // return the beginning of the key only
  {
    return strlen($val) > 36 ? substr($val, 0, 36) . '...' : $val;
  }
  
  protected function ensureLocale()
  {
    $lq = tep_db_query('SELECT * FROM languages WHERE languages_id = ' . (int)$_SESSION['languages_id']);
    if ($lr = tep_db_fetch_array($lq)) {
      if (in_array($lr['code'], self::$LOCALES)) {
        return $lr['code'];
      }
    }
    return 'auto';
  }

  function event_log($customer_id, $action, $request, $response) {
    if ($this->base_constant('LOG') == "True") {
      tep_db_query("insert into stripe_event_log (customer_id, action, request, response, date_added) values ('" . $customer_id . "', '" . $action . "', '" . tep_db_input($request) . "', '" . tep_db_input($response) . "', now())");
    }
  }

  protected function format_raw($number, $currency_code = '', $currency_value = '') {
    // returns currency value multiplied by 100, no decimals
    // e.g. 2.50EUR becomes 250
    global $currencies;

    if (empty($currency_code) || !$currencies->is_set($currency_code)) {
      $currency_code = $_SESSION['currency'];
    }

    if (empty($currency_value) || !is_numeric($currency_value)) {
      $currency_value = $currencies->currencies[$currency_code]['value'];
    }

    return number_format(tep_round($number * $currency_value, $currencies->currencies[$currency_code]['decimal_places']) * 100, 0, '', '');
  }

  protected function getTestConnectionResult() {
    $stripe_result = json_decode($this->sendTransactionToGateway('https://api.stripe.com/v1/balance'), true);

    $dialog_success = $this->base_constant('DIALOG_CONNECTION_SUCCESS');
    $dialog_failed = $this->base_constant('DIALOG_CONNECTION_FAILED');
    $dialog_error = $this->base_constant('DIALOG_CONNECTION_ERROR');
    $dialog_live = $this->base_constant('DIALOG_CONNECTION_MODE_LIVE');
    $dialog_test = $this->base_constant('DIALOG_CONNECTION_MODE_TEST');

    $return = ['msg' => $dialog_failed];

    if (is_array($stripe_result) && !empty($stripe_result)) {

      if (isset($stripe_result['error'])) {
        $return['msg'] = sprintf($dialog_error, $stripe_result['error']);
      } else {
        $return['msg'] = sprintf($dialog_success, $stripe_result['livemode'] == 1 ? $dialog_live : $dialog_test);
        $return['status'] = 'ok';
      }
    }

    return json_encode($return);
  }

  protected function getTestLinkInfo() {
    $dialog_title = $this->base_constant('DIALOG_CONNECTION_TITLE');
    $dialog_button_close = $this->base_constant('DIALOG_CONNECTION_BUTTON_CLOSE');
    $dialog_connection_time = $this->base_constant('DIALOG_CONNECTION_TIME');
    $conn_link_title = $this->base_constant('DIALOG_CONNECTION_LINK_TITLE');
    $conn_link_text = $this->base_constant('DIALOG_CONNECTION_GENERAL_TEXT');

    //$test_url = tep_href_link('modules.php', 'set=payment&module=' . $this->code . '&action=install&subaction=conntest'); // interim version issue encoding as &amp;
    $test_url = tep_href_link('modules.php') . '?set=payment&module=' . $this->code . '&action=install&subaction=conntest';

    $btn = '<br/>' . self::modal_button($conn_link_title, 'connTest', 'fas fa-external-link-alt');
    self::ajax_onload_script('testConn', $test_url);
    self::modal_ajax_onload('connTest', 'testConn', $dialog_title, $dialog_button_close);

    return $btn;
  }

  protected function sendTransactionToGateway($url, $parameters = null, $curl_opts = array()) {
    $server = parse_url($url);

    if (isset($server['port']) === false) {
      $server['port'] = ($server['scheme'] == 'https') ? 443 : 80;
    }

    if (isset($server['path']) === false) {
      $server['path'] = '/';
    }

    $header = [
      'Stripe-Version: ' . $this->api_version,
      'User-Agent: Phoenix ' . tep_get_version()
    ];

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

    if (tep_not_null($this->base_constant('PROXY'))) {
        curl_setopt($curl, CURLOPT_HTTPPROXYTUNNEL, true);
        curl_setopt($curl, CURLOPT_PROXY, $this->base_constant('PROXY'));
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


}