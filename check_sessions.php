<?php
$d = json_decode(file_get_contents('/var/www/html/dashboard/data/sessions.json'), true);
echo "Total: " . ($d['total'] ?? 0) . "\n";
echo "By firewall:\n";
print_r($d['by_firewall'] ?? []);
echo "\nFirst 3 sessions:\n";
$sessions = $d['details'] ?? [];
for ($i = 0; $i < 3 && $i < count($sessions); $i++) {
    echo $sessions[$i]['firewall'] . ": " . $sessions[$i]['saddr'] . " -> " . $sessions[$i]['daddr'] . "\n";
}
