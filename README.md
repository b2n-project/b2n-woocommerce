# B2Ncoin for WooCommerce

B2Ncoin for WooCommerce is a Wordpress plugin that allows merchants to accept B2N at WooCommerce-powered online stores.

Contributors: KittyCatTech, gesman

Tags: b2ncoin, b2ncoin wordpress plugin, b2ncoin plugin, b2ncoin payments, accept b2ncoin, b2ncoins

Requires at least: 3.0.1

Tested up to: 4.4.1

Stable tag: trunk

License: BipCot NoGov Software License bipcot.org

License URI: https://github.com/b2n-project/b2n-woocommerce/blob/master/LICENSE

## Description

Your online store must use WooCommerce platform (free wordpress plugin).
Once you have installed and activated WooCommerce, you may install and activate B2Ncoin for WooCommerce.

### Benefits 

* Fully automatic operation.
* Can be used with view only wallet so only the view private key is on the server and none of the spend private keys are required to be kept anywhere on your online store server.
* Accept payments in B2Ncoins directly into your B2Ncoin wallet.
* B2Ncoin wallet payment option completely removes dependency on any third party service and middlemen.
* Accept payment in B2Ncoins for physical and digital downloadable products.
* Add B2Ncoin option to your existing online store with alternative main currency.
* Flexible exchange rate calculations fully managed via administrative settings.
* Zero fees and no commissions for B2Ncoin processing from any third party.
* Set main currency of your store in USD, B2N or BTC.
* Automatic conversion to B2Ncoin via realtime exchange rate feed and calculations.
* Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.


## Installation 


1.  Install WooCommerce plugin and configure your store (if you haven't done so already - http://wordpress.org/plugins/woocommerce/).
2.  Install "B2Ncoin for WooCommerce" wordpress plugin just like any other Wordpress plugin.
3.  Activate.
4.  Download and install on your computer B2Ncoin wallet program from: https://bitcoin2.network/
5.  Copy and setup your wallet on the server. Change permission to executable. Run b2ncoind as a service.
6.  Generate Container (optionally reset containter to view only container and add view only address). Run walletd as a service.
7.  Get your wallet address from walletd.
8.  Within your site's Wordpress admin, navigate to:
	    WooCommerce -> Settings -> Checkout -> B2Ncoin
	    and paste your wallet address into "Wallet Address" field.
9.  Select "B2Ncoin service provider" = "Local Wallet" and fill-in other settings at B2Ncoin management panel.
10. Press [Save changes]
11. If you do not see any errors - your store is ready for operation and to access payments in B2Ncoins!


## Remove plugin

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


## Changelog

none
