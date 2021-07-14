<?php
/**

  langauge definitions for Stripe-Klarna payment module
 
  version 1.1 March 2021

  author: John Ferguson @BrockleyJohn oscommerce@sewebsites.net
  copyright (c) 2020 SEwebsites
 
* released under SE Websites Commercial licence
* without warranty express or implied
* DISTRIBUTION RESTRICTED see se-websites-commercial-licence.txt
*****************************************************************/

// module
  define('MODULE_PAYMENT_STRIPE_KLARNA_TEXT_TITLE', 'Klarna via Stripe');
  define('MODULE_PAYMENT_STRIPE_KLARNA_TEXT_PUBLIC_TITLE', 'Buy now pay later with Klarna');
  define('MODULE_PAYMENT_STRIPE_KLARNA_TEXT_DESCRIPTION', 'Klarna payments as a Stripe Source');

// email texts
  define('MODULE_PAYMENT_STRIPE_KLARNA_FAIL_EMAIL_SUBJECT', 'Klarna setup failed - order not placed');
  define('MODULE_PAYMENT_STRIPE_KLARNA_FAIL_EMAIL_TEXT', "Dear %s,\n\nSorry but there was a problem setting up the Klarna payment arrangement for order %s. Please contact us to make alternative arrangements to pay.");

  define('MODULE_PAYMENT_STRIPE_KLARNA_EMAIL_INTRO', 'Thanks for your order.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_CONFIRMED','Klarna payment confirmed - order accepted');
  define('MODULE_PAYMENT_STRIPE_KLARNA_EMAIL_TEXT_INVOICE_URL', 'Order details:');
  define('MODULE_PAYMENT_STRIPE_KLARNA_EMAIL_CODA', 'If you have any questions about your order or payments please contact us.');

// module-specific order statuses
  define('MODULE_PAYMENT_STRIPE_KLARNA_PREPARE_ORDER_STATUS_TEXT', 'Preparing [Stripe Klarna]');
  define('MODULE_PAYMENT_STRIPE_KLARNA_TRANSACTION_ORDER_STATUS_TEXT', 'Stripe Klarna [Transactions]');
  define('MODULE_PAYMENT_STRIPE_KLARNA_APPLICATION_ORDER_STATUS_TEXT', 'In progress [Stripe Klarna]');
  define('MODULE_PAYMENT_STRIPE_KLARNA_APP_FAILED_ORDER_STATUS_TEXT', 'Setup failed [Stripe Klarna]');

// module configuration
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_ADMIN_CURL', 'This module requires cURL to be enabled in PHP and will not load until it has been enabled on this webserver.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_ADMIN_CONFIGURATION', 'This module will not load until the Publishable Key and Secret Key parameters have been configured. Please edit and configure the settings of this module.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_STATUS_TITLE', 'Enable Stripe Klarna Module');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_STATUS_DESC', 'Do you want to accept Klarna payments via Stripe?');
  define('MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API_TITLE', 'Which API');
  define('MODULE_PAYMENT_STRIPE_KLARNA_GLOBAL_API_DESC', 'Is your store in Europe or the US?');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SERVER_TITLE', 'Transaction Server');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SERVER_DESC', 'Perform transactions on the production server or on the testing server.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_PUB_TITLE', 'Live Publishable API Key');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_PUB_DESC', 'The Stripe account publishable API key to use for production transactions.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_SECRET_TITLE', 'Live Secret API Key');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_SECRET_DESC', 'The Stripe account secret API key to use with the live publishable key.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_WEBHOOK_TITLE', 'Live Signing Secret for Klarna Webhook');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LIVE_WEBHOOK_DESC', 'The Stripe account live webhook signing secret of the webhook you created to listen for Klarna events (different from Stripe SCA webhook).');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_PUB_TITLE', 'Test Publishable API Key');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_PUB_DESC', 'The Stripe account publishable API key to use for testing.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_SECRET_TITLE', 'Test Secret API Key');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_SECRET_DESC', 'The Stripe account secret API key to use with the test publishable key.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_WEBHOOK_TITLE', 'Test Signing Secret for Klarna Webhook');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TEST_WEBHOOK_DESC', 'The Stripe account test webhook signing secret of the webhook you created to listen for Klarna events (different from Stripe SCA webhook).');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LOG_TITLE', 'Log Events');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_LOG_DESC', 'Log calls to Stripe functions?');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_NEW_ORDER_TITLE', 'Set New Order Status');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_NEW_ORDER_DESC', 'Set the status of orders created with this payment module to this value');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_APP_ORDER_TITLE', 'Set Application Order Status');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_APP_ORDER_DESC', 'Set the status of orders whose Klarna application is being processed but has not yet succeeded to this value');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_FAIL_ORDER_TITLE', 'Set Failed Order Status');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_FAIL_ORDER_DESC', 'Set the status of orders which fail in async Klarna processing to this value');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROCESSED_TITLE', 'Set Order Processed Status');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROCESSED_DESC', 'Set the status of orders successfully processed with this payment module to this value');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TRANSACTION_TITLE', 'Transaction Order Status');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_TRANSACTION_DESC', 'Include transaction information in this order status level');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_ZONE_TITLE', 'Payment Zone');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_ZONE_DESC', 'If a zone is selected, only enable this payment method for that zone.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EVENT_NUM_TITLE', 'Event limit');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EVENT_NUM_DESC', 'The number of events to fetch when trying to get the last error for the customer - may need increasing on busier stores');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROXY_TITLE', 'Proxy Server');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_PROXY_DESC', 'Send API requests through this proxy server. (host:port, eg: 123.45.67.89:8080 or proxy.example.com:8080)');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EMAIL_TITLE', 'Debug E-Mail Address');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_EMAIL_DESC', 'All parameters of an invalid transaction will be sent to this email address.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SORT_TITLE', 'Sort order of display.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ADMIN_SORT_DESC', 'Sort order of display. Lowest, non-zero is displayed first.');
  
