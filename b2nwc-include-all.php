<?php
/*
B2Ncoin for WooCommerce
https://github.com/b2n-project/b2n-woocommerce/
*/

//---------------------------------------------------------------------------
// Global definitions
if (!defined('B2NWC_PLUGIN_NAME'))
  {
  define('B2NWC_VERSION',           '0.01');

  //-----------------------------------------------
  define('B2NWC_EDITION',           'Standard');    

  //-----------------------------------------------
  define('B2NWC_SETTINGS_NAME',     'B2NWC-Settings');
  define('B2NWC_PLUGIN_NAME',       'B2Ncoin for WooCommerce');   


  // i18n plugin domain for language files
  define('B2NWC_I18N_DOMAIN',       'b2bwc');

  }
//---------------------------------------------------------------------------

//------------------------------------------
// Load wordpress for POSTback, WebHook and API pages that are called by external services directly.
if (defined('B2NWC_MUST_LOAD_WP') && !defined('WP_USE_THEMES') && !defined('ABSPATH'))
   {
   $g_blog_dir = preg_replace ('|(/+[^/]+){4}$|', '', str_replace ('\\', '/', __FILE__)); // For love of the art of regex-ing
   define('WP_USE_THEMES', false);
   require_once ($g_blog_dir . '/wp-blog-header.php');

   // Force-elimination of header 404 for non-wordpress pages.
   header ("HTTP/1.1 200 OK");
   header ("Status: 200 OK");

   require_once ($g_blog_dir . '/wp-admin/includes/admin.php');
   }
//------------------------------------------


// This loads necessary modules
require_once (dirname(__FILE__) . '/libs/forknoteWalletdAPI.php');

require_once (dirname(__FILE__) . '/b2bwc-cron.php');
require_once (dirname(__FILE__) . '/b2bwc-utils.php');
require_once (dirname(__FILE__) . '/b2bwc-admin.php');
require_once (dirname(__FILE__) . '/b2bwc-render-settings.php');
require_once (dirname(__FILE__) . '/b2bwc-b2ncoin-gateway.php');

?>