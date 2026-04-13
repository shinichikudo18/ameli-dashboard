<?php
$json = file_get_contents('/var/www/html/dashboard/data/sessions.json');
$data = json_decode($json, true);
$apps = [];
foreach ($data['details'] ?? [] as $s) {
    $appName = $s['app_name'] ?? 'Unknown';
    if (!isset($apps[$appName])) $apps[$appName] = 0;
    $apps[$appName]++;
}
arsort($apps);
echo "App distribution:\n";
foreach ($apps as $app => $count) {
    echo "$count - $app\n";
}
