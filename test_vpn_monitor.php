<?php
error_reporting(0);
$fgIp = '1.2.3.4';
$fgToken = 'q7N88NNwff4n0d0hs0769Gd03j9gcq';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://{$fgIp}/api/v2/monitor/vpn/ipsec/phase1-interface?vdom=root");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fgToken]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($resp, true);
echo "Code: $code\n";
echo "Response:\n";
print_r($data);
