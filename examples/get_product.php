<?php

define('MCAPI_CONFIG_PATH', '.');
require 'bootstrap.php';
require 'printers.php';

use MyCloud\Api\Core\MCError;
use MyCloud\Api\Model\Product;

// ARGUMENTS:
//   [1] Product ID

if ( count($argv) != 2 ) {
	print "Incorrect arguments. Usage:" . PHP_EOL;
	print "    php " . $argv[0] . " productId" . PHP_EOL;
} else {
	try {
		$product = Product::get( $argv[1] );

		if ( $product instanceof MCError ) {
			print "ERROR retrieving product:" . PHP_EOL;
			print "      " . $product->getMessage() . PHP_EOL;
		} else {
			print_product( "Product", $product );
		}
	} catch ( Exception $ex ) {
		print "EXCEPTION: " . $ex->getMessage() . PHP_EOL;
	}
}
