<?php
if (IS_LOGGED == false) {
    $data = array('status' => 400, 'error' => 'Not logged in');
    echo json_encode($data);
    exit();
}

// Debug: Check what parameters we're receiving
$debug_info = array(
    'get_first' => $_GET['first'] ?? 'not set',
    'post_id' => $_POST['id'] ?? 'not set', 
    'get_id' => $_GET['id'] ?? 'not set',
    'all_get' => $_GET,
    'all_post' => $_POST
);

if (!empty($_GET['first']) && (!empty($_POST['id']) || !empty($_GET['id']))) {
    $id = PT_Secure(!empty($_POST['id']) ? $_POST['id'] : $_GET['id']);
    $reply_data = $db->where('id', $id)->getOne(T_COMM_REPLIES);
    
    // Debug: Check if reply exists
    $debug_info['reply_exists'] = !empty($reply_data) ? 'yes' : 'no';
    $debug_info['reply_id_searched'] = $id;
    if (!empty($reply_data)) {
        $debug_info['reply_data'] = array(
            'id' => $reply_data->id,
            'user_id' => $reply_data->user_id,
            'comment_id' => $reply_data->comment_id,
            'text' => substr($reply_data->text, 0, 50) . '...'
        );
    }
    
    if (!empty($reply_data)) {
        if ($_GET['first'] == 'like' || $_GET['first'] == 'up') {
            $db->where('user_id', $user->id);
            $db->where('reply_id', $id);
            $db->where('type', 1);
            $check_for_like = $db->getValue(T_COMMENTS_LIKES, 'count(*)');
            
            // Debug: Check like status
            $debug_info['action'] = 'like/up';
            $debug_info['check_for_like'] = $check_for_like;
            $debug_info['user_id'] = $user->id;
            if ($check_for_like > 0) {
                $db->where('user_id', $user->id);
                $db->where('reply_id', $id);
                $db->where('type', 1);
                $delete = $db->delete(T_COMMENTS_LIKES);
                $data   = array(
                    'status' => 200,
                    'type' => 'deleted_like'
                );
            } else {
                $db->where('user_id', $user->id);
                $db->where('reply_id', $id);
                $db->where('type', 2);
                $delete = $db->delete(T_COMMENTS_LIKES);

                $insert_data = array(
                    'user_id' => $user->id,
                    'comment_id' => $reply_data->comment_id,
                    'reply_id' => $id,
                    'video_id' => $reply_data->video_id,
                    'post_id' => $reply_data->post_id,
                    'time' => time(),
                    'type' => 1
                );

                $insert = $db->insert(T_COMMENTS_LIKES, $insert_data);
                $debug_info['insert_result'] = $insert;
                $debug_info['insert_data'] = $insert_data;
                
                if ($insert) {
                    $data = array(
                        'status' => 200,
                        'type' => 'added_like'
                    );
                } else {
                    $data = array(
                        'status' => 400,
                        'error' => 'Failed to add like'
                    );
                }
            }
        } elseif ($_GET['first'] == 'dislike' || $_GET['first'] == 'down') {
            $db->where('user_id', $user->id);
            $db->where('reply_id', $id);
            $db->where('type', 2);
            $check_for_like = $db->getValue(T_COMMENTS_LIKES, 'count(*)');
            
            // Debug: Check dislike status
            $debug_info['action'] = 'dislike/down';
            $debug_info['check_for_dislike'] = $check_for_like;

            if ($check_for_like > 0) {
                $db->where('user_id', $user->id);
                $db->where('reply_id', $id);
                $db->where('type', 2);
                $delete = $db->delete(T_COMMENTS_LIKES);
                $data   = array(
                    'status' => 200,
                    'type' => 'deleted_dislike',
                    'code' => 0,
                );
            } else {
                $db->where('user_id', $user->id);
                $db->where('reply_id', $id);
                $db->where('type', 1);
                $delete = $db->delete(T_COMMENTS_LIKES);

                $insert_data = array(
                    'user_id' => $user->id,
                    'comment_id' => $reply_data->comment_id,
                    'reply_id' => $id,
                    'video_id' => $reply_data->video_id,
                    'post_id' => $reply_data->post_id,
                    'time' => time(),
                    'type' => 2
                );

                $insert = $db->insert(T_COMMENTS_LIKES, $insert_data);
                if ($insert) {
                    $data = array(
                        'status' => 200,
                        'type' => 'added_dislike',
                        'code' => 1
                    );
                } else {
                    $data = array(
                        'status' => 400,
                        'error' => 'Failed to add dislike'
                    );
                }
            }
        } else {
            // Unknown action
            $data = array('status' => 400, 'error' => 'Unknown action', 'debug' => $debug_info);
            echo json_encode($data);
            exit();
        }

        if (in_array($data['type'], array('added_like','added_dislike'))) {
            if ($reply_data->user_id != $user->id) {
                $type       = ($data['type'] == 'added_like') ? 'liked_ur_comment' : 'disliked_ur_comment';
                $notif_data = array(
                    'notifier_id' => $pt->user->id,
                    'recipient_id' => $reply_data->user_id,
                    'type' => $type,
                    'url' => ('@'.$pt->user->username),
                    'time' => time()
                );

                if (!empty($reply_data->video_id)) {
                    $video_data = $db->where('id',$reply_data->video_id)->getOne(T_VIDEOS);
                    $uniq_id           = $video_data->video_id;
                    $notif_data['url'] = "watch/$uniq_id&rl=$id";
                } else if(!empty($reply_data->post_id)){
                    $post_data = $db->where('id',$reply_data->post_id)->getOne(T_POSTS);
                    $uniq_id           = $post_data->id;
                    $notif_data['url'] = "articles/read/$uniq_id&rl=$id";
                }

                pt_notify($notif_data);
            }
        }

        $db->where('reply_id', $id);
        $db->where('type', 1);
        $data['up']    = $db->getValue(T_COMMENTS_LIKES, "count(*)");

        $db->where('reply_id', $id);
        $db->where('type', 2);
        $data['down'] = $db->getValue(T_COMMENTS_LIKES, "count(*)");
        
        // Debug: Check final data
        $debug_info['final_data'] = $data;
        $debug_info['data_status'] = isset($data['status']) ? $data['status'] : 'not set';
        $debug_info['data_type'] = isset($data['type']) ? $data['type'] : 'not set';
        
        $data['debug'] = $debug_info;
        echo json_encode($data);
        exit();
    } else {
        $data = array('status' => 400, 'error' => 'Reply not found', 'debug' => $debug_info);
        echo json_encode($data);
        exit();
    }
} else {
    $data = array('status' => 400, 'error' => 'Missing parameters', 'debug' => $debug_info);
    echo json_encode($data);
    exit();
}
?>