<?php
// Aggregates previous day's views into user_views_daily for fast leaderboards
// Usage: php cron/aggregate_views_daily.php

require_once __DIR__ . '/../assets/includes/app_start.php';

if (!function_exists('PT_TableExists') || !PT_TableExists(T_USER_VIEWS_DAILY)) {
    echo "Aggregation table not found. Run db/aggregations.sql first.\n";
    exit(1);
}

$yesterday = date('Y-m-d', strtotime('-1 day'));
$start = strtotime($yesterday . ' 00:00:00');
$end   = strtotime($yesterday . ' 23:59:59');

// Calculate counts per owner from views
$sql = 'SELECT u.user_id AS user_id, COUNT(*) AS count
        FROM ' . T_VIEWS . ' v JOIN ' . T_VIDEOS . ' u ON u.id = v.video_id
        WHERE v.time >= ' . $start . ' AND v.time <= ' . $end . '
        GROUP BY u.user_id';

$rows = $db->rawQuery($sql);

if (!empty($rows)) {
    foreach ($rows as $r) {
        $db->rawQuery("INSERT INTO `" . T_USER_VIEWS_DAILY . "` (`user_id`,`date`,`view_count`) VALUES (?,?,?)
                       ON DUPLICATE KEY UPDATE `view_count` = VALUES(`view_count`)",
                       array($r->user_id, $yesterday, $r->count));
    }
    echo "Aggregated " . count($rows) . " user rows for $yesterday\n";
} else {
    echo "No views to aggregate for $yesterday\n";
}

