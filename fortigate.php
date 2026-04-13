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
    'switch-controller/vlan',
    'system/interface',
    'wifi/managed_ap',
    'system/status',
    'system/resource',
    'firewall/address',
    'firewall/policy',
    'ips/stats',
    'webfilter/stats',
    'antivirus/stats',
    'application/list',
    'vpn/ipsec/phase1-interface',
    'vpn/ssl/stats',
    'wireless-controller/ssid',
    'wireless-controller/client',
    'switch-controller/managed-switch',
    'firewall/Service/custom',
    'firewall/Service/group',
    'firewall/schedule',
    'system/time',
    'system/firmware'
];

function fetchFromDevice($device, $endpoint, $vdom) {
    global $monitorEndpoints;
    $isMonitor = in_array($endpoint, $monitorEndpoints);
    $baseUrl = $isMonitor ? 'https://' . $device['ip'] . '/api/v2/monitor' : 'https://' . $device['ip'] . '/api/v2/cmdb';

    $makeRequest = function($url) use ($device) {
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

        return [$response, $httpCode, $error];
    };

    if ($endpoint === 'firewall/session') {
        $allDetails = [];

        foreach ([0, 1000] as $start) {
            $url = $baseUrl . '/firewall/session/select?vdom=' . $vdom . '&count=1000&start=' . $start;
            list($response, $httpCode, $error) = $makeRequest($url);

            if ($error) {
                return ['error' => $error, 'http_code' => 500];
            }

            if ($httpCode !== 200) {
                return ['error' => 'HTTP ' . $httpCode, 'http_code' => $httpCode, 'raw' => $response];
            }

            $result = json_decode($response, true);
            if ($result === null) {
                return ['error' => 'Invalid JSON', 'http_code' => $httpCode, 'raw' => $response];
            }

            $details = $result['results']['details'] ?? [];
            if (!empty($details)) {
                $allDetails = array_merge($allDetails, $details);
            }

            if (count($details) < 1000) {
                break;
            }
        }

        $result = ['results' => ['details' => $allDetails], 'http_code' => 200];
        $result['device_id'] = $device['name'];
        $result['device_short'] = $device['short_name'];
        $result['device_ip'] = $device['ip'];
        return $result;
    }

    if ($endpoint === 'vpn') {
        $sections = [
            'phase1' => 'vpn.ipsec/phase1-interface',
            'phase2' => 'vpn.ipsec/phase2-interface',
            'ssl_settings' => 'vpn.ssl/settings',
            'ssl_portal' => 'vpn.ssl.web/portal'
        ];

        $sectionData = [];

        foreach ($sections as $key => $pathName) {
            list($resp, $code, $err) = $makeRequest($baseUrl . '/' . $pathName . '?vdom=' . $vdom);
            if ($err) {
                return ['error' => $err, 'http_code' => 500];
            }
            if ($code !== 200) {
                return ['error' => 'HTTP ' . $code, 'http_code' => $code, 'raw' => $resp];
            }
            $data = json_decode($resp, true);
            if ($data === null) {
                return ['error' => 'Invalid JSON', 'http_code' => $code, 'raw' => $resp];
            }
            $sectionData[$key] = $data['results'] ?? [];
        }

        $phase1 = $sectionData['phase1'] ?? [];
        $phase2 = $sectionData['phase2'] ?? [];
        $sslSettings = $sectionData['ssl_settings'] ?? [];
        $sslPortal = $sectionData['ssl_portal'] ?? [];

        $summary = [
            'ipsec_tunnels' => count($phase1),
            'ssl_portals' => count($sslPortal),
            'ssl_pools' => count($sslSettings['tunnel-ip-pools'] ?? []),
            'health_checks' => count($sslSettings['authentication-rule'] ?? []),
            'users' => count($sslSettings['authentication-rule'] ?? []),
        ];

        $item = [
            'firewall' => $device['short_name'],
            'device_ip' => $device['ip'],
            'summary' => $summary,
            'ssl_settings' => $sslSettings,
            'phase1' => $phase1,
            'phase2' => $phase2,
            'ssl_portal' => $sslPortal,
            'kind' => 'vpn'
        ];

        $result = ['results' => [$item], 'http_code' => 200];
        $result['device_id'] = $device['name'];
        $result['device_short'] = $device['short_name'];
        $result['device_ip'] = $device['ip'];
        return $result;
    }

    if ($endpoint === 'sdwan') {
        $sdwanUrl = $baseUrl . '/system/sdwan?vdom=' . $vdom;
        $interfaceUrl = $baseUrl . '/system/interface?vdom=' . $vdom;

        list($sdwanResp, $sdwanCode, $sdwanErr) = $makeRequest($sdwanUrl);
        if ($sdwanErr) {
            return ['error' => $sdwanErr, 'http_code' => 500];
        }
        if ($sdwanCode !== 200) {
            return ['error' => 'HTTP ' . $sdwanCode, 'http_code' => $sdwanCode, 'raw' => $sdwanResp];
        }

        list($ifaceResp, $ifaceCode, $ifaceErr) = $makeRequest($interfaceUrl);
        $ifaceResults = [];
        if (!$ifaceErr && $ifaceCode === 200) {
            $ifaceData = json_decode($ifaceResp, true);
            $ifaceResults = $ifaceData['results'] ?? [];
        }

        $sdwanData = json_decode($sdwanResp, true);
        if ($sdwanData === null) {
            return ['error' => 'Invalid JSON', 'http_code' => $sdwanCode, 'raw' => $sdwanResp];
        }

        $sdwan = $sdwanData['results'] ?? [];
        $ifaceMap = [];
        foreach ($ifaceResults as $iface) {
            if (isset($iface['name'])) {
                $ifaceMap[$iface['name']] = $iface;
            }
        }

        $members = [];
        foreach (($sdwan['members'] ?? []) as $member) {
            $name = $member['interface'] ?? '';
            $iface = $ifaceMap[$name] ?? [];
            $member['interface_details'] = [
                'status' => $iface['status'] ?? $iface['link'] ?? null,
                'mode' => $iface['mode'] ?? null,
                'ip' => $iface['ip'] ?? null,
                'ip6' => $iface['ip6'] ?? null,
                'mtu' => $iface['mtu'] ?? null,
                'speed' => $iface['speed'] ?? null,
                'type' => $iface['type'] ?? null,
                'alias' => $iface['alias'] ?? null
            ];
            $members[] = $member;
        }

        $healthChecks = [];
        foreach (($sdwan['health-check'] ?? []) as $hc) {
            $healthChecks[] = [
                'name' => $hc['name'] ?? '',
                'server' => trim($hc['server'] ?? '', '"'),
                'protocol' => $hc['protocol'] ?? '',
                'interval' => $hc['interval'] ?? 0,
                'failtime' => $hc['failtime'] ?? 0,
                'recoverytime' => $hc['recoverytime'] ?? 0,
                'members' => count($hc['members'] ?? []),
                'sla' => count($hc['sla'] ?? [])
            ];
        }

        $services = [];
        foreach (($sdwan['service'] ?? []) as $svc) {
            $services[] = [
                'id' => $svc['id'] ?? null,
                'name' => $svc['name'] ?? '',
                'mode' => $svc['mode'] ?? '',
                'status' => $svc['status'] ?? '',
                'internet_service' => $svc['internet-service'] ?? '',
                'internet_services' => array_map(function($x) { return $x['name'] ?? ''; }, $svc['internet-service-name'] ?? []),
                'health_checks' => array_map(function($x) { return $x['name'] ?? ''; }, $svc['health-check'] ?? []),
                'priority_members' => count($svc['priority-members'] ?? []),
                'dst_count' => count($svc['dst'] ?? []),
                'src_count' => count($svc['src'] ?? [])
            ];
        }

        $zones = array_map(function($z) { return $z['name'] ?? ''; }, $sdwan['zone'] ?? []);
        $enabledMembers = array_filter($members, function($m) { return ($m['status'] ?? '') === 'enable'; });

        $normalized = [
            'status' => $sdwan['status'] ?? 'unknown',
            'mode' => $sdwan['load-balance-mode'] ?? 'unknown',
            'zones' => $zones,
            'members' => $members,
            'health_checks' => $healthChecks,
            'services' => $services,
            'summary' => [
                'zones' => count($zones),
                'members' => count($members),
                'enabled_members' => count($enabledMembers),
                'health_checks' => count($healthChecks),
                'services' => count($services)
            ]
        ];

        $result = ['results' => [$normalized], 'http_code' => 200];
        $result['device_id'] = $device['name'];
        $result['device_short'] = $device['short_name'];
        $result['device_ip'] = $device['ip'];
        return $result;
    }

    $url = $baseUrl . '/' . $endpoint . '?vdom=' . $vdom;

    if (isset($_GET['start'])) $url .= '&start=' . $_GET['start'];
    if (isset($_GET['count'])) $url .= '&count=' . $_GET['count'];
    if (isset($_GET['switch_id'])) $url .= '&switch_id=' . $_GET['switch_id'];

    list($response, $httpCode, $error) = $makeRequest($url);

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
