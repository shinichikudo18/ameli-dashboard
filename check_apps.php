<?php
$d = json_decode(file_get_contents('/var/www/html/dashboard/data/sessions.json'), true);
echo "Sessions with app_list_id > 0:\n";
$count = 0;
foreach ($d['details'] ?? [] as $s) {
    if (($s['app_list_id'] ?? 0) > 0) {
        $app = $s['apps'][0] ?? [];
        echo "app_list_id: " . $s['app_list_id'] . " | App ID: " . ($app['id'] ?? 'N/A') . " | Name: " . ($app['name'] ?? 'N/A') . " | Proto: " . ($s['proto'] ?? 'N/A') . " | Port: " . ($s['dport'] ?? 'N/A') . "\n";
        $count++;
        if ($count >= 50) break;
    }
}
echo "\nTotal with app_list_id > 0: ";
$count = 0;
foreach ($d['details'] ?? [] as $s) {
    if (($s['app_list_id'] ?? 0) > 0) $count++;
}
echo $count . "\n";
