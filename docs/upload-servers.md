# Multi-Server Upload & Conversion Guide

This guide explains how to add extra VPS nodes (Linux or Windows) to handle video uploads and FFmpeg conversion, and connect them to your main site.

The recommended pattern keeps everything on the same origin via a reverse proxy from the main domain. This avoids cross‑domain cookies and CORS complexity.

---

## Overview

- Same database for all nodes (main + VPS)
- Nodes run the same app code (or a minimal subset) and FFmpeg
- Main site proxies upload requests to nodes via distinct paths
- Optional: nodes push converted files to S3/Wasabi/CDN

---

## 1) Prepare Each VPS Node

Requirements
- PHP ≥ 7.1 with extensions: `mysqli`, `curl`, `gd`, `zip`
- Web server (Nginx/Apache or IIS on Windows)
- FFmpeg installed (`/usr/bin/ffmpeg` on Linux; `C:\\ffmpeg\\bin\\ffmpeg.exe` on Windows)
- Writable folders: `upload/` and its subfolders

Deploy code
- Copy the site files to the VPS web root (you can deploy full code or a minimal subset, but include `ajax.php`, the `ajax/` folder, `assets/` libs, and `upload/`).
- Ensure `nodejs/config.json` exists (as in main site). It’s used by app bootstrapping; you can reuse values from the main server.

Configure database and site URL
- Edit `config.php` on the VPS:
  - Point DB credentials to the same MySQL as the main site
  - Set `$site_url` to your main domain (e.g., `https://whatsohot.com`). This keeps generated links consistent.
- In Admin on the main site, ensure `ffmpeg_system = on` and set `ffmpeg_binary_file` for each node’s environment (nodes read config.php).

Routing rewrite on the node
- Nginx (on the VPS) — in the server block:
  ```nginx
  location ~ ^/aj/(.*)$ {
    try_files $uri /ajax.php?type=$1&$query_string;
  }
  ```
- Apache — add to the site’s `.htaccess` or vhost:
  ```apache
  RewriteEngine On
  RewriteRule ^aj/(.*)$ ajax.php?type=$1 [L,QSA]
  ```

Windows specifics
- Ensure `shell_exec` is not disabled in `php.ini`
- Use a full, quoted path for FFmpeg in config (e.g., `C:\\ffmpeg\\bin\\ffmpeg.exe`)

---

## 2) Reverse Proxy From Main Site (Recommended)

On the main site (whatsohot.com), add a proxy path for each VPS node. This keeps requests on the same origin so your login cookie remains valid.

Nginx (main server)
```nginx
# Linux node example
location /node-lnx-1/aj/ {
  proxy_pass https://vps-linux-1.yourdomain.com/aj/;
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;
  # Important for large chunks / long conversions
  proxy_request_buffering off;
  proxy_read_timeout 600s;
}

# Windows node example
location /node-win-1/aj/ {
  proxy_pass https://win-node.yourdomain.com/aj/;
  proxy_set_header Host $host;
  proxy_set_header X-Real-IP $remote_addr;
  proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
  proxy_set_header X-Forwarded-Proto $scheme;
  proxy_request_buffering off;
  proxy_read_timeout 600s;
}
```

Apache (main server)
```apache
ProxyPass "/node-lnx-1/aj/"  "https://vps-linux-1.yourdomain.com/aj/"
ProxyPassReverse "/node-lnx-1/aj/"  "https://vps-linux-1.yourdomain.com/aj/"

ProxyPass "/node-win-1/aj/"  "https://win-node.yourdomain.com/aj/"
ProxyPassReverse "/node-win-1/aj/"  "https://win-node.yourdomain.com/aj/"

# Optional performance / timeout tuning
ProxyTimeout 600
RequestReadTimeout header=60 body=0,MinRate=500
```

Notes
- Consider `client_max_body_size 0;` (Nginx) if you run into body size limits
- Keep HTTPS everywhere; self‑signed certs on nodes require trust config or use proper certificates

---

## 3) Register Nodes In The App

Edit `config/upload_servers.php` and add one array entry per node. Use the proxy paths you just created.

