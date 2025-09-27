<?php
// Lightweight AVDB auto-import cron
// Usage:
//   - CLI: php cron-avdb.php secret=YOUR_SECRET
//   - HTTP: /cron-avdb.php?secret=YOUR_SECRET

require_once __DIR__ . '/assets/init.php';

header('Content-Type: application/json; charset=utf-8');

function resp($code, $arr) {
    http_response_code($code);
    echo json_encode($arr, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    exit;
}

function cfg_get($k, $def = null) {
    global $pt, $db;
    if (isset($pt->config->{$k})) return $pt->config->{$k};
    // Try DB
    $row = $db->where('name', $k)->getOne(T_CONFIG);
    if ($row && isset($row->value)) return $row->value;
    return $def;
}

function cfg_set($k, $v) {
    global $pt, $db;
    if (isset($pt->config->{$k})) { $pt->config->{$k} = $v; }
    $exists = $db->where('name',$k)->getValue(T_CONFIG,'COUNT(*)');
    if ($exists) {
        $db->where('name',$k)->update(T_CONFIG, ['value' => $v]);
    } else {
        $db->insert(T_CONFIG, ['name' => $k, 'value' => $v]);
    }
}

// Validate secret
$secret = null;
// CLI arg secret=...
if (php_sapi_name() == 'cli') {
    foreach ($argv as $arg) {
        if (strpos($arg, 'secret=') === 0) { $secret = substr($arg, 7); }
    }
} else {
    $secret = isset($_GET['secret']) ? $_GET['secret'] : null;
}

// Ensure defaults
$defaults = [
    'avdb_cron_enable' => 'off',
    'avdb_cron_secret' => '',
    'avdb_cron_api_url' => 'https://avdbapi.com/api.php/provide/vod/?ac=detail&pg=',
    'avdb_cron_page_from' => '1',
    'avdb_cron_page_to' => '100',
    'avdb_cron_next_page' => '1',
    'avdb_cron_items_per_run' => '25',
    'avdb_cron_rate_delay_ms' => '0',
    'avdb_cron_min_interval_sec' => '300',
    'avdb_cron_max_load' => '6',
    'avdb_cron_lock_ttl' => '600',
    'avdb_cron_last_run' => '0',
    'avdb_cron_user_id' => '1'
];
foreach ($defaults as $k=>$v) {
    if (cfg_get($k, null) === null) cfg_set($k, $v);
}
$dbSecret = cfg_get('avdb_cron_secret', '');
if (!$dbSecret) {
    // First-run convenience: if caller sent a secret, adopt it; otherwise generate one
    if (!empty($secret)) {
        cfg_set('avdb_cron_secret', $secret);
        $dbSecret = $secret;
    } else {
        $gen = bin2hex(random_bytes(12));
        cfg_set('avdb_cron_secret', $gen);
        $dbSecret = $gen;
    }
}

if ($secret !== $dbSecret) {
    resp(401, ['status'=>401,'message'=>'invalid secret']);
}

if (cfg_get('avdb_cron_enable') !== 'on') {
    resp(200, ['status'=>204,'message'=>'disabled']);
}

// Smart backoff: min interval
$now = time();
$last = (int)cfg_get('avdb_cron_last_run', 0);
$minInt = (int)cfg_get('avdb_cron_min_interval_sec', 300);
if ($last > 0 && ($now - $last) < $minInt) {
    resp(200, ['status'=>204,'message'=>'too_soon','seconds_until'=>($minInt - ($now-$last))]);
}

// Smart guard: load average (Linux)
if (function_exists('sys_getloadavg')) {
    $load = sys_getloadavg();
    $maxLoad = (float)cfg_get('avdb_cron_max_load', 6);
    if (!empty($load) && $load[0] > $maxLoad) {
        resp(200, ['status'=>204,'message'=>'high_load','load'=>$load[0],'max'=>$maxLoad]);
    }
}

// Acquire lock (file-based)
$lockDir = PT_CacheDir();
$lockFile = $lockDir . '/avdb_cron.lock';
$lockTtl = (int)cfg_get('avdb_cron_lock_ttl', 600);
if (file_exists($lockFile)) {
    $info = @json_decode(@file_get_contents($lockFile), true);
    $ts = isset($info['ts']) ? (int)$info['ts'] : 0;
    if ($ts && (time()-$ts) < $lockTtl) {
        resp(200, ['status'=>204,'message'=>'locked']);
    }
}
@file_put_contents($lockFile, json_encode(['ts'=>time(),'pid'=>getmypid()]), LOCK_EX);

$apiTpl = cfg_get('avdb_cron_api_url');
$pageFrom = (int)cfg_get('avdb_cron_page_from', 1);
$pageTo = (int)cfg_get('avdb_cron_page_to', 1);
$nextPage = (int)cfg_get('avdb_cron_next_page', $pageFrom);
$limit = (int)cfg_get('avdb_cron_items_per_run', 25);
$rateDelay = (int)cfg_get('avdb_cron_rate_delay_ms', 0);

// Only reset nextPage if it's before pageFrom
if ($nextPage < $pageFrom) $nextPage = $pageFrom;

$imported = 0; $pagesScanned = 0; $errors = 0; $updated = 0; $skipped = 0; $startPage = $nextPage;

// Helper: import a single AVDB item (subset of logic from ajax/ap.php)
function avdb_import_item($item) {
    global $db, $pt;
    if (!function_exists('author_to_user_id')) {
        function author_to_user_id($raw, $provider, $fallback) {
            global $db;
            $name = html_entity_decode((string)$raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $name = trim($name);
            if (empty($name) || in_array(strtolower($name), ['updating','unknown','n/a','na'])) { return (int)$fallback; }
            $slug_us = preg_replace('/[^a-z0-9]+/i','_', strtolower($name));
            $slug_us = trim($slug_us, '_');
            $slug_cc = preg_replace('/[^a-z0-9]+/i','', strtolower($name));
            $variants = array_unique(array_filter([$slug_us,$slug_cc,str_replace('_','-',$slug_us),str_replace('_','',$slug_us)]));
            foreach ($variants as $cand) {
                if (strlen($cand) < 3) continue; $u = $db->where('username',$cand)->getOne(T_USERS); if (!empty($u) && !empty($u->id)) return (int)$u->id;
            }
            $slug = $slug_us; if (strlen($slug) < 5) { $slug = 'ch_' . substr(md5($name),0,8); }
            $username = $slug; $i=0; while (PT_UsernameExists($username)) { $i++; $username = $slug.$i; }
            $email = $username.'@'.$provider.'.local'; $i=0; while (PT_UserEmailExists($email)) { $i++; $email = $username.$i.'@'.$provider.'.local'; }
            $pass = bin2hex(random_bytes(6)); $hash = password_hash($pass, PASSWORD_DEFAULT);
            $data = [ 'username'=>$username,'password'=>$hash,'email'=>$email,'gender'=>'male','active'=>1,'email_code'=>sha1(time()+rand(111,999)),'last_active'=>time(),'time'=>time(),'registered'=>date('Y').'/'.intval(date('m'))];
            $nid = $db->insert(T_USERS,$data);
            return !empty($nid) ? (int)$nid : (int)$fallback;
        }
    }
    $title = isset($item['name']) ? $item['name'] : '';
    $desc  = isset($item['description']) ? $item['description'] : '';
    $actors = (!empty($item['actor']) && is_array($item['actor'])) ? $item['actor'] : array();
    $categories = (!empty($item['category']) && is_array($item['category'])) ? $item['category'] : array();
    $movie_code = !empty($item['movie_code']) ? str_replace('-', '', $item['movie_code']) : '';
    $poster_url = isset($item['poster_url']) ? $item['poster_url'] : '';
    $embed_url = '';
    if (!empty($item['episodes']['server_data']['Full']['link_embed'])) {
        $linkEmbed = $item['episodes']['server_data']['Full']['link_embed'];
        $embed_url = $linkEmbed;
        if (preg_match('/[?&]s=([^&#]+)/', $linkEmbed, $m)) {
            $embed_url = urldecode($m[1]);
        }
    }
    if (empty($title) || empty($embed_url)) {
        return ['ok'=>false,'error'=>'missing title or embed url'];
    }
    $tags_arr = array();
    if (!empty($categories)) { $tags_arr = array_merge($tags_arr, $categories); }
    if (!empty($actors)) { $tags_arr = array_merge($tags_arr, $actors); }
    if (!empty($movie_code)) { $tags_arr[] = $movie_code; }
    $tags_str = implode(', ', array_filter($tags_arr));

    $provider = 'avdb';
    $remote_id = isset($item['id']) ? (int)$item['id'] : 0;
    $map = null;
    if ($remote_id > 0) {
        $map = $db->where('provider',$provider)->where('remote_id',$remote_id)->getOne(T_EXTERNAL_MAP);
    }
    if (empty($map) && !empty($movie_code)) {
        $map = $db->where('provider',$provider)->where('movie_code',$movie_code)->getOne(T_EXTERNAL_MAP);
    }
    
    // Debug: Log video status to file
    // Check if video exists in external map

    // Prepare fields
    $duration = !empty($item['time']) ? $item['time'] : '00:00';
    $video_id = PT_GenerateKey(15, 15);
    $title_clean = PT_Secure(addToHashTags($title,1));
    if (function_exists('mb_substr')) { $title_clean = mb_substr($title_clean, 0, 100, 'UTF-8'); } else { $title_clean = substr($title_clean, 0, 100); }
    $desc_clean = PT_Secure(addToHashTags($desc,1));
    $stars = '';
    if (!empty($actors)) { $stars = implode(', ', $actors); }
    if (function_exists('mb_substr')) { $stars = mb_substr($stars, 0, 200, 'UTF-8'); } else { $stars = substr($stars, 0, 200); }

    // Category mapping (optional)
    $category_id = 0; $pick = '';
    if (!empty($item['type_name'])) { $pick = $item['type_name']; }
    if (empty($pick) && !empty($categories)) { $pick = $categories[0]; }
    if (!empty($pick)) {
        $pick = trim($pick);
        $row = $db->where('type','category')->where('english', $pick)->getOne(T_LANGS);
        if (!empty($row) && !empty($row->id)) {
            $category_id = (int)$row->id;
        } else {
            $ins = array('type'=>'category','english'=>PT_Secure($pick));
            $newId = $db->insert(T_LANGS, $ins);
            if (!empty($newId)) {
                $db->where('id',$newId)->update(T_LANGS, array('lang_key'=>$newId));
                $category_id = (int)$newId;
            }
        }
    }
    if (empty($category_id)) {
        $def = 'Uncategorized';
        $row = $db->where('type','category')->where('english',$def)->getOne(T_LANGS);
        if (!empty($row) && !empty($row->id)) {
            $category_id = (int)$row->id;
        } else {
            $newId = $db->insert(T_LANGS, array('type'=>'category','english'=>$def));
            if (!empty($newId)) {
                $db->where('id',$newId)->update(T_LANGS, array('lang_key'=>$newId));
                $category_id = (int)$newId;
            }
        }
    }
    $video_privacy = 0; $age_restriction = 1;
    $ownerId = (int)cfg_get('avdb_cron_user_id', 1);
    if (!empty($item['author'])) { $ownerId = author_to_user_id($item['author'], 'avdb', $ownerId); }
    if ($ownerId <= 0) { $ownerId = 1; }
    // Always auto-approve videos imported by cron (publish immediately)
    $approvedFlag = 1;
    $data_insert = array(
        'video_id' => $video_id,
        'user_id' => $ownerId,
        'title' => $title_clean,
        'description' => $desc_clean,
        'tags' => PT_Secure($tags_str,1),
        'duration' => $duration,
        'category_id' => $category_id,
        'thumbnail' => PT_Secure($poster_url,0),
        'time' => time(),
        'registered' => date('Y') . '/' . intval(date('m')),
        'privacy' => $video_privacy,
        'age_restriction' => $age_restriction,
        'stars' => $stars,
        'embed' => 1,
        'type'  => 'embed',
        'video_location' => PT_Secure($embed_url,0),
        'approved' => $approvedFlag,
    );

    $hash_fields = array(
        'title'=>$title_clean,
        'desc'=>$desc_clean,
        'embed_url'=>$embed_url,
        'poster'=>$poster_url,
        'stars'=>$stars,
        'tags'=>$tags_str,
        'category_id'=>$category_id
    );
    $content_hash = sha1(json_encode($hash_fields));

    if (!empty($map) && !empty($map->local_video_id)) {
        // Check if content has changed by comparing hashes
        if ($map->last_hash === $content_hash) {
            return ['ok'=>true,'skipped'=>true,'id'=>(int)$map->local_video_id,'reason'=>'no_changes'];
        }
        
        $update = array(
            'title' => $title_clean,
            'description' => $desc_clean,
            'tags' => PT_Secure($tags_str,1),
            'thumbnail' => PT_Secure($poster_url,0),
            'category_id' => $category_id,
            'embed' => 1,
            'type' => 'embed',
            'video_location' => PT_Secure($embed_url,0),
            'approved' => $approvedFlag
        );
        $db->where('id',(int)$map->local_video_id)->update(T_VIDEOS,$update);
        $db->where('id',$map->id)->update(T_EXTERNAL_MAP, array('last_hash'=>$content_hash,'updated_at'=>time(),'remote_id'=>$remote_id,'movie_code'=>$movie_code));
        return ['ok'=>true,'updated'=>true,'id'=>(int)$map->local_video_id];
    }
    $ok = $db->insert(T_VIDEOS, $data_insert);
    if ($ok) {
        $db->insert(T_EXTERNAL_MAP, array(
            'provider'=>'avdb',
            'remote_id'=>$remote_id,
            'movie_code'=>$movie_code,
            'local_video_id'=>$ok,
            'last_hash'=>$content_hash,
            'updated_at'=>time()
        ));
        return ['ok'=>true,'inserted'=>true,'id'=>(int)$ok];
    }
    $err = method_exists($db,'getLastError') ? $db->getLastError() : 'insert_failed';
    return ['ok'=>false,'error'=>$err];
}

// Process only ONE page per cron run
if ($nextPage <= $pageTo) {
    $pagesScanned++;
    $api_url = $apiTpl . $nextPage;
    $res = connect_to_url($api_url, ['timeout'=>20,'connect_timeout'=>10]);
    $json = json_decode($res, true);
    if (!empty($json) && !empty($json['list'])) {
        foreach ($json['list'] as $item) {
            if ($imported >= $limit) break;
            // Let the import function handle duplicates intelligently
            $r = avdb_import_item($item);
            if (!empty($r['ok'])) { 
                if (!empty($r['skipped'])) {
                    $skipped++;
                } else {
                    $imported += 1; 
                    if (!empty($r['updated'])) $updated++; 
                }
            } else { 
                $errors++; 
            }
            if ($rateDelay > 0) usleep($rateDelay * 1000);
        }
    }
    // Always increment nextPage after processing a page
    $nextPage++;
    
    // If we've gone past pageTo, reset to pageFrom for looping
    if ($nextPage > $pageTo) {
        $nextPage = $pageFrom;
    }
}

cfg_set('avdb_cron_next_page', (string)$nextPage);
cfg_set('avdb_cron_last_run', (string)time());
@unlink($lockFile);

resp(200, [
    'status'=>200,
    'imported'=>$imported,
    'updated'=>$updated,
    'skipped'=>$skipped,
    'errors'=>$errors,
    'pages_scanned'=>$pagesScanned,
    'next_page'=>$nextPage,
]);
