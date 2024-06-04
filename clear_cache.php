<?php

require('../../../wp-load.php');

// Clear WP Rocket cache
if (function_exists('rocket_clean_domain')) {
	rocket_clean_domain();
}
if (function_exists('rocket_clean_minify')) {
	rocket_clean_minify();
}

// WP Fastest Cache
if (function_exists('wpfc_clear_all_cache')) {
	wpfc_clear_all_cache(true);
}

// WP Super Cache
if (function_exists('wp_cache_clear_cache')) {
	wp_cache_clear_cache();
}

// W3 Total Cache
if (function_exists('w3tc_flush_all')) {
	w3tc_flush_all();
}

// Autoptimize
if (class_exists('autoptimizeCache')) {
	autoptimizeCache::clearall();
}

// Asset CleanUp Pro
if (class_exists('WpAssetCleanUpPro')) {
	$wpacuPro = new WpAssetCleanUpPro();
	$wpacuPro->clearPageCache();
}