<?php 
/***************************************************************
*
* hook to go with payment module
* Klarna processing is async so push back some processing
* as late as possible and run in checkout success
*
* version 0.1 September 2020
* author: John Ferguson @BrockleyJohn oscommerce@sewebsites.net
* copyright (c) 2020 SEwebsites
*
* released under MIT licence without warranty express or implied
*
****************************************************************/

class hook_shop_siteWide_klarna_hook
{

  function listen_injectRedirects() 
  {
    global $language, $currencies, $PHP_SELF;
    
    switch (basename($PHP_SELF)) {
        
      case 'checkout_success.php' :
      
        if (isset($_SESSION['cart_Stripe_Klarna_ID']) && isset($_SESSION['payment']) && $_SESSION['payment'] == 'stripe_klarna') {

          require_once(DIR_FS_CATALOG . "includes/languages/{$language}/modules/payment/stripe_klarna.php");
          if (! class_exists('stripe_klarna')) {
            require_once(DIR_FS_CATALOG . 'includes/modules/payment/stripe_klarna.php');
          }

          $stripe_klarna = new stripe_klarna();
          $stripe_klarna->post_process();
          
        }

        break;
        
    }
  }
}