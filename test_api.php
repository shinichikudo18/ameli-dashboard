<?php
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=all&endpoint=firewall/session&start=0&count=2');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
if (isset($data['results'][0])) {
    $first = $data['results'][0];
    echo "Primer resultado:\n";
    echo "Tiene firewall: " . (isset($first['firewall']) ? 'SI - ' . $first['firewall'] : 'NO') . "\n";
    echo "Keys: " . implode(', ', array_keys($first)) . "\n";
} else {
    echo "No hay results\n";
    echo "Keys: " . implode(', ', array_keys($data)) . "\n";
}
