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

$table                = T_VIDEOS;
$response_data        = array(
    'api_status'      => '200',
    'api_version'     => $api_version,
    'data'            => array(
    	'featured'    => array(),
    	'top'         => array(),
    	'latest'      => array(),
    	'fav'      => array(),
    	'live'      => array(),
    )
);

$get_params           = array(
	'featured_offset' => null,
	'top_offset'      => null,
	'latest_offset'   => null,
	'fav_offset'   => null,
	'live_offset'   => null,
	'limit'           => null
);

foreach ($get_params as $key => $value) {
	if (!empty($_GET[$key]) && is_numeric($_GET[$key])) {
		$get_params[$key] = $_GET[$key];
	}	
}




# Home Page Featured Videos
if (!empty($get_params['featured_offset'])) {
	$db->where('id', $get_params['featured_offset'],'<');
}

$featured = array();
$limit    = ((!empty($get_params['limit'])) ? $get_params['limit'] : 10);
// Avoid RAND(): choose randomly from recent featured
$recent_featured = $db->where('featured','1')->orderBy('id','DESC')->get($table, max($limit*5, 50), array('video_id','user_id'));
if (!empty($recent_featured)) {
    // Pick up to $limit random unique indices
    $count = min($limit, count($recent_featured));
    $idxs = array_rand($recent_featured, $count);
    if (!is_array($idxs)) { $idxs = array($idxs); }
    foreach ($idxs as $ix) { $featured[] = $recent_featured[$ix]; }
}

if (empty($featured)) {
    if (!empty($get_params['featured_offset'])) {
        $db->where('id', $get_params['featured_offset'],'<');
    }
    $featured = $db->orderBy('id', 'DESC')->get(T_VIDEOS,$limit,array('video_id','user_id'));
}


foreach ($featured as $video) {
	$video = PT_GetVideoByID($video->video_id);
	if (!empty($video)) {
		$video->owner = array_intersect_key(ToArray($video->owner), array_flip($user_public_data));
		$response_data['data']['featured'][] = $video;
	}
}

#Home Page Top Videos
if (!empty($get_params['top_offset'])) {
	$db->where('id', $get_params['top_offset'],'<');
}

$limit = ((!empty($get_params['limit'])) ? $get_params['limit'] : 6);
$top   = $db->orderby('views', 'DESC')->get(T_VIDEOS, $limit,array('video_id','user_id'));

foreach ($top as $video) {
	$video = PT_GetVideoByID($video->video_id);
	if (!empty($video)) {
		$video->owner = array_intersect_key(ToArray($video->owner), array_flip($user_public_data));
		$response_data['data']['top'][] = $video;
	}
}


#Home Page Latest Videos
if (!empty($get_params['latest_offset'])) {
	$db->where('id', $get_params['latest_offset'],'<');
}

$limit  = ((!empty($get_params['limit'])) ? $get_params['limit'] : 10);
$latest = $db->orderby('id', 'DESC')->get(T_VIDEOS, $limit,array('video_id','user_id'));

foreach ($latest as $video) {
	$video = PT_GetVideoByID($video->video_id);
	if (!empty($video)) {
		$video->owner = array_intersect_key(ToArray($video->owner), array_flip($user_public_data));
		$response_data['data']['latest'][] = $video;
	}
}

if (IS_LOGGED && !empty($pt->user->fav_category)) {
	$limit  = ((!empty($get_params['limit'])) ? $get_params['limit'] : 10);
	if (!empty($get_params['fav_offset'])) {
		$db->where('id', $get_params['fav_offset'],'<');
	}
	$db->where("category_id",$pt->user->fav_category,"IN");
	$db->where('privacy', 0);
	$db->orderBy('id', 'DESC');
	$pt->cat_videos = $db->where('is_movie', 0)->where('user_id',$pt->blocked_array , 'NOT IN')->get(T_VIDEOS, $limit);
	foreach ($pt->cat_videos as $key => $video) {
		$video = PT_GetVideoByID($video->video_id);
		if (!empty($video)) {
			$video->owner = array_intersect_key(ToArray($video->owner), array_flip($user_public_data));
			$response_data['data']['fav'][] = $video;
		}
	}
}
if ($pt->config->live_video == 1) {
	if (!empty($get_params['live_offset'])) {
		$db->where('id', $get_params['live_offset'],'<');
	}
    $live_data = $db->where('privacy', 0)->where('is_movie', 0)->where('user_id',$pt->blocked_array , 'NOT IN')->where('approved',1)->where('live_time',0,'>')->orderBy('id', 'DESC')->get(T_VIDEOS, $limit);
    if (!empty($live_data)) {
    	foreach ($live_data as $key => $video) {
			$video = PT_GetVideoByID($video->video_id);
			if (!empty($video)) {
				$video->owner = array_intersect_key(ToArray($video->owner), array_flip($user_public_data));
				$response_data['data']['live'][] = $video;
			}
		}
    }
}