// klarna widget selection
  define('MODULE_PAYMENT_STRIPE_KLARNA_OPTIONS','Payment options with Klarna:');
  define('MODULE_PAYMENT_STRIPE_KLARNA_PAY_NOW','Pay now');
  define('MODULE_PAYMENT_STRIPE_KLARNA_PAY_LATER','Pay later');
  define('MODULE_PAYMENT_STRIPE_KLARNA_PAY_OVER_TIME','Pay in instalments');
  
// error messages and email
  define('MODULE_PAYMENT_STRIPE_KLARNA_WEBHOOK_PARAMETER', 'Unexpected parameter value received');
  define('MODULE_PAYMENT_STRIPE_KLARNA_SECRET_ERROR', 'Invalid webhook signing secret');
  define('MODULE_PAYMENT_STRIPE_KLARNA_WEBHOOK_SERVER', 'Server error - check logs');

  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_TITLE', 'There has been an error setting up Klarna payments');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_GENERAL', 'Please try again and if problems persist, please try another payment method.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_NOT_FOUND', 'No log of the cause was found. Please try again and if problems persist, please try another payment method.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_SOURCE', 'Failed setting up your choice of Klarna options. Please try again and if problems persist, please try another payment method.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_CHARGE', 'Failed setting up your choice of Klarna options. Please try again and if problems persist, please try another payment method. Error %s %s');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_UNKOWN', 'Unexpected result with Klarna payments. Please try again and if problems persist, please try another payment method. Event %s');
  define('MODULE_PAYMENT_STRIPE_KLARNA_ERROR_FAIL', 'Failed to process your Klarna Payment. Please try again and if problems persist, please try another payment method. Error %s %s');
  define('MODULE_PAYMENT_STRIPE_KLARNA_TRAN_CHARGE_FAIL', 'FAIL: Klarna charge failed %s %s');
  define('MODULE_PAYMENT_STRIPE_KLARNA_MISMATCH_ORDER_CUSTOMER', 'Klarna charge succeeded but order %s customer %s not matched');
  define('MODULE_PAYMENT_STRIPE_KLARNA_TRAN_INCOMPLETE','Order placed subject to confirmation from Klarna. Check your emails or the order status in your account.');

// connection test
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_LINK_TITLE', 'Test API Server Connection');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_TITLE', 'API Server Connection Test');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_GENERAL_TEXT', 'Testing connection to server...');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_BUTTON_CLOSE', 'Close');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_TIME', 'Connection Time:');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_SUCCESS', 'Success!');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_FAILED', 'Failed! Please review the Verify SSL Certificate settings and try again.');
  define('MODULE_PAYMENT_STRIPE_KLARNA_DIALOG_CONNECTION_ERROR', 'An error occurred. Please refresh the page, review your settings, and try again.');
