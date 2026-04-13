<?php
$d = json_decode(file_get_contents('/var/www/html/dashboard/data/wifi.json'), true);
echo "APs: " . count($d['aps'] ?? []) . "\n";
echo "Clients: " . count($d['clients'] ?? []) . "\n";
echo "By firewall:\n";
print_r($d['by_firewall'] ?? []);
echo "\nFirst AP:\n";
if (!empty($d['aps'])) print_r($d['aps'][0]);
