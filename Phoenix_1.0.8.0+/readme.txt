Stripe SCA Payment module for osCommerce 2.3.x
----------------------------------------------

Stripe SCA is a refactored version of the standard Stripe payments to update the Stripe API to v3 and support the SCA process flow. Other than using different Stripe API calls to set up and process payments, the main change is to defer order fulfillment to a web hook called by Stripe after the payment has been authorised.

This module is installed as a separate payment method from the standard Stripe module and can be run in parallel.

Note that it shares the customer_stripe_tokens table created by the standard Stripe module, and cards saved using the standard Stripe module can be reused by the Stripe SCA module, however, cards stored by the Stripe SCA module cannot be reused by the standard Stripe module. They will be rejected by Stripe with a message 'You cannot create a charge with a PaymentMethod. Use the Payment Intents API instead.'

Install
-------

Copy the contents of this zip to the root osCommerce folder of your store.

Login to the osCommerce Admin page of your store and go to Modules > Payment.
- Click on the Install button at the upper right of the page
- Choose 'Stripe SCA' from the module list and select Install Module.

If you are allowing cards to be saved, add the cards management page:
- go to Modules > Content
- click on the Install Module button at the upper right of the page
- select 'Stripe SCA Cards Management Page' and select Install Module.
That will add the 'Manage saved payment cards' link to the customers' My Account page.

Configuration
-------------

The basic configuration of the Stripe SCA module is the same as the standard Stripe module, requiring Publishable and Secret API keys, however as the order fulfillment has been moved to a webhook, you need to add the address of the Stripe SCA webhook at your store to your Stripe account dashboard, and add the webhook signing secret it generates to the Stripe SCA payment module configuration.

Login to your account at the Stripe web site, and select Developers > Webhooks
- select '+ Add endpoint' at the upper right of the page
- set the URL to: https://yourstore.url/ext/modules/payment/stripe_sca/webhook.php
- select version as 'Latest API version'
- select event 'payment_intent.succeeded' and 'payment_intent.payment_failed'
- click 'add endpoint' to save the webhook endpoint.

Then select the new endpoint URL from the list of end points, and then 'click to reveal' to see the Signing Secret. Copy and paste the text of the signing secret to the Webhook Signing Secret in the Stripe SCA module configuration form.

Note that the 'view test data' switch at the Stripe dashboard toggles between live and test modes, and there are seperate API keys and webhooks in each mode. You have to create a webhook endpoint in the test mode to be used in testing the module. If your test environment is on a local network machine that is not directly accessible from the internet, you can create a free account at ngrok.com, to create a tunnel to the webhook URL on your local test machine. See https://stripe.com/docs/webhooks/setup for more details.

The Stripe SCA module adds a log table, stripe_event_log, to the database, and if you select 'Log events?' in the Stripe SCA configuration, it will record each Stripe API call with the parameters that are passed to Stripe and the response received.

As the process flow has changed to use a web hook, the order has to be created in a pending state, and then updated to completed status after the payment has been authorised. Consequently, you need to set the new order status to 'Preparing [Stripe SCA]', and the order status is set to the status the order is to be set to, after the payment is authorised.

Technical Notes
---------------

The current stripe-php library, as at module publish date, has been included in in the module install. Calls to the Stripe library functions have replaced directly sending transactions to the Stripe gateway. You should be able to replace the includes/modules/payment/stripe_sca folder with the complete contents of the stripe-php library when Stripe releases updates to their library. If you do so, set the new API version in includes/modules/payment/stripe_sca.php.

The Stripe v3 process flow now requires a PaymentIntent to be created before the payment page is displayed, and a 'data secret' it generates to be included in the HTML form. If a saved card is used, the Stripe customer id and payment method id has to be added to the PaymentIntent. During coding, it was found that a payment method could not be removed from a PaymentIntent, so rather than update the PaymentIntent with a server call as the saved card/new card is selected, the adding of the customer and payment method is deferred until immediately before submitting the payment to Stripe in a Javascript call when the form is submitted. The payment_intent.php server hook is also used to save the value of the 'save card' check box in the PaymentIntent so it is accessible to the webhook called after the payment is authorised.

Stripe v3 provides UI elements to collect card details. A 'card-element' element is required for the new card to show card number, expiry and CVC fields, but is also required for saved cards in order to provide a place holder that Stripe can use to display authorisation prompts if required. Consequently, two occurrences of the 'card-element' element were created with the name of each toggled depending on whether a saved card or new card is selected. Otherwise, trying to reuse the same element for both purposes block the authorisation of saved card when the new card details was hidden.

All order fulfilment and card saving code has been moved to ext/modules/payment/stripe_sca/webhook.php.

Note the DIR_FS_CATALOG constant should be set to a path string, rather than the value  dirname($_SERVER['SCRIPT_FILENAME']) . '/' because the includes/modules/payment/stripe_sca.php has a require that references the stripe-php library, and is executed at different locations in the directory structure and fails when the DIR_FS_CATALOG value varies with location.

