<?php
/*
  Version of the admin order update notification module so it works for webhooks

  author: John Ferguson @BrockleyJohn phoenix@sewebsites.net

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

  class n_update_order_webhook extends abstract_module {

    const CONFIG_KEY_BASE = 'MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_';

    const TRIGGERS = [ 'update_order_webhook' ];
    const REQUIRES = [ 'address', 'name', 'email_address' ];

    public function notify($data) {
      $data['notify_comments'] = sprintf(MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_COMMENTS_UPDATE, $data['notify_comments']) . "\n\n";

      // templates are shop side
      Guarantor::ensure_global('hooks', 'shop');

      ob_start();
      include Guarantor::ensure_global('oscTemplate')->map_to_template(__FILE__);
      echo $GLOBALS['OSCOM_Hooks']->call('siteWide', 'statusUpdateEmail', $data);

      return tep_mail($data['customers_name'], $data['customers_email_address'], MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_SUBJECT, ob_get_clean(), STORE_OWNER, STORE_OWNER_EMAIL_ADDRESS);
    }

    protected function get_parameters() {
      return [
        static::CONFIG_KEY_BASE . 'STATUS' => [
          'title' => 'Enable Order Update Notification module',
          'value' => 'True',
          'desc' => 'Do you want to add the module to your shop?',
          'set_func' => "tep_cfg_select_option(['True', 'False'], ",
        ],
      ];
    }

  }

