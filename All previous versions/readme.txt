Klarna via Stripe Payment module for osCommerce 2.3.x
----------------------------------------------

This module accepts Pay Later with Klarna payments via a Stripe account. It can be installed and run on its own or in parallel with Stripe SCA payments; it uses different payment objects (sources).
You need to use the version of Stripe PHP SDK that's delivered with this module, but it's backwards compatible and you can safely overwrite a previous version without breaking an existing Stripe SCA module.

It shares the events table created for that module.

Like Stripe SCA, this module also uses a webhook - it is a separate file and needs configuring in your Stripe account.

PLEASE NOTE: KLARNA PAYMENTS ARE ASYNCRONOUS

This means you don't always know straightaway whether a charge submitted to Klarna goes through successfully, before the customer has completed checkout. So, the results of most calls are handled in the webhook rather than during the customer's checkout processing.
- orders are created in a 'Preparing' status during checkout
- the initial Klarna application is conducted via the widget on checkout_confirmation
- if the application is successful and the client confirms, a charge request is sent in checkout_process and the order is changed to an 'Application' status
- when the result of this arrives, the order will either move on to a successful or failed status
- if this has happened by the time the customer reaches checkout_success they are informed and if a failure they are returned to checkout
- they are notified by email too
- the application and fail status have the public flag set so that the customer can see in their account orders list what is happening
- note that if you choose not to log events in the module settings, you and the customer won't have any information on the cause that a klarna charge failed

IF YOU WANT TO CREATE A NEW LANGUAGE TRANSLATION
Do it before you install the module. The names and descriptions of the admin settings and the new status names are all defined in the language file, so if done first you can match them to your admin language.

Install
-------

Copy the contents of this zip to the root osCommerce folder of your store.

Login to the osCommerce Admin page of your store and go to Modules > Payment.
- Click on the Install button at the upper right of the page
- Choose 'Klarna via Stripe' from the module list and select Install Module.

Check that your store has 'hooks' which it will if you have the paypal app. Older stores will use 'global' shop hooks while Phoenix stores use 'siteWide' hooks. Both are provided in the files for this module.

Configuration
-------------

The module needs publishable and secret keys copying from your Stripe account. You will also need to create a webhook (it's a script on your site that receives callbacks from Stripe). If you are running Stripe SCA you will already have a webhook, but even so for this module you need to create a second one.

Login to your account at the Stripe web site, and select Developers > Webhooks
- select '+ Add endpoint' at the upper right of the page
- set the URL to: https://yourstore.url/ext/modules/payment/stripe_sca/klarnahook.php
- select events: 
    charge.updated
    charge.succeeded
    charge.refunded
    charge.pending
    charge.failed
    charge.expired
    charge.captured
    source.failed
    source.chargeable
    source.canceled
- click 'add endpoint' to save the webhook endpoint.

Then select the new endpoint URL from the list of end points, and then 'click to reveal' to see the Signing Secret. Copy and paste the text of the signing secret to the Webhook Signing Secret in the Klarna module settings. You also need the Publishable Key and Secret Key from the API Keys section of the Stripe account (above Webhooks underneath Developer heading).

Note that the 'view test data' switch at the Stripe dashboard toggles between live and test modes, and there are seperate API keys and webhooks in each mode. You have to create a webhook endpoint in the test mode to be used in testing the module. If your test environment is on a local network machine that is not directly accessible from the internet, you can create a free account at ngrok.com, to create a tunnel to the webhook URL on your local test machine. See https://stripe.com/docs/webhooks/setup for more details.

The Klarna via Stripe and Stripe SCA modules add a log table stripe_event_log to the database, and if you select 'Log events?' in the module configuration, it will record each Stripe API call with the parameters that are passed to Stripe and the response received.
Installing this module in a store with an existing stripe_event_log table updates the definition so that more data is stored for each request and response.

