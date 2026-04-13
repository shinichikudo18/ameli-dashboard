<?php
error_reporting(0);
$fgIp = '1.2.3.4';
$fgToken = 'q7N88NNwff4n0d0hs0769Gd03j9gcq';

// Try different VPN endpoints
$endpoints = [
    '/api/v2/monitor/vpn/ipsec/phase2-interface',
    '/api/v2/cmdb/vpn.ipsec/phase1-interface',
    '/api/v2/monitor/vpn/status',
    '/api/v2/cmdb/vpn/ipsec/phase1-interface'
];

foreach ($endpoints as $ep) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://{$fgIp}{$ep}?vdom=root");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fgToken]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $data = json_decode($resp, true);
    echo "$ep: Code $code\n";
    if ($code === 200 && isset($data['results'])) {
        echo "  Has " . count($data['results']) . " results\n";
        if (count($data['results']) > 0) {
            print_r($data['results'][0]);
        }
    } elseif ($code === 200 && isset($data['data'])) {
        echo "  Has data\n";
    }
    echo "\n";
}
