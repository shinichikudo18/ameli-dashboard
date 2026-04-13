<?php
$json = file_get_contents('http://localhost/dashboard/proxy.php?action=sessions');
$data = json_decode($json, true);
$apps = [];
foreach ($data['results'] ?? [] as $s) {
    $appName = $s['app_name'] ?? 'Unknown';
    if (!isset($apps[$appName])) $apps[$appName] = 0;
    $apps[$appName]++;
}
arsort($apps);
echo "App distribution (top 30):\n";
$i = 0;
foreach ($apps as $app => $count) {
    echo "$count - $app\n";
    $i++;
    if ($i >= 30) break;
}
