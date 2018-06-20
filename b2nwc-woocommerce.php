<?php
/*

Plugin Name: B2Ncoin for WooCommerce
Plugin URI: https://github.com/b2n-project/b2n-woocommerce/
Description: B2Ncoin for WooCommerce plugin allows you to accept payments in B2Ncoins for physical and digital products at your WooCommerce-powered online store.
Version: 0.01
Author: KittyCatTech
Author URI: https://github.com/b2n-project/b2n-woocommerce/
License: BipCot NoGov Software License bipcot.org

*/


// Include everything
include (dirname(__FILE__) . '/b2bwc-include-all.php');

//---------------------------------------------------------------------------
// Add hooks and filters

// create custom plugin settings menu
add_action( 'admin_menu',                   'B2NWC_create_menu' );

register_activation_hook(__FILE__,          'B2NWC_activate');
register_deactivation_hook(__FILE__,        'B2NWC_deactivate');
register_uninstall_hook(__FILE__,           'B2NWC_uninstall');

add_filter ('cron_schedules',               'B2NWC__add_custom_scheduled_intervals');
add_action ('B2NWC_cron_action',             'B2NWC_cron_job_worker');     // Multiple functions can be attached to 'B2NWC_cron_action' action

B2NWC_set_lang_file();
//---------------------------------------------------------------------------

//===========================================================================
// activating the default values
function B2NWC_activate()
{
    global  $g_B2NWC__config_defaults;

    $b2bwc_default_options = $g_B2NWC__config_defaults;

    // This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
    $b2bwc_settings = B2NWC__get_settings ();

    foreach ($b2bwc_settings as $key=>$value)
    	$b2bwc_default_options[$key] = $value;

    update_option (B2NWC_SETTINGS_NAME, $b2bwc_default_options);

    // Re-get new settings.
    $b2bwc_settings = B2NWC__get_settings ();

    // Create necessary database tables if not already exists...
    B2NWC__create_database_tables ($b2bwc_settings);
    B2NWC__SubIns ();

    //----------------------------------
    // Setup cron jobs

    if ($b2bwc_settings['enable_soft_cron_job'] && !wp_next_scheduled('B2NWC_cron_action'))
    {
    	$cron_job_schedule_name = $b2bwc_settings['soft_cron_job_schedule_name'];
    	wp_schedule_event(time(), $cron_job_schedule_name, 'B2NWC_cron_action');
    }
    //----------------------------------

}
//---------------------------------------------------------------------------
// Cron Subfunctions
function B2NWC__add_custom_scheduled_intervals ($schedules)
{
	$schedules['seconds_30']     = array('interval'=>30,     'display'=>__('Once every 30 seconds'));
	$schedules['minutes_1']      = array('interval'=>1*60,   'display'=>__('Once every 1 minute'));
	$schedules['minutes_2.5']    = array('interval'=>2.5*60, 'display'=>__('Once every 2.5 minutes'));
	$schedules['minutes_5']      = array('interval'=>5*60,   'display'=>__('Once every 5 minutes'));

	return $schedules;
}
//---------------------------------------------------------------------------
//===========================================================================

//===========================================================================
// deactivating
function B2NWC_deactivate ()
{
    // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...

    //----------------------------------
    // Clear cron jobs
    wp_clear_scheduled_hook ('B2NWC_cron_action');
    //----------------------------------
}
//===========================================================================

//===========================================================================
// uninstalling
function B2NWC_uninstall ()
{
    $b2bwc_settings = B2NWC__get_settings();

    if ($b2bwc_settings['delete_db_tables_on_uninstall'])
    {
        // delete all settings.
        delete_option(B2NWC_SETTINGS_NAME);

        // delete all DB tables and data.
        B2NWC__delete_database_tables ();
    }
}
//===========================================================================

//===========================================================================
function B2NWC_create_menu()
{

    // create new top-level menu
    // http://www.fileformat.info/info/unicode/char/e3f/index.htm
    add_menu_page (
        __('Woo B2Ncoin', B2NWC_I18N_DOMAIN),                    // Page title
        __('B2Ncoin', B2NWC_I18N_DOMAIN),                        // Menu Title - lower corner of admin menu
        'administrator',                                        // Capability
        'b2bwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'B2NWC__render_general_settings_page',                   // Function
        plugins_url('/images/b2ncoin_16x.png', __FILE__)      // Icon URL
        );

    add_submenu_page (
        'b2bwc-settings',                                        // Parent
        __("WooCommerce B2Ncoin Gateway", B2NWC_I18N_DOMAIN),                   // Page title
        __("General Settings", B2NWC_I18N_DOMAIN),               // Menu Title
        'administrator',                                        // Capability
        'b2bwc-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
        'B2NWC__render_general_settings_page'                    // Function
        );

}
//===========================================================================

//===========================================================================
// load language files
function B2NWC_set_lang_file()
{
    # set the language file
    $currentLocale = get_locale();
    if(!empty($currentLocale))
    {
        $moFile = dirname(__FILE__) . "/lang/" . $currentLocale . ".mo";
        if (@file_exists($moFile) && is_readable($moFile))
        {
            load_textdomain(B2NWC_I18N_DOMAIN, $moFile);
        }

    }
}
//===========================================================================

