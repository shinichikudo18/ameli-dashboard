<?php
$d = json_decode(file_get_contents('/var/www/html/dashboard/data/sessions.json'), true);
echo "Total: " . ($d['total'] ?? 0) . "\n";
echo "By firewall:\n";
print_r($d['by_firewall'] ?? []);