If you do not see the card number, expiry and CVC in the order confirmation payment form, please check the browser console for any javascript errors. jQuery must be loaded before the payment module script in the page source.

If you enter card details and the page hangs with the payment button disabled, please check the browser console for any javascript errors. If that's ok, check the latest rows in the stripe_event_log table. If there is not an entry for the action 'ajax retrieve', that suggests that the server hook https://yourstore.url/ext/modules/payment/stripe_sca/payment_intent.php is not accessible. Check the URL in your browser for any errors. It should show the response ' {"status":"fail","reason":"No intent id received"} '.

If the payment is processed, and the checkout success page is displayed, but the order is not complete, first check the Stripe dashboard to see if the payment was processed. If ok, check the webhook events in the stripe Developer page. It will show the response received for each webhook attempt, and may show PHP errors in the response. You may need to copy and paste to a notepad to view the messages more easily. Also check the latest rows in the stripe_event_log table. There should be a series of rows for the actions: 'webhook', 'webhook process payment', 'webhook processOrder', 'webhook updateOrderStatus', plus messages for 'webhook createCustomer' and 'webhook saveCard' if token saving is enabled and the 'save card' check box was ticked in the payment form. Check that the server hook https://yourstore.url/ext/modules/payment/stripe_sca/webhook.php is accessible. If you enter the URL in your browser, you should get a blank page displayed with no errors.

The install includes the following files:
ext/modules/content/account/stripe_sca/cards.php
ext/modules/payment/stripe_sca/payment_intent.php
ext/modules/payment/stripe_sca/webhook.php
includes/languages/english/modules/content/account/cm_account_stripe_sca_cards.php
includes/languages/english/modules/payment/stripe_sca.php
includes/modules/content/account/cm_account_stripe_sca_cards.php
includes/modules/payment/stripe_sca.php
includes/modules/payment/stripe_sca/* (stripe-php library from https://github.com/stripe/stripe-php)


Change Log
----------
1.0
- Initial release

1.0.1 
- update all SQL to use actual table name instead of global variable
- add javascript to 'footer_scripts' template block, so loaded after jQuery in Phoenix and remove $ undefined error javascript error
- add Bootstrap classes to form controls to improve appearance in Phoenix, and fix card element not being visible in Phoenix
- hide 'add card' prompt in payment form when token save configuration is false
- stop logging to event table when configuration logging setting is false
- set module public title to 'Credit Card (Stripe SCA)' so enabling multiple credit card modules is less confusing
- fix save card icon display in Phoenix 'my account' page
- remove configuration option to validate CVC, as card fields are managed by Stripe

1.0.2
- update webhook to change email link for account history to string literal
- update configuration to accept both live and test keys and use correct key when toggling between live and test selection
- implement authorise only option. Authorised transactions must be captured using the Stripe dashboard, which will then fire the call to the webhook and complete the order process

1.0.3
- fix missing payment method in customer order confirmation email
- separate web hook signing secrets for test and live 
- attempt to resolve 'cannot access empty property' error in stripe_sca.php, line 393

1.0.4
- fix web hook signing secret
- add hooks for Discount Codes BS module

1.0.5
- make card number field style consistant with card name field

1.0.6
- add call to $order_total_modules->process(); when creating a new temporary order
- Stripe apiKey not set correctly in webhook.php and payment_intent.php
- improve checking for Discount Codes BS module

1.0.7
- implement raiwa refactoring of order processing, moving most out of webhook and into after_process function of payment module and include hooks to a number of common order processing modules
  Note that setting of several customer columns in order table is commented out to enable module to run in standard system
- Remove a number of English prompts and messages from processing modules and define them in language file
- refactor javascript in payment form to remove redundant fields and some dependancies on a specific theme

1.0.8
- remove hooks to third party order processing modules
- update order status with error message if webhook receives error
- fix missing information in order status for successful payment, and assume customer received email

1.0.9
- add order comment to preparing order status and save into order status history
- update order status history with success order status
- remove customer notified flag from "Stripe [Transactions]" order status

1.0.10
- fix stripe error in payment intent hook not being passed back to browser
- fix stripe error with saved card not being shown to user
- allow stripe error detaching card from customer to still delete saved card from store database

1.0.11
- remove cart items from Stripe transaction metadata to fix error when too many items
- fix missing totals from customer email

1.0.12
- Stripe amount not handling shipping and tax correctly
- Stop creating multiple 'preparing' order records as cart changed

1.0.13
- Remove potential SQL injection
- Tidy account saved cards when multiple cards
- remove potential webhook secret exposure

1.0.14
- Added line to reset attributes variable between each product in order e-mail

1.0.16
- Fixed order total sort order not saved in database