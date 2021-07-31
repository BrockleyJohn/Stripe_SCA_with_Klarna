<?php
/*
  $Id$

  CE Phoenix, E-Commerce made Easy
  https://phoenixcart.org

  Copyright (c) 2021 Phoenix Cart

  Released under the GNU General Public License
*/

const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_TITLE = 'Update order status via webhook';
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_DESCRIPTION = 'Send a notification when the order status is updated by a webhook (as opposed to in admin)';

const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_SEPARATOR = '------------------------------------------------------';
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_SUBJECT = 'Order Status Update';
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_ORDER_NUMBER = 'Order Number:  %d';
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_INVOICE_URL = 'Detailed Invoice:  %s';
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_DATE_ORDERED = 'Date Ordered:  %s';
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_STATUS_UPDATE = <<<'EOT'
Your order has been updated to the following status.

New status: %s

Please reply to this email if you have any questions.


EOT;
const MODULE_NOTIFICATIONS_UPDATE_ORDER_WEBHOOK_TEXT_COMMENTS_UPDATE = 'The comments for your order are' . "\n\n%s\n\n";
