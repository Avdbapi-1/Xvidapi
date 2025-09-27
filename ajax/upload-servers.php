<?php
// Returns a list of available upload servers (for multi-server selection UI)
// Integrates with Whatsohot context for URL building.

if (!defined('IS_LOGGED')) {
    // Load base if called directly
    require_once('./assets/init.php');
}

header('Content-Type: application/json');

if (IS_LOGGED == false || $pt->config->upload_system != 'on') {
    echo json_encode(array('status' => 400, 'error' => 'Not allowed'));
    exit();
}

// Load server registry
$servers = array();
try {
    $UPLOAD_SERVERS = array();
    $configPath = __DIR__ . '/../config/upload_servers.php';
    if (file_exists($configPath)) {
        require $configPath;
    }
    if (!empty($UPLOAD_SERVERS) && is_array($UPLOAD_SERVERS)) {
        foreach ($UPLOAD_SERVERS as $srv) {
            if (!empty($srv['enabled'])) {
                $uploadUrl = $srv['upload_url'];
                // Resolve site-relative URLs
                if (strpos($uploadUrl, 'http://') !== 0 && strpos($uploadUrl, 'https://') !== 0) {
                    $uploadUrl = PT_Link(trim($uploadUrl, '/'));
                }
                $servers[] = array(
                    'id'          => isset($srv['id']) ? $srv['id'] : md5($uploadUrl),
                    'name'        => isset($srv['name']) ? $srv['name'] : $uploadUrl,
                    'upload_url'  => $uploadUrl,
                    'max_size_mb' => isset($srv['max_size_mb']) ? (int)$srv['max_size_mb'] : 0,
                    'notes'       => isset($srv['notes']) ? $srv['notes'] : '',
                    'chunked'     => true,
                );
            }
        }
    }
} catch (Exception $e) {
    // ignore and return empty list
}

echo json_encode(array(
    'status'  => 200,
    'servers' => $servers,
));
exit();

