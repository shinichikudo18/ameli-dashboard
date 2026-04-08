<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';
$entity = $_GET['entity'] ?? '';
$baseDir = __DIR__;

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

$haUrl = 'http://192.168.100.3:8123';
$haToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiI2NmMwNjBiMDg3YWI0MjhlYTliODg4N2Q5ZWY5ZDQ2NCIsImlhdCI6MTc3NDk4NDg3MSwiZXhwIjoyMDkwMzQ0ODcxfQ.x8K1uTtPvOde_oKXoBf-m70ilAXy-BVW5aAQeqcNeIc';

switch ($action) {
    case 'entities':
        $domain = $_GET['domain'] ?? '';
        if (empty($domain)) {
            http_response_code(400);
            echo json_encode(['error' => 'Domain required']);
            exit;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $haUrl . '/api/states');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $haToken]);
        $resp = curl_exec($ch);
        $states = json_decode($resp, true);
        curl_close($ch);
        $filtered = array_filter($states, function($s) use ($domain) {
            return strpos($s['entity_id'], $domain . '.') === 0;
        });
        echo json_encode(array_values($filtered));
        break;
        
    case 'ha_entities':
        $entities = [
            'sensor.agnov_fg_wifi_clients_ssid1',
            'sensor.agnov_fg_wifi_clients_ssid2', 
            'sensor.agnov_fg_wifi_clients'
        ];
        $results = [];
        foreach ($entities as $ent) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $haUrl . '/api/states/' . $ent);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $haToken]);
            $resp = curl_exec($ch);
            $data = json_decode($resp, true);
            curl_close($ch);
            $results[] = [
                'name' => $ent,
                'value' => isset($data['state']) ? floatval($data['state']) : 0,
                'unit' => $data['attributes']['unit_of_measurement'] ?? '',
                'showInChart' => true
            ];
        }
        echo json_encode($results);
        break;
        
    case 'entity':
        if (empty($entity)) {
            http_response_code(400);
            echo json_encode(['error' => 'Entity ID required']);
            exit;
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $haUrl . '/api/states/' . $entity);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $haToken]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        http_response_code($httpCode);
        echo $resp;
        break;
        
    case 'ha_service':
        $service = $_POST['service'] ?? '';
        $entityId = $_POST['entity'] ?? '';
        if (empty($service) || empty($entityId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Service and entity required']);
            exit;
        }
        $domain = explode('.', $entityId)[0];
        $postData = json_encode(['entity_id' => $entityId]);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $haUrl . '/api/services/' . $domain . '/' . $service);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $haToken, 'Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        http_response_code($httpCode);
        echo $resp;
        break;
        
    case 'clients':
        $wifi = loadJson($baseDir . '/data/wifi.json');
        echo json_encode(['results' => ['results' => $wifi['clients'] ?? 0]]);
        break;
        
    case 'aps':
        $wifi = loadJson($baseDir . '/data/wifi.json');
        echo json_encode(['results' => ['results' => [['name' => 'WiFi', 'clients' => $wifi['clients'] ?? 0]]]]);
        break;
        
    case 'dhcp':
        $dhcp = loadJson($baseDir . '/data/dhcp.json');
        echo json_encode(['results' => $dhcp['data'] ?? []]);
        break;
        
    case 'sessions':
        $sessions = loadJson($baseDir . '/data/sessions.json');
        echo json_encode(['results' => $sessions['details'] ?? []]);
        break;
        
    case 'switches':
        $switches = loadJson($baseDir . '/data/switches.json');
        $swData = $switches['data'] ?? [];
        echo json_encode(['results' => [
            'summary' => ['total' => $switches['total'] ?? 0, 'online' => $switches['online'] ?? 0, 'offline' => ($switches['total'] ?? 0) - ($switches['online'] ?? 0)],
            'switches' => $swData
        ]]);
        break;

    case 'sdwan':
        echo json_encode(['results' => [
            ['summary' => ['zones' => 1, 'members' => 2, 'enabled_members' => 2, 'health_checks' => 2, 'services' => 1]]
        ]]);
        break;

    case 'red':
    case 'wifi':
        $wifi = loadJson($baseDir . '/data/wifi.json');
        $dhcp = loadJson($baseDir . '/data/dhcp.json');
        $sessions = loadJson($baseDir . '/data/sessions.json');
        $interfaces = loadJson($baseDir . '/data/interfaces.json');
        echo json_encode([
            'results' => [
                'wifi_clients' => $wifi['clients'] ?? 0,
                'wifi_aps' => $wifi['aps'] ?? 0,
                'dhcp_leases' => count($dhcp['data'] ?? []),
                'dhcp_devices' => $dhcp['data'] ?? [],
                'sessions_total' => $sessions['total'] ?? 0,
                'sessions_blocked' => $sessions['blocked'] ?? 0,
                'sessions_details' => array_slice($sessions['details'] ?? [], 0, 50),
                'interfaces' => $interfaces['data'] ?? []
            ]
        ]);
        break;

    case 'vpn':
        $vpn = loadJson($baseDir . '/data/vpn.json');
        $vpnData = $vpn['data'] ?? [];
        if (empty($vpnData)) {
            $vpnData = [
                ['name' => 'VPN-SSL', 'status' => 'up', 'remote-gateway' => '0.0.0.0', 'type' => 'ssl'],
                ['name' => 'VPN-IPSec-Office', 'status' => 'up', 'remote-gateway' => '192.168.100.1', 'type' => 'ipsec']
            ];
        }
        echo json_encode(['results' => [[
            'summary' => ['ipsec_tunnels' => count(array_filter($vpnData, fn($v) => ($v['type'] ?? '') === 'ipsec')), 'ssl_portals' => 1, 'ssl_pools' => 1, 'health_checks' => 0],
            'phase1' => $vpnData,
            'ssl_settings' => ['port' => 443, 'login' => 'enabled']
        ]]]);
        break;

    case 'vpn-users':
        echo json_encode(['results' => [
            ['type' => 'auth_logon', 'user' => 'admin', 'ip' => '192.168.100.50', 'duration' => 3600],
            ['type' => 'auth_logon', 'user' => 'user1', 'ip' => '192.168.100.51', 'duration' => 1800]
        ]]);
        break;

    case 'fortivoice':
        $voip = loadJson($baseDir . '/data/voip.json');
        echo json_encode(['results' => ['phones' => $voip['phones'] ?? [], 'collection' => $voip['phones'] ?? []]]);
        break;

    case 'fortivoice-licenses':
        $voip = loadJson($baseDir . '/data/voip.json');
        echo json_encode(['results' => ['total' => $voip['devices'] ?? 0, 'used' => $voip['registered'] ?? 0]]);
        break;

    case 'freepbx':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/FreePBX.php?action=dashboard');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;
        
    case 'soc_data':
        $metrics = loadJson($baseDir . '/data/metrics_current.json');
        $interfaces = loadJson($baseDir . '/data/interfaces.json');
        $voip = loadJson($baseDir . '/data/voip.json');
        $dhcp = loadJson($baseDir . '/data/dhcp.json');
        
        $registeredPhones = array_filter($voip['phones'] ?? [], fn($p) => $p['status'] === 'registered');
        
        $socData = [
            'timestamp' => $metrics['timestamp'] ?? date('Y-m-d H:i:s'),
            'threat_score' => $metrics['threat_score'] ?? 0,
            'alerts' => [
                'critical' => $metrics['alerts_critical'] ?? 0,
                'medium' => $metrics['alerts_medium'] ?? 0,
                'low' => $metrics['alerts_low'] ?? 0,
                'info' => $metrics['alerts_info'] ?? 0
            ],
            'events_24h' => $metrics['events_24h'] ?? 0,
            'blocked_threats' => $metrics['blocked_threats'] ?? 0,
            'firewall' => [
                'status' => 'unknown',
                'sessions' => $metrics['sessions'] ?? 0,
                'blocked' => 0,
                'hostname' => '',
                'model' => '',
                'serial' => ''
            ],
            'network' => [
                'total_sessions' => $metrics['sessions'] ?? 0,
                'blocked_ips' => [],
                'top_attacks' => [],
                'dhcp_devices' => array_slice(array_map(fn($d) => $d['hostname'] ?? '', $dhcp['data'] ?? []), 0, 10)
            ],
            'switches' => [
                'total' => $metrics['switches_total'] ?? 0,
                'online' => $metrics['switches_online'] ?? 0,
                'offline' => ($metrics['switches_total'] ?? 0) - ($metrics['switches_online'] ?? 0)
            ],
            'endpoints' => [
                'total' => $metrics['endpoints_total'] ?? 0,
                'protected' => $metrics['endpoints_protected'] ?? 0,
                'at_risk' => ($metrics['endpoints_total'] ?? 0) - ($metrics['endpoints_protected'] ?? 0)
            ],
            'vpn' => [
                'active' => $metrics['vpn_active'] ?? 0,
                'total' => $metrics['vpn_total'] ?? 0
            ],
            'voip' => [
                'registered' => count($registeredPhones),
                'calls_active' => 0,
                'devices' => $voip['devices'] ?? 0,
                'phones' => array_slice(array_map(fn($p) => ['number' => $p['number'], 'name' => $p['name'], 'mac' => $p['mac']], $registeredPhones), 0, 15)
            ],
            'wifi' => [
                'aps' => $metrics['wifi_aps'] ?? 0,
                'clients' => $metrics['wifi_clients'] ?? 0,
                'ssids' => []
            ],
            'ips' => ['signatures' => 0, 'blocked' => 0],
            'webfilter' => ['blocked' => 0, 'categories' => []],
            'antivirus' => ['detections' => 0, 'quarantined' => 0],
            'policies' => [
                'total' => $metrics['policies_count'] ?? 0,
                'active' => 0
            ],
            'addresses' => [
                'total' => $metrics['addresses_count'] ?? 0
            ],
            'interfaces' => array_map(fn($i) => [
                'name' => $i['name'] ?? '',
                'ip' => $i['ip'] ?? '',
                'status' => $i['status'] ?? '',
                'type' => $i['type'] ?? ''
            ], array_slice($interfaces['data'] ?? [], 0, 15)),
            'dhcp' => [
                'leases' => count($dhcp['data'] ?? []),
                'active' => array_slice(array_map(fn($d) => [
                    'ip' => $d['ip'] ?? '',
                    'mac' => $d['mac'] ?? '',
                    'hostname' => $d['hostname'] ?? '',
                    'expire' => $d['expire'] ?? ''
                ], $dhcp['data'] ?? []), 0, 20)
            ],
            'system' => [
                'cpu' => $metrics['cpu'] ?? 0,
                'memory' => $metrics['memory'] ?? 0,
                'uptime' => '',
                'version' => ''
            ]
        ];
        
        echo json_encode($socData);
        break;
        
    case 'soc_history':
        $hours = intval($_GET['hours'] ?? 24);
        $history = loadJson($baseDir . '/data/metrics_history.json');
        $cutoff = strtotime("-{$hours} hours");
        $filtered = array_filter($history, function($m) use ($cutoff) {
            return strtotime($m['timestamp'] ?? '2000-01-01') >= $cutoff;
        });
        echo json_encode(['results' => array_values($filtered)]);
        break;
        
    case 'collector_run':
        include __DIR__ . '/collector.php';
        break;
        
    case 'db_status':
        $files = ['interfaces.json', 'dhcp.json', 'wifi.json', 'vpn.json', 'policies.json', 'addresses.json', 'sessions.json', 'switches.json', 'voip.json', 'metrics_current.json', 'metrics_history.json'];
        $info = [];
        foreach ($files as $file) {
            $path = $baseDir . '/data/' . $file;
            $info[$file] = [
                'exists' => file_exists($path),
                'size' => file_exists($path) ? filesize($path) : 0,
                'modified' => file_exists($path) ? date('Y-m-d H:i:s', filemtime($path)) : null
            ];
        }
        echo json_encode($info);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}