Example
```php
$UPLOAD_SERVERS = array(
  array(
    'id'          => 'local',
    'name'        => 'This Server',
    'upload_url'  => 'aj/upload-video-ffmpeg',
    'enabled'     => true,
    'max_size_mb' => 0,
    'notes'       => 'Default local uploader with chunking',
  ),
  array(
    'id'          => 'lnx1',
    'name'        => 'Linux Node 1',
    'upload_url'  => '/node-lnx-1/aj/upload-video-ffmpeg',
    'enabled'     => true,
    'notes'       => 'Ubuntu FFmpeg node',
  ),
  array(
    'id'          => 'win1',
    'name'        => 'Windows Node 1',
    'upload_url'  => '/node-win-1/aj/upload-video-ffmpeg',
    'enabled'     => true,
    'notes'       => 'Windows FFmpeg node',
  ),
);
```

UI behavior
- The upload page shows a server selector
- When you pick a server, the chunk upload URL and the submit‑details URL are switched to that server

---

## 4) Health Check & Testing

Health check
- Each node exposes `?ping=1` on the uploader endpoint
- Test from your browser:
  - `https://whatsohot.com/node-lnx-1/aj/upload-video-ffmpeg?ping=1`
  - Should return JSON `{ "status": 200, "ok": true }`

Upload test
- Open `https://whatsohot.com/upload-video`
- Select the new server from the dropdown
- Click “Select Media”, choose a small MP4, watch progress
- After upload, fill details and publish

---

## 5) Conversion Jobs On Nodes

Each node should run the conversion cron script. It reads the shared DB and processes files present on that node.

Linux (cron)
```
* * * * * /usr/bin/php /var/www/site/cron-job.php >/dev/null 2>&1
```

Windows (Task Scheduler)
- Create a task to run: `php C:\\path\\to\\site\\cronjob.php`
- Trigger every minute

Notes
- Both `cron-job.php` and `cronjob.php` exist; use the one you already run on the main site to match your environment

---

## 6) Optional: Object Storage

For multiple nodes, consider enabling S3/Wasabi/Backblaze (Admin > Settings > S3). After conversion, nodes call `PT_UploadToS3()` and videos stream from the bucket/CDN.

Benefits
- Nodes don’t need to serve files to users
- Fewer cross‑server file reads

---

## 7) Alternative: Direct Cross‑Domain (CORS)

If you don’t want a reverse proxy and prefer direct node URLs (e.g., `https://vps-linux-1.example.com/aj/upload-video-ffmpeg`), then:
- Add CORS headers for the uploader endpoint on the node (in `ajax/upload-video-ffmpeg.php`):
  ```php
  header('Access-Control-Allow-Origin: https://whatsohot.com');
  header('Access-Control-Allow-Credentials: true');
  header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
  if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }
  ```
- Still point the node to the same database
- Ensure cookies/sessions are valid or rely only on the `?hash=` CSRF token (current code checks `IS_LOGGED` so same‑origin is simpler)

Reverse proxy is strongly recommended to avoid CORS/session complexities.

---

## 8) Troubleshooting

- “Select Media” doesn’t open
  - Hard refresh; we fixed a JS syntax issue in upload templates
- Upload fails immediately
  - Check Nginx/Apache body size limits; disable request buffering for large chunks
- 400 bad‑request on `ajax.php`
  - The `?hash=` token is missing or expired; reload the page
- Conversion never starts
  - Confirm cron is running and that the node has access to the uploaded files
  - Verify FFmpeg path in Admin > Settings > FFmpeg
- Thumbnails not generated
  - Ensure `upload/photos/YYYY/MM` is writable and `shell_exec` allowed

---

## 9) Security & Performance Tips

- HTTPS everywhere; don’t proxy to plain HTTP nodes
- Limit who can access node admin endpoints (firewall/allow‑lists)
- Set `proxy_request_buffering off` and ample `proxy_read_timeout` for large files
- Enable object storage to reduce inter‑server file serving

---

## Appendix: Files Involved

- Server registry: `config/upload_servers.php`
- Server list API: `ajax/upload-servers.php`
- Uploader endpoint: `ajax/upload-video-ffmpeg.php` (supports `?ping=1`)
- Details submit: `ajax/ffmpeg-submit.php`
- UI templates: `themes/*/layout/upload-video/ffmpeg.html`
- Cron workers: `cron-job.php`, `cronjob.php`

---

Need help wiring a specific VPS? Share its URL and web server, and we’ll tailor the proxy block and entry for you.

