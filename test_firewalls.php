<?php
error_reporting(1);
ini_set('display_errors', 1);

$fortigates = [
    'fg-oficina' => ['ip' => '192.168.100.1', 'token' => 'q7N88NNwff4n0d0hs0769Gd03j9gcq', 'name' => 'FG Oficina'],
    'fg-data' => ['ip' => '1.2.3.5', 'token' => 'rzyhGgcHtsst87nr9jtQ3k0rtrcrfn', 'name' => 'FG Data']
];

echo "Testing FG Oficina (192.168.100.1)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://192.168.100.1/api/v2/monitor/system/status?vdom=root");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer q7N88NNwff4n0d0hs0769Gd03j9gcq']);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Code: $code\n";
if ($code === 200) {
    $data = json_decode($resp, true);
    echo "Hostname: " . ($data['results'][0]['hostname'] ?? 'N/A') . "\n";
} else {
    echo "Error: " . substr($resp, 0, 200) . "\n";
}
curl_close($ch);

echo "\nTesting FG Data (1.2.3.5)...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://1.2.3.5/api/v2/monitor/system/status?vdom=root");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer rzyhGgcHtsst87nr9jtQ3k0rtrcrfn']);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
echo "Code: $code\n";
if ($code === 200) {
    $data = json_decode($resp, true);
    echo "Hostname: " . ($data['results'][0]['hostname'] ?? 'N/A') . "\n";
} else {
    echo "Error: " . substr($resp, 0, 200) . "\n";
}
curl_close($ch);
