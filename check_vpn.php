<?php
$d = json_decode(file_get_contents('/var/www/html/dashboard/data/vpn.json'), true);
foreach ($d['data'] as $v) {
    echo $v['name'] . ': ' . ($v['status'] ?? 'X') . "\n";
}
