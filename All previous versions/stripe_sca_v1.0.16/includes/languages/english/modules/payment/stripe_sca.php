<?php
/*
  $Id: $

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2014 osCommerce

  Released under the GNU General Public License
*/
  define('MODULE_CONTENT_CHECKOUT_SUCCESS_TEXT_SUCCESS', 'Your order has been successfully processed! Your products will be available to download when your payment has been authorised.');

  define('MODULE_PAYMENT_STRIPE_SCA_TEXT_TITLE', 'Stripe SCA');
  define('MODULE_PAYMENT_STRIPE_SCA_TEXT_PUBLIC_TITLE', 'Credit Card (Stripe SCA)');
  define('MODULE_PAYMENT_STRIPE_SCA_TEXT_DESCRIPTION', '<img src="images/icon_info.gif" border="0" />&nbsp;<a href="http://library.oscommerce.com/Package&en&stripe&oscom23&stripe_js" target="_blank" rel="noopener" style="text-decoration: underline; font-weight: bold;">View Online Documentation</a><br /><br /><img src="images/icon_popup.gif" border="0">&nbsp;<a href="https://www.stripe.com" target="_blank" rel="noopener" style="text-decoration: underline; font-weight: bold;">Visit Stripe Website</a>');

  define('MODULE_PAYMENT_STRIPE_SCA_ERROR_ADMIN_CURL', 'This module requires cURL to be enabled in PHP and will not load until it has been enabled on this webserver.');
  define('MODULE_PAYMENT_STRIPE_SCA_ERROR_ADMIN_CONFIGURATION', 'This module will not load until the Publishable Key and Secret Key parameters have been configured. Please edit and configure the settings of this module.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_STATUS_TITLE', 'Enable Stripe SCA Module');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_STATUS_DESC', 'Do you want to accept Stripe v3 payments?');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_SERVER_TITLE', 'Transaction Server');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_SERVER_DESC', 'Perform transactions on the production server or on the testing server.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LIVE_PUB_TITLE', 'Live Publishable API Key');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LIVE_PUB_DESC', 'The Stripe account publishable API key to use for production transactions.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LIVE_SECRET_TITLE', 'Live Secret API Key');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LIVE_SECRET_DESC', 'The Stripe account secret API key to use with the live publishable key.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LIVE_WEBHOOK_TITLE', 'Live Webhook Signing Secret');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LIVE_WEBHOOK_DESC', 'The Stripe account live webhook signing secret of the webhook you created to listen for payment_intent.succeeded events.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TEST_PUB_TITLE', 'Test Publishable API Key');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TEST_PUB_DESC', 'The Stripe account publishable API key to use for testing.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TEST_SECRET_TITLE', 'Test Secret API Key');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TEST_SECRET_DESC', 'The Stripe account secret API key to use with the test publishable key.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TEST_WEBHOOK_TITLE', 'Test Webhook Signing Secret');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TEST_WEBHOOK_DESC', 'The Stripe account test webhook signing secret of the webhook you created to listen for payment_intent.succeeded events.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TOKENS_TITLE', 'Create Tokens');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TOKENS_DESC', 'Create and store tokens for card payments customers can use on their next purchase?');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LOG_TITLE', 'Log Events');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_LOG_DESC', 'Log calls to Sripe functions?');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_METHOD_TITLE', 'Transaction Method');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_METHOD_DESC', 'The processing method to use for each transaction.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_NEW_ORDER_TITLE', 'Set New Order Status');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_NEW_ORDER_DESC', 'Set the status of orders created with this payment module to this value');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_PROCESSED_TITLE', 'Set Order Processed Status');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_PROCESSED_DESC', 'Set the status of orders successfully processed with this payment module to this value');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TRANSACTION_TITLE', 'Transaction Order Status');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_TRANSACTION_DESC', 'Include transaction information in this order status level');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_ZONE_TITLE', 'Payment Zone');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_ZONE_DESC', 'If a zone is selected, only enable this payment method for that zone.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_SSL_TITLE', 'Verify SSL Certificate');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_SSL_DESC', 'Verify gateway server SSL certificate on connection?');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_PROXY_TITLE', 'Proxy Server');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_PROXY_DESC', 'Send API requests through this proxy server. (host:port, eg: 123.45.67.89:8080 or proxy.example.com:8080)');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_EMAIL_TITLE', 'Debug E-Mail Address');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_EMAIL_DESC', 'All parameters of an invalid transaction will be sent to this email address.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_SORT_TITLE', 'Sort order of display.');
  define('MODULE_PAYMENT_STRIPE_SCA_ADMIN_SOR_DESC', 'Sort order of display. Lowest, non-zero is displayed first.');
  
  define('MODULE_PAYMENT_STRIPE_SCA_CREDITCARD_NEW', 'Enter a new Card');
  define('MODULE_PAYMENT_STRIPE_SCA_CREDITCARD_OWNER', 'Card holder name');
  define('MODULE_PAYMENT_STRIPE_SCA_CREDITCARD_TYPE', 'Credit or Debit card');
  define('MODULE_PAYMENT_STRIPE_SCA_CREDITCARD_SAVE', 'Save Card for next purchase?');
  define('MODULE_PAYMENT_STRIPE_SCA_MISSING_INTENT', 'Missing intent id');
  define('MODULE_PAYMENT_STRIPE_SCA_MISSING_CUSTOMER_TOKEN', 'Missing customer token');
  define('MODULE_PAYMENT_STRIPE_SCA_MISSING_CARD_FOR_TOKEN', 'No card details found for token ');

  define('MODULE_PAYMENT_STRIPE_SCA_WEBHOOK_PARAMETER', 'Unexpected parameter value received');
  define('MODULE_PAYMENT_STRIPE_SCA_SECRET_ERROR', 'Invalid webhook signing secret');
  define('MODULE_PAYMENT_STRIPE_SCA_WEBHOOK_SERVER', 'Server error - check logs');
  
  define('MODULE_PAYMENT_STRIPE_SCA_ERROR_TITLE', 'There has been an error processing your credit card');
  define('MODULE_PAYMENT_STRIPE_SCA_ERROR_GENERAL', 'Please try again and if problems persist, please try another payment method.');
  define('MODULE_PAYMENT_STRIPE_SCA_ERROR_CARDSTORED', 'The stored card could not be found. Please try again and if problems persist, please try another payment method.');

  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_LINK_TITLE', 'Test API Server Connection');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_TITLE', 'API Server Connection Test');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_GENERAL_TEXT', 'Testing connection to server..');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_BUTTON_CLOSE', 'Close');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_TIME', 'Connection Time:');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_SUCCESS', 'Success!');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_FAILED', 'Failed! Please review the Verify SSL Certificate settings and try again.');
  define('MODULE_PAYMENT_STRIPE_SCA_DIALOG_CONNECTION_ERROR', 'An error occurred. Please refresh the page, review your settings, and try again.');
?>
