<?php
/*
  Easily add header and footer scripts in admin e.g. from the page
  
  author John Ferguson @BrockleyJohn oscommerce@sewebsites.net

  Copyright (c) 2021 SE Websites

  Released under MIT licence without warranty express or implied
*/

class hook_admin_siteWide_zEzScripts {
  public $scripts = null;

  public function listen_injectSiteStart() {

    return $this->getScripts('header_scripts');
  }

  public function listen_injectBodyEnd() {

    return $this->getScripts('footer_scripts');
  }
  
  private function getScripts($which) {
    
    if (isset($GLOBALS[$which]) && is_array($GLOBALS[$which])) {
      
      foreach ($GLOBALS[$which] as $script) {

        $this->scripts .= $script . PHP_EOL;
      }
    }

    return $this->scripts;
  }

}
