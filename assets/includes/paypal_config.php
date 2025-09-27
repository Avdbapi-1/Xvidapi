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

$url = "https://api-m.sandbox.paypal.com";
if ($pt->config->paypal_mode == 'live') {
    $url = "https://api-m.paypal.com";
}

$pt->paypal_access_token = null;
$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $url . '/v1/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
curl_setopt($ch, CURLOPT_USERPWD, $pt->config->paypal_id . ':' . $pt->config->paypal_secret);

$headers = array();
$headers[] = 'Content-Type: application/x-www-form-urlencoded';
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

$result = curl_exec($ch);
curl_close($ch);
$result = json_decode($result);
if (!empty($result->access_token)) {
  $pt->paypal_access_token = $result->access_token;
}