<?php
// +------------------------------------------------------------------------+
// | @author What So Hot
// | @author_url 1: https://whatsohot.com
// | @author_url 2: https://avdbapi.com
// | @author_email: admin@whatsohot.com   
// +------------------------------------------------------------------------+
// | Whatsohot.com - Discover Top Trending Content
// | Copyright (c) 2025  Whatsohot. All rights reserved.
// +------------------------------------------------------------------------+

// if (!PT_IsAdmin()) {
// 	$config = array_intersect_key($config, array_flip($site_public_data));
// }
$config['currency_array'] = $pt->config->currency_array;
$config['currency_symbol_array'] = $pt->config->currency_symbol_array;
$config['payed_subscribers'] = $pt->config->payed_subscribers;
$config['continents'] = $pt->continents;
$config['movies_categories'] = $pt->movies_categories;
$config['sub_categories'] = $pt->sub_categories;
$config['categories'] = $pt->categories;

$config = json_encode($config, JSON_PRETTY_PRINT);
$config = openssl_encrypt($config, "AES-128-ECB", $siteEncryptKey);

$response_data       = array(
    'api_status'     => '200',
    'api_version'    => $api_version,
    'data'           => array(
        'site_settings'  => $config
    )
);
