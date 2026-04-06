<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$fortigates = [
    'fg-oficina' => [
        'name' => 'FortiGate Oficina',
        'short_name' => 'Oficina',
        'ip' => '1.2.3.4',
        'token' => 'q7N88NNwff4n0d0hs0769Gd03j9gcq'
    ],
    'fg-data' => [
        'name' => 'FortiGate Data',
        'short_name' => 'Data',
        'ip' => '1.2.3.5',
        'token' => 'rzyhGgcHtsst87nr9jtQ3k0rtrcrfn'
    ]
];

$deviceId = $_GET['device'] ?? 'all';
$vdom = $_GET['vdom'] ?? 'root';
$endpoint = $_GET['endpoint'] ?? 'wifi/client';

$monitorEndpoints = [
    'wifi/client', 
    'system/dhcp', 
    'firewall/session', 
    'wireless-controller/managed-ap', 
    'switch-controller/managed-switch', 
    'switch-controller/vlan',
    'system/interface',
    'wifi/managed_ap'
];

function fetchFromDevice($device, $endpoint, $vdom) {
    global $monitorEndpoints;
    $isMonitor = in_array($endpoint, $monitorEndpoints);
    $baseUrl = $isMonitor ? 'https://' . $device['ip'] . '/api/v2/monitor' : 'https://' . $device['ip'] . '/api/v2/cmdb';
    $url = $baseUrl . '/' . $endpoint . '?vdom=' . $vdom;
    
    if (isset($_GET['start'])) $url .= '&start=' . $_GET['start'];
    if (isset($_GET['count'])) $url .= '&count=' . $_GET['count'];
    if (isset($_GET['switch_id'])) $url .= '&switch_id=' . $_GET['switch_id'];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $device['token']
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => 500];
    }
    
    $result = json_decode($response, true);
    if ($result === null) {
        return ['error' => 'Invalid JSON', 'http_code' => $httpCode, 'raw' => $response];
    }
    
    $result['device_id'] = $device['name'];
    $result['device_short'] = $device['short_name'];
    $result['device_ip'] = $device['ip'];
    $result['http_code'] = $httpCode;
    
    return $result;
}

if ($deviceId === 'all') {
    $allResults = [];
    $devices = [];
    $combinedResults = [];
    $hasError = false;
    
    foreach ($fortigates as $id => $device) {
        $result = fetchFromDevice($device, $endpoint, $vdom);
        $devices[$id] = [
            'name' => $device['name'],
            'short_name' => $device['short_name'],
            'ip' => $device['ip'],
            'status' => isset($result['error']) ? 'error' : 'ok',
            'error' => $result['error'] ?? null
        ];
        
        if (!isset($result['error'])) {
            // Special handling for firewall/session which has results.details
            if ($endpoint === 'firewall/session') {
                $results = $result['results']['details'] ?? [];
            } else {
                $results = $result['results'] ?? [];
            }
            foreach ($results as &$item) {
                $item['firewall'] = $device['short_name'];
                $item['device_ip'] = $device['ip'];
            }
            $combinedResults = array_merge($combinedResults, $results);
        } else {
            $hasError = true;
        }
    }
    
    echo json_encode([
        'results' => $combinedResults,
        'devices' => $devices,
        'total_devices' => count($fortigates),
        'endpoint' => $endpoint
    ]);
    
} else {
    if (!isset($fortigates[$deviceId])) {
        http_response_code(400);
        echo json_encode(['error' => 'Dispositivo no encontrado', 'available' => array_keys($fortigates)]);
        exit;
    }
    
    $device = $fortigates[$deviceId];
    $result = fetchFromDevice($device, $endpoint, $vdom);
    
    if (isset($result['error'])) {
        http_response_code(500);
    }
    
    echo json_encode($result);
}
