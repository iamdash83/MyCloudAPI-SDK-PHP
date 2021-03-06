<?php

define('MCAPI_CONFIG_PATH', '.');
require 'bootstrap.php';
require 'printers.php';

use MyCloud\Api\Core\MCError;
use MyCloud\Api\Model\ProductCategory;

// ARGUMENTS:
//   [1] ProductCategory ID

if ( count($argv) != 2 ) {
	print "Incorrect arguments. Usage:" . PHP_EOL;
	print "    php " . $argv[0] . " productCategoryId" . PHP_EOL;
} else {
	try {
		$category = ProductCategory::get( $argv[1] );

		if ( $category instanceof MCError ) {
			print "ERROR retrieving ProductCategory:" . PHP_EOL;
			print "      " . $category->getMessage() . PHP_EOL;
		} else {
			print_category( "ProductCategory", $category );
		}
	} catch ( Exception $ex ) {
		print "EXCEPTION: " . $ex->getMessage() . PHP_EOL;
	}
}
