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

# Include Initial Modules
require('assets/api-v1.0-config.php');
require('assets/api-v1.0-init.php');


if ($application == 'phone') {

	$appmod = "app_api/v$api_version/platform/mobile/$type.php";

	if (file_exists($appmod)) {
		require_once $appmod;
	}

	else{
		$response_data       = array(
	        'api_status'     => '400',
	        'api_version'    => $api_version,
	        'errors'         => array(
	            'error_id'   => '1',
	            'error_text' => 'Error: 400 Bad request, no type specified!'
	        )
	    );
	}	
} 

else if ($application == 'desktop'){
	/* .... */
}