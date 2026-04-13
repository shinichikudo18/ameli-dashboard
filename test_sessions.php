<?php
error_reporting(0);
$fgIp = '1.2.3.4';
$fgToken = 'q7N88NNwff4n0d0hs0769Gd03j9gcq';

echo "Test 1: count=1000, start=0\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://{$fgIp}/api/v2/monitor/firewall/session?vdom=root&count=1000&start=0");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fgToken]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($resp, true);
echo "Code: $code\n";
if (isset($data['results']['details'])) {
    echo "Count: " . count($data['results']['details']) . "\n";
    if (count($data['results']['details']) > 0) {
        print_r($data['results']['details'][0]);
    }
} else {
    print_r($data);
}

echo "\n\nTest 2: count=1000, start=1000\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://{$fgIp}/api/v2/monitor/firewall/session?vdom=root&count=1000&start=1000");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fgToken]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$data = json_decode($resp, true);
echo "Code: $code\n";
if (isset($data['results']['details'])) {
    echo "Count: " . count($data['results']['details']) . "\n";
    if (count($data['results']['details']) > 0) {
        print_r($data['results']['details'][0]);
    }
}
