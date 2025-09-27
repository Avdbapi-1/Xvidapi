<?php
// Upload server registry for multi-server support.
// You can add remote upload nodes here. Each entry:
// - id: unique identifier
// - name: display name
// - upload_url: absolute URL or site-relative path for the upload endpoint (expects plupload-compatible chunk fields)
// - enabled: boolean
// - max_size_mb: integer max size hint (optional)
// - notes: optional string

$UPLOAD_SERVERS = array(
    array(
        'id'          => 'local',
        'name'        => 'This Server',
        // Site-relative; resolved through PT_Link()
        'upload_url'  => 'aj/upload-video-ffmpeg',
        'enabled'     => true,
        'max_size_mb' => 0,
        'notes'       => 'Default local uploader with chunking',
    ),
);

