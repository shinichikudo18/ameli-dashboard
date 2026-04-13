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
        echo json_encode(['results' => $wifi['clients'] ?? []]);
        break;
        
    case 'aps':
        $wifi = loadJson($baseDir . '/data/wifi.json');
        echo json_encode(['results' => $wifi['aps'] ?? []]);
        break;
        
    case 'wifi':
        $wifi = loadJson($baseDir . '/data/wifi.json');
        echo json_encode(['results' => $wifi['clients'] ?? [], 'by_firewall' => $wifi['by_firewall'] ?? []]);
        break;
        
    case 'dhcp':
        $dhcp = loadJson($baseDir . '/data/dhcp.json');
        echo json_encode(['results' => $dhcp['data'] ?? []]);
        break;
        
    case 'sessions':
        $sessions = loadJson($baseDir . '/data/sessions.json');
        $firewall = $_GET['firewall'] ?? '';
        $details = $sessions['details'] ?? [];
        if (!empty($firewall)) {
            $details = array_filter($details, function($s) use ($firewall) {
                return ($s['firewall_key'] ?? '') === $firewall;
            });
            $details = array_values($details);
        }
        
        // Cargar base de datos de aplicaciones
        $appDb = loadJson($baseDir . '/data/apps.json');
        $appsLocal = $appDb['apps'] ?? [];
        
        // Mapas para identificar aplicaciones por puerto
        $portAppMap = [
            53 => ['name' => 'DNS', 'category' => 'Network', 'icon' => '🔍'],
            80 => ['name' => 'HTTP', 'category' => 'Web', 'icon' => '🌐'],
            443 => ['name' => 'HTTPS', 'category' => 'Web', 'icon' => '🔒'],
            22 => ['name' => 'SSH', 'category' => 'Remote', 'icon' => '💻'],
            21 => ['name' => 'FTP', 'category' => 'File', 'icon' => '📁'],
            25 => ['name' => 'SMTP', 'category' => 'Email', 'icon' => '📧'],
            110 => ['name' => 'POP3', 'category' => 'Email', 'icon' => '📧'],
            143 => ['name' => 'IMAP', 'category' => 'Email', 'icon' => '📧'],
            993 => ['name' => 'IMAPS', 'category' => 'Email', 'icon' => '📧'],
            995 => ['name' => 'POP3S', 'category' => 'Email', 'icon' => '📧'],
            587 => ['name' => 'SMTP-TLS', 'category' => 'Email', 'icon' => '📧'],
            123 => ['name' => 'NTP', 'category' => 'Network', 'icon' => '⏰'],
            161 => ['name' => 'SNMP', 'category' => 'Network', 'icon' => '📊'],
            3389 => ['name' => 'RDP', 'category' => 'Remote', 'icon' => '🖥️'],
            3306 => ['name' => 'MySQL', 'category' => 'Database', 'icon' => '🗄️'],
            5432 => ['name' => 'PostgreSQL', 'category' => 'Database', 'icon' => '🗄️'],
            1433 => ['name' => 'MSSQL', 'category' => 'Database', 'icon' => '🗄️'],
            27017 => ['name' => 'MongoDB', 'category' => 'Database', 'icon' => '🗄️'],
            445 => ['name' => 'SMB', 'category' => 'File', 'icon' => '📂'],
            137 => ['name' => 'NetBIOS', 'category' => 'Network', 'icon' => '🌐'],
            138 => ['name' => 'NetBIOS', 'category' => 'Network', 'icon' => '🌐'],
            139 => ['name' => 'NetBIOS', 'category' => 'Network', 'icon' => '🌐'],
            5060 => ['name' => 'SIP', 'category' => 'VoIP', 'icon' => '📞'],
            5061 => ['name' => 'SIPS', 'category' => 'VoIP', 'icon' => '📞'],
            3478 => ['name' => 'STUN', 'category' => 'VoIP', 'icon' => '📞'],
            5000 => ['name' => 'UPnP', 'category' => 'Network', 'icon' => '🔌'],
            1900 => ['name' => 'SSDP', 'category' => 'Network', 'icon' => '🔌'],
            5353 => ['name' => 'mDNS', 'category' => 'Network', 'icon' => '🔌'],
            8080 => ['name' => 'HTTP-Alt', 'category' => 'Proxy', 'icon' => '🌐'],
            3128 => ['name' => 'HTTP-Proxy', 'category' => 'Proxy', 'icon' => '🌐'],
            8888 => ['name' => 'HTTP-Alt', 'category' => 'Proxy', 'icon' => '🌐'],
            5222 => ['name' => 'XMPP', 'category' => 'Messaging', 'icon' => '💬'],
            5223 => ['name' => 'XMPP-SSL', 'category' => 'Messaging', 'icon' => '💬'],
            5190 => ['name' => 'AOL', 'category' => 'Messaging', 'icon' => '💬'],
            1863 => ['name' => 'MSN', 'category' => 'Messaging', 'icon' => '💬'],
            5228 => ['name' => 'Google.Play', 'category' => 'Store', 'icon' => '📱'],
            3478 => ['name' => 'FaceTime', 'category' => 'Video', 'icon' => '📹'],
            8008 => ['name' => 'HTTP-Alt', 'category' => 'Web', 'icon' => '🌐'],
            8443 => ['name' => 'HTTPS-Alt', 'category' => 'Web', 'icon' => '🔒'],
            465 => ['name' => 'SMTPS', 'category' => 'Email', 'icon' => '📧'],
            1883 => ['name' => 'MQTT', 'category' => 'IoT', 'icon' => '📡'],
            8123 => ['name' => 'HomeAssistant', 'category' => 'IoT', 'icon' => '🏠'],
            3260 => ['name' => 'iSCSI', 'category' => 'Storage', 'icon' => '💾'],
            389 => ['name' => 'LDAP', 'category' => 'Directory', 'icon' => '📋'],
            636 => ['name' => 'LDAPS', 'category' => 'Directory', 'icon' => '📋'],
            8006 => ['name' => 'Proxmox', 'category' => 'Virtualization', 'icon' => '🖧'],
            8009 => ['name' => 'AJP', 'category' => 'Web', 'icon' => '🌐'],
            5433 => ['name' => 'PostgreSQL-Alt', 'category' => 'Database', 'icon' => '🗄️'],
            5900 => ['name' => 'VNC', 'category' => 'Remote', 'icon' => '🖥️'],
            853 => ['name' => 'DNS-over-TLS', 'category' => 'Network', 'icon' => '🔒'],
            2082 => ['name' => 'cPanel', 'category' => 'Management', 'icon' => '🖧'],
            2083 => ['name' => 'cPanel-SSL', 'category' => 'Management', 'icon' => '🖧'],
            3000 => ['name' => 'Dev-Server', 'category' => 'Development', 'icon' => '💻'],
            3001 => ['name' => 'Dev-Server', 'category' => 'Development', 'icon' => '💻'],
            8883 => ['name' => 'MQTT-SSL', 'category' => 'IoT', 'icon' => '📡'],
            9090 => ['name' => 'Prometheus', 'category' => 'Monitoring', 'icon' => '📊'],
            9100 => ['name' => 'Printer', 'category' => 'Hardware', 'icon' => '🖨️'],
            631 => ['name' => 'IPP', 'category' => 'Printing', 'icon' => '🖨️'],
            1194 => ['name' => 'OpenVPN', 'category' => 'VPN', 'icon' => '🔐'],
            1723 => ['name' => 'PPTP', 'category' => 'VPN', 'icon' => '🔐'],
            500 => ['name' => 'ISAKMP', 'category' => 'VPN', 'icon' => '🔐'],
            4500 => ['name' => 'IPSec-NAT', 'category' => 'VPN', 'icon' => '🔐'],
            1701 => ['name' => 'L2TP', 'category' => 'VPN', 'icon' => '🔐'],
            9993 => ['name' => 'WireGuard', 'category' => 'VPN', 'icon' => '🔐'],
            51820 => ['name' => 'WireGuard', 'category' => 'VPN', 'icon' => '🔐'],
            8443 => ['name' => 'HTTPS-Alt', 'category' => 'Web', 'icon' => '🔒'],
            10000 => ['name' => 'Webmin', 'category' => 'Management', 'icon' => '🖧'],
            27018 => ['name' => 'MongoDB', 'category' => 'Database', 'icon' => '🗄️'],
            28017 => ['name' => 'MongoDB', 'category' => 'Database', 'icon' => '🗄️'],
            7474 => ['name' => 'Neo4j', 'category' => 'Database', 'icon' => '🗄️'],
            7687 => ['name' => 'Bolt', 'category' => 'Database', 'icon' => '🗄️'],
            9200 => ['name' => 'Elasticsearch', 'category' => 'Search', 'icon' => '🔍'],
            5601 => ['name' => 'Kibana', 'category' => 'Visualization', 'icon' => '📊'],
            9000 => ['name' => 'SonarQube', 'category' => 'DevOps', 'icon' => '🔍'],
            8086 => ['name' => 'InfluxDB', 'category' => 'Metrics', 'icon' => '📊'],
            8126 => ['name' => 'StatsD', 'category' => 'Metrics', 'icon' => '📊'],
            15672 => ['name' => 'RabbitMQ', 'category' => 'Messaging', 'icon' => '🐰'],
            5672 => ['name' => 'AMQP', 'category' => 'Messaging', 'icon' => '🐰'],
            6379 => ['name' => 'Redis', 'category' => 'Cache', 'icon' => '⚡'],
            11211 => ['name' => 'Memcached', 'category' => 'Cache', 'icon' => '💾'],
        ];
        
        foreach ($details as &$s) {
            $appId = 0;
            $appName = '';
            $appCategory = '';
            $dport = $s['dport'] ?? 0;
            $proto = $s['proto'] ?? '';
            $destIp = $s['daddr'] ?? '';
            
            // Primero buscar por app_id si existe
            if (!empty($s['apps'])) {
                $app = $s['apps'][0];
                $appId = $app['id'] ?? 0;
                $appName = $app['name'] ?? '';
            }
            
            if ($appId > 0) {
                // Usar app_id de la base de datos local
                if (isset($appsLocal[$appId])) {
                    $appName = $appsLocal[$appId]['name'];
                    $appCategory = $appsLocal[$appId]['category'];
                } else {
                    $appName = 'App-' . $appId;
                    $appCategory = 'Unknown';
                }
            } else {
                // Usar puerto para identificar
                if ($dport > 0 && isset($portAppMap[$dport])) {
                    $appName = $portAppMap[$dport]['name'];
                    $appCategory = $portAppMap[$dport]['category'];
                } else {
                    $appName = strtoupper($proto) . '/' . $dport;
                    $appCategory = 'Network';
                }
            }
            
            $s['app_name'] = $appName;
            $s['app_category'] = $appCategory;
        }
        echo json_encode([
            'results' => $details,
            'by_firewall' => $sessions['by_firewall'] ?? [],
            'total' => $sessions['total'] ?? 0
        ]);
        break;
        
    case 'apps':
        $apps = loadJson($baseDir . '/data/apps.json');
        echo json_encode(['results' => $apps['apps'] ?? []]);
        break;
        
    case 'switches':
        $switches = loadJson($baseDir . '/data/switches.json');
        $swData = $switches['data'] ?? [];
        $formatted = [];
        foreach ($swData as $sw) {
            $ports = $sw['ports'] ?? [];
            $upPorts = 0;
            $poePorts = 0;
            foreach ($ports as $p) {
                if (($p['status'] ?? '') === 'up') $upPorts++;
                if (($p['poe-status'] ?? '') === 'enable') $poePorts++;
            }
            $formatted[] = [
                'name' => $sw['name'] ?: $sw['switch-id'] ?: 'Unknown',
                'switch-id' => $sw['switch-id'] ?? '',
                'ip' => $sw['ip'] ?? '',
                'status' => ($sw['dynamically-discovered'] ?? 0) === 1 ? 'up' : 'down',
                'ports' => count($ports),
                'ports_up' => $upPorts,
                'ports_poe' => $poePorts,
                'dynamically-discovered' => $sw['dynamically-discovered'] ?? 0
            ];
        }
        echo json_encode(['results' => $formatted]);
        break;

    case 'switch-ports':
        $switchId = $_GET['switch_id'] ?? '';
        $switches = loadJson($baseDir . '/data/switches.json');
        foreach ($switches['data'] ?? [] as $sw) {
            if (($sw['switch-id'] ?? '') === $switchId || $switchId === '') {
                $ports = $sw['ports'] ?? [];
                echo json_encode(['results' => array_map(function($p) {
                    return [
                        'port' => $p['port-name'] ?? '',
                        'status' => $p['status'] ?? 'unknown',
                        'speed' => $p['speed'] ?? 'auto',
                        'poe' => $p['poe-status'] ?? 'unknown',
                        'poe-power' => $p['poe-power'] ?? 0,
                        'vlan' => $p['vlan'] ?? '',
                        'description' => $p['description'] ?? ''
                    ];
                }, $ports)]);
                exit;
            }
        }
        echo json_encode(['results' => [], 'error' => 'Switch no encontrado']);
        break;

    case 'sdwan':
        echo json_encode(['results' => [
            [
                'summary' => ['zones' => 2, 'members' => 4, 'enabled_members' => 4, 'health_checks' => 4, 'services' => 2],
                'members' => [
                    ['name' => 'wan1', 'zone' => 'wan', 'status' => 'enable', 'ip' => '192.169.100.2', 'gateway' => '192.169.100.1', 'type' => 'static'],
                    ['name' => 'wan2', 'zone' => 'wan', 'status' => 'enable', 'ip' => '188.168.0.1', 'gateway' => '188.168.0.2', 'type' => 'static'],
                    ['name' => 'dsl', 'zone' => 'backup', 'status' => 'disable', 'ip' => '0.0.0.0', 'gateway' => '0.0.0.0', 'type' => 'dsl'],
                    ['name' => '4g', 'zone' => 'backup', 'status' => 'enable', 'ip' => '10.0.0.1', 'gateway' => '10.0.0.2', 'type' => '4g']
                ],
                'health_checks' => [
                    ['name' => 'Google-DNS', 'server' => '8.8.8.8', 'protocol' => 'ping', 'interval' => 5, 'sla' => ['latency' => 10, 'jitter' => 5, 'loss' => 0]],
                    ['name' => 'Cloudflare-DNS', 'server' => '1.1.1.1', 'protocol' => 'ping', 'interval' => 5, 'sla' => ['latency' => 12, 'jitter' => 3, 'loss' => 1]]
                ]
            ]
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
                ['name' => 'Agnov', 'status' => 'up', 'remote-gateway' => '0.0.0.0', 'type' => 'ipsec'],
                ['name' => 'SD_Agnov_02', 'status' => 'up', 'remote-gateway' => '0.0.0.0', 'type' => 'ipsec'],
                ['name' => 'fext-ipsec-wlaI', 'status' => 'up', 'remote-gateway' => '0.0.0.0', 'type' => 'ipsec']
            ];
        }
        echo json_encode(['results' => [[
            'summary' => ['ipsec_tunnels' => count(array_filter($vpnData, fn($v) => ($v['type'] ?? '') === 'ipsec')), 'ssl_portals' => 0, 'ssl_pools' => 0, 'health_checks' => 0],
            'phase1' => $vpnData,
            'ssl_settings' => ['port' => 0, 'login' => 'disabled']
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