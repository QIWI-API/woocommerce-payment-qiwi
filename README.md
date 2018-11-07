# QIWI payment gateway for Woocommerce

This [Wordpress](https://wordpress.org/) plugin allows [Woocommerce](https://wordpress.org/plugins/woocommerce/) based store accept payments over [QIWI universal payments protocol](https://developer.qiwi.com/en/bill-payments/).

## Installation

This plugin depends on Woocommerce 3 and minimum requires [PHP](https://php.net/) 7 witch [cURL extension](https://secure.php.net/manual/en/book.curl.php).

### Manual installation

Please, [check release archive](https://github.com/QIWI-API/woocommerce-payment-qiwi/releases), or build package which [Composer from source](#from-source).
The WordPress codex contains [instructions how to setup plugins on you site](https://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

### Composer installation

This package provides automatic installation over [Composer](https://getcomposer.org/): 

```bash
composer require qiwi/woocommerce-payment-qiwi
```

This method use [Installers extension](http://composer.github.io/installers/) to menage source of your site.

#### From source

Another way is setup plugin from source.
Get package witch Composer into plugins directory of yours site source.

```bash
git clone git@github.com:QIWI-API/woocommerce-payment-qiwi.git
cd woocommerce-payment-qiwi
composer install --no-dev
``` 
