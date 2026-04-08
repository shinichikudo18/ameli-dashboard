<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? '';
$baseDir = __DIR__;

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

$haUrl = 'http://192.168.100.3:8123';
$haToken = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiI2NmMwNjBiMDg3YWI0MjhlYTliODg4N2Q5ZWY5ZDQ2NCIsImlhdCI6MTc3NDk4NDg3MSwiZXhwIjoyMDkwMzQ0ODcxfQ.x8K1uTtPvOde_oKXoBf-m70ilAXy-BVW5aAQeqcNeIc';

switch ($action) {
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
        
    case 'clients':
        $wifi = loadJson($baseDir . '/../data/wifi.json');
        echo json_encode(['results' => $wifi['clients'] ?? 0]);
        break;
        
    case 'aps':
        $wifi = loadJson($baseDir . '/../data/wifi.json');
        $intf = loadJson($baseDir . '/../data/interfaces.json');
        $aps = [];
        foreach ($intf['data'] ?? [] as $i) {
            if (strpos($i['name'] ?? '', 'Wifi') !== false || strpos($i['name'] ?? '', 'wifi') !== false) {
                $aps[] = ['name' => $i['name'], 'status' => $i['status'] ?? 'up'];
            }
        }
        echo json_encode(['results' => ['results' => $aps]]);
        break;
        
    case 'dhcp':
        $dhcp = loadJson($baseDir . '/../data/dhcp.json');
        echo json_encode(['results' => $dhcp['data'] ?? []]);
        break;
        
    case 'sessions':
        $sessions = loadJson($baseDir . '/../data/sessions.json');
        echo json_encode(['results' => $sessions['details'] ?? []]);
        break;
        
    case 'switches':
        $sw = loadJson($baseDir . '/../data/switches.json');
        echo json_encode(['results' => $sw['data'] ?? []]);
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}