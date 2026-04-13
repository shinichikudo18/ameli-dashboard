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

function httpJsonRequest($url, $headers = [], $method = 'GET', $body = null, $timeout = 20) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($method !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp ? json_decode($resp, true) : null, $resp];
}

function wazuhAuthToken() {
    static $token = null;
    static $tokenAt = 0;
    if ($token && (time() - $tokenAt) < 840) {
        return $token;
    }

    $wazuhUrl = 'https://192.168.140.9:55000';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $wazuhUrl . '/security/user/authenticate');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_USERPWD, 'wazuh:wazuh');
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($code === 200 && $resp) {
        $data = json_decode($resp, true);
        $token = $data['data']['token'] ?? null;
        $tokenAt = time();
        return $token;
    }

    return null;
}

function wazuhRequest($path, $token) {
    $wazuhUrl = 'https://192.168.140.9:55000';
    if (empty($token)) {
        return [null, null];
    }
    return httpJsonRequest($wazuhUrl . $path, ['Authorization: Bearer ' . $token], 'GET', null, 20);
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
        
        // Mapas para identificar aplicaciones por puerto con iconos SVG
        $portAppMap = [
            53 => ['name' => 'DNS', 'category' => 'Network'],
            80 => ['name' => 'HTTP', 'category' => 'Web'],
            443 => ['name' => 'HTTPS', 'category' => 'Web'],
            22 => ['name' => 'SSH', 'category' => 'Remote'],
            21 => ['name' => 'FTP', 'category' => 'File'],
            25 => ['name' => 'SMTP', 'category' => 'Email'],
            110 => ['name' => 'POP3', 'category' => 'Email'],
            143 => ['name' => 'IMAP', 'category' => 'Email'],
            993 => ['name' => 'IMAPS', 'category' => 'Email'],
            995 => ['name' => 'POP3S', 'category' => 'Email'],
            587 => ['name' => 'SMTP-TLS', 'category' => 'Email'],
            123 => ['name' => 'NTP', 'category' => 'Network'],
            161 => ['name' => 'SNMP', 'category' => 'Network'],
            3389 => ['name' => 'RDP', 'category' => 'Remote'],
            3306 => ['name' => 'MySQL', 'category' => 'Database'],
            5432 => ['name' => 'PostgreSQL', 'category' => 'Database'],
            1433 => ['name' => 'MSSQL', 'category' => 'Database'],
            27017 => ['name' => 'MongoDB', 'category' => 'Database'],
            445 => ['name' => 'SMB', 'category' => 'File'],
            137 => ['name' => 'NetBIOS', 'category' => 'Network'],
            138 => ['name' => 'NetBIOS', 'category' => 'Network'],
            139 => ['name' => 'NetBIOS', 'category' => 'Network'],
            5060 => ['name' => 'SIP', 'category' => 'VoIP'],
            5061 => ['name' => 'SIPS', 'category' => 'VoIP'],
            3478 => ['name' => 'STUN', 'category' => 'VoIP'],
            5000 => ['name' => 'UPnP', 'category' => 'Network'],
            1900 => ['name' => 'SSDP', 'category' => 'Network'],
            5353 => ['name' => 'mDNS', 'category' => 'Network'],
            8080 => ['name' => 'HTTP-Alt', 'category' => 'Proxy'],
            3128 => ['name' => 'HTTP-Proxy', 'category' => 'Proxy'],
            8888 => ['name' => 'HTTP-Alt', 'category' => 'Proxy'],
            5222 => ['name' => 'XMPP', 'category' => 'Messaging'],
            5223 => ['name' => 'XMPP-SSL', 'category' => 'Messaging'],
            5190 => ['name' => 'AOL', 'category' => 'Messaging'],
            1863 => ['name' => 'MSN', 'category' => 'Messaging'],
            5228 => ['name' => 'Google.Play', 'category' => 'Store'],
            3478 => ['name' => 'FaceTime', 'category' => 'Video'],
            8008 => ['name' => 'HTTP-Alt', 'category' => 'Web'],
            8443 => ['name' => 'HTTPS-Alt', 'category' => 'Web'],
            465 => ['name' => 'SMTPS', 'category' => 'Email'],
            1883 => ['name' => 'MQTT', 'category' => 'IoT'],
            8123 => ['name' => 'HomeAssistant', 'category' => 'IoT'],
            3260 => ['name' => 'iSCSI', 'category' => 'Storage'],
            389 => ['name' => 'LDAP', 'category' => 'Directory'],
            636 => ['name' => 'LDAPS', 'category' => 'Directory'],
            8006 => ['name' => 'Proxmox', 'category' => 'Virtualization'],
            8009 => ['name' => 'AJP', 'category' => 'Web'],
            5433 => ['name' => 'PostgreSQL-Alt', 'category' => 'Database'],
            5900 => ['name' => 'VNC', 'category' => 'Remote'],
            853 => ['name' => 'DNS-over-TLS', 'category' => 'Network'],
            2082 => ['name' => 'cPanel', 'category' => 'Management'],
            2083 => ['name' => 'cPanel-SSL', 'category' => 'Management'],
            3000 => ['name' => 'Dev-Server', 'category' => 'Development'],
            3001 => ['name' => 'Dev-Server', 'category' => 'Development'],
            8883 => ['name' => 'MQTT-SSL', 'category' => 'IoT'],
            9090 => ['name' => 'Prometheus', 'category' => 'Monitoring'],
            9100 => ['name' => 'Printer', 'category' => 'Hardware'],
            631 => ['name' => 'IPP', 'category' => 'Printing'],
            1194 => ['name' => 'OpenVPN', 'category' => 'VPN'],
            1723 => ['name' => 'PPTP', 'category' => 'VPN'],
            500 => ['name' => 'ISAKMP', 'category' => 'VPN'],
            4500 => ['name' => 'IPSec-NAT', 'category' => 'VPN'],
            1701 => ['name' => 'L2TP', 'category' => 'VPN'],
            9993 => ['name' => 'WireGuard', 'category' => 'VPN'],
            51820 => ['name' => 'WireGuard', 'category' => 'VPN'],
            10000 => ['name' => 'Webmin', 'category' => 'Management'],
            27018 => ['name' => 'MongoDB', 'category' => 'Database'],
            28017 => ['name' => 'MongoDB', 'category' => 'Database'],
            7474 => ['name' => 'Neo4j', 'category' => 'Database'],
            7687 => ['name' => 'Bolt', 'category' => 'Database'],
            9200 => ['name' => 'Elasticsearch', 'category' => 'Search'],
            5601 => ['name' => 'Kibana', 'category' => 'Visualization'],
            9000 => ['name' => 'SonarQube', 'category' => 'DevOps'],
            8086 => ['name' => 'InfluxDB', 'category' => 'Metrics'],
            8126 => ['name' => 'StatsD', 'category' => 'Metrics'],
            15672 => ['name' => 'RabbitMQ', 'category' => 'Messaging'],
            5672 => ['name' => 'AMQP', 'category' => 'Messaging'],
            6379 => ['name' => 'Redis', 'category' => 'Cache'],
            11211 => ['name' => 'Memcached', 'category' => 'Cache'],
        ];
        
        // Iconos para aplicaciones conocidas (usando Font Awesome classes para CSS)
        $appIcons = [
            'DNS' => 'fa-server',
            'HTTP' => 'fa-globe',
            'HTTPS' => 'fa-lock',
            'SSH' => 'fa-terminal',
            'FTP' => 'fa-folder',
            'SMTP' => 'fa-envelope',
            'IMAP' => 'fa-envelope-open',
            'NTP' => 'fa-clock',
            'SNMP' => 'fa-chart-line',
            'RDP' => 'fa-desktop',
            'MySQL' => 'fa-database',
            'PostgreSQL' => 'fa-database',
            'MSSQL' => 'fa-database',
            'MongoDB' => 'fa-database',
            'SMB' => 'fa-folder-open',
            'SIP' => 'fa-phone',
            'SIPS' => 'fa-phone',
            'LDAP' => 'fa-address-book',
            'MQTT' => 'fa-broadcast-tower',
            'HomeAssistant' => 'fa-home',
            'iSCSI' => 'fa-hdd',
            'VNC' => 'fa-desktop',
            'OpenVPN' => 'fa-shield-alt',
            'WireGuard' => 'fa-shield-alt',
            'Redis' => 'fa-bolt',
            'Elasticsearch' => 'fa-search',
            'Gmail' => 'fa-envelope',
            'Slack' => 'fa-slack',
            'Teams' => 'fa-users',
            'Microsoft.Teams' => 'fa-users',
            'Zoom' => 'fa-video',
            'Telegram' => 'fa-paper-plane',
            'WhatsApp' => 'fa-whatsapp',
            'Instagram' => 'fa-instagram',
            'Facebook' => 'fa-facebook',
            'Twitter' => 'fa-twitter',
            'YouTube' => 'fa-youtube',
            'Netflix' => 'fa-tv',
            'Spotify' => 'fa-music',
            'Dropbox' => 'fa-cloud',
            'Google' => 'fa-google',
            'Google.Drive' => 'fa-google-drive',
            'Google.Play' => 'fa-google-play',
            'Apple' => 'fa-apple',
            'Apple.iCloud' => 'fa-cloud',
            'Microsoft' => 'fa-microsoft',
            'Microsoft.Office365' => 'fa-file-alt',
            'Microsoft.Portal' => 'fa-globe',
            'Microsoft.Authentication' => 'fa-key',
            'Windows.Notification' => 'fa-bell',
            'GitHub' => 'fa-github',
            'SSL' => 'fa-lock',
            'SSL.TLS' => 'fa-lock',
            'FaceTime' => 'fa-video',
            'XMPP' => 'fa-comment',
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
        $byFirewallRaw = $switches['by_firewall'] ?? [];
        $formatted = [];
        $byFirewallDetailed = [];
        
        foreach ($swData as $sw) {
            $ports = $sw['ports'] ?? [];
            $upPorts = 0;
            $poePorts = 0;
            $rj45Ports = 0;
            $sfpPorts = 0;
            $portsData = [];
            
            foreach ($ports as $p) {
                $portStatus = $p['status'] ?? 'down';
                $mediaType = $p['media-type'] ?? 'RJ45';
                if ($portStatus === 'up') $upPorts++;
                if (($p['poe-status'] ?? '') === 'enable') $poePorts++;
                if ($mediaType === 'RJ45') $rj45Ports++;
                if ($mediaType === 'SFP' || $mediaType === 'SFP+') $sfpPorts++;
                $portsData[] = [
                    'name' => $p['port-name'] ?? '',
                    'status' => $portStatus,
                    'speed' => $p['speed'] ?? 'auto',
                    'poe' => $p['poe-status'] ?? 'disable',
                    'media' => $mediaType,
                    'vlan' => $p['vlan'] ?? '',
                    'description' => $p['description'] ?? ''
                ];
            }
            
            $isOnline = ($sw['dynamically-discovered'] ?? 0) === 1 || ($sw['fsw-wan1-peer'] ?? '') === 'fortilink' || ($sw['fsw-wan1-admin'] ?? '') === 'enable';
            $fwKey = $sw['firewall_key'] ?? '';
            $fwName = $sw['firewall'] ?? 'Unknown';
            
            $portCount = count($ports);
            $model = 'FortiSwitch';
            if ($portCount >= 48) $model = 'FortiSwitch 248E';
            elseif ($portCount >= 24) $model = 'FortiSwitch 424';
            elseif ($portCount >= 16) $model = 'FortiSwitch 116';
            elseif ($portCount >= 8) $model = 'FortiSwitch 108';
            
            $formatted[] = [
                'name' => $sw['name'] ?: $sw['switch-id'] ?: 'Unknown',
                'switch-id' => $sw['switch-id'] ?? '',
                'ip' => $sw['ip'] ?? '',
                'status' => $isOnline ? 'up' : 'down',
                'ports' => $portCount,
                'ports_up' => $upPorts,
                'ports_poe' => $poePorts,
                'ports_rj45' => $rj45Ports,
                'ports_sfp' => $sfpPorts,
                'ports_data' => $portsData,
                'dynamically-discovered' => $sw['dynamically-discovered'] ?? 0,
                'firewall' => $fwName,
                'firewall_key' => $fwKey,
                'serial' => $sw['sn'] ?? $sw['serial'] ?? '',
                'model' => $model
            ];
            
            if (!isset($byFirewallDetailed[$fwKey])) {
                $byFirewallDetailed[$fwKey] = [
                    'name' => $fwName,
                    'key' => $fwKey,
                    'count' => 0,
                    'online' => 0,
                    'offline' => 0,
                    'total_ports' => 0,
                    'ports_up' => 0,
                    'ports_poe' => 0
                ];
            }
            $byFirewallDetailed[$fwKey]['count']++;
            if ($isOnline) $byFirewallDetailed[$fwKey]['online']++;
            else $byFirewallDetailed[$fwKey]['offline']++;
            $byFirewallDetailed[$fwKey]['total_ports'] += count($ports);
            $byFirewallDetailed[$fwKey]['ports_up'] += $upPorts;
            $byFirewallDetailed[$fwKey]['ports_poe'] += $poePorts;
        }
        
        echo json_encode(['results' => $formatted, 'by_firewall' => $byFirewallDetailed]);
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
        $sessions = loadJson($baseDir . '/data/sessions.json');
        $apps = loadJson($baseDir . '/data/apps.json');
        $wazuhToken = wazuhAuthToken();
        $wazuhAgents = [];
        $wazuhAgentsTotal = 0;
        $wazuhAgentsActive = 0;
        $wazuhAgentsDisconnected = 0;
        $wazuhAgentsPending = 0;
        $wazuhAgentsSynced = 0;
        $wazuhAgentsNotSynced = 0;
        $wazuhAgentOS = [];
        $wazuhAgentOSCounts = [];
        $wazuhOutdatedAgents = [];
        $wazuhLogsSummary = [];
        $wazuhLogErrors = 0;
        $wazuhVulnLogs = [];
        $wazuhVulnScannerEvents = 0;
        $wazuhVulnFeedErrors = 0;
        $wazuhVulnInfo = 0;
        $wazuhFimEvents = 0;
        $wazuhFimWarnings = 0;
        $wazuhFimErrors = 0;
        $wazuhFimRecent = [];
        $wazuhUserRiskTotal = 0;
        $wazuhUserRiskPrivileged = 0;
        $wazuhUserRiskLocked = 0;
        $wazuhUserRiskItems = [];
        $wazuhApiOk = false;
        $wazuhClusterRunning = false;
        $wazuhClusterName = '';
        $wazuhManager = [];
        $wazuhRemoted = [];
        $wazuhAnalysisd = [];
        $wazuhSecurity = [];

        if ($wazuhToken) {
            $wazuhApiOk = true;
            list($agentsCode, $agentsResp) = wazuhRequest('/agents?limit=500&offset=0&q=id!=000', $wazuhToken);
            if ($agentsCode === 200 && isset($agentsResp['data']['affected_items'])) {
                $wazuhAgents = $agentsResp['data']['affected_items'];
                $wazuhAgentsTotal = intval($agentsResp['data']['total_affected_items'] ?? count($wazuhAgents));
                foreach ($wazuhAgents as $agent) {
                    $status = strtolower($agent['status'] ?? '');
                    if ($status === 'active') $wazuhAgentsActive++;
                    elseif ($status === 'disconnected') $wazuhAgentsDisconnected++;
                    else $wazuhAgentsPending++;

                    $agentOs = strtolower(trim($agent['os']['name'] ?? $agent['os'] ?? 'unknown'));
                    if ($agentOs === '') $agentOs = 'unknown';
                    if (!isset($wazuhAgentOSCounts[$agentOs])) {
                        $wazuhAgentOSCounts[$agentOs] = 0;
                    }
                    $wazuhAgentOSCounts[$agentOs]++;
                }
            }

            list($agentsSummaryCode, $agentsSummaryResp) = wazuhRequest('/agents/summary/status', $wazuhToken);
            if ($agentsSummaryCode === 200 && isset($agentsSummaryResp['data']['connection'])) {
                $wazuhAgentsActive = intval($agentsSummaryResp['data']['connection']['active'] ?? $wazuhAgentsActive);
                $wazuhAgentsDisconnected = intval($agentsSummaryResp['data']['connection']['disconnected'] ?? $wazuhAgentsDisconnected);
                $wazuhAgentsPending = intval($agentsSummaryResp['data']['connection']['pending'] ?? $wazuhAgentsPending);
                $wazuhAgentsTotal = intval($agentsSummaryResp['data']['connection']['total'] ?? $wazuhAgentsTotal);
                $wazuhAgentsSynced = intval($agentsSummaryResp['data']['configuration']['synced'] ?? 0);
                $wazuhAgentsNotSynced = intval($agentsSummaryResp['data']['configuration']['not_synced'] ?? 0);
            }

            list($agentsOsCode, $agentsOsResp) = wazuhRequest('/agents/summary/os', $wazuhToken);
            if ($agentsOsCode === 200 && isset($agentsOsResp['data']['affected_items'])) {
                $wazuhAgentOS = $agentsOsResp['data']['affected_items'];
            }

            list($outdatedCode, $outdatedResp) = wazuhRequest('/agents/outdated?limit=20', $wazuhToken);
            if ($outdatedCode === 200 && isset($outdatedResp['data']['affected_items'])) {
                $wazuhOutdatedAgents = $outdatedResp['data']['affected_items'];
            }

            list($logsSummaryCode, $logsSummaryResp) = wazuhRequest('/manager/logs/summary', $wazuhToken);
            if ($logsSummaryCode === 200 && isset($logsSummaryResp['data']['affected_items'])) {
                $wazuhLogsSummary = $logsSummaryResp['data']['affected_items'];
                foreach ($wazuhLogsSummary as $item) {
                    $module = array_keys($item)[0] ?? '';
                    $stats = $module ? ($item[$module] ?? []) : [];
                    $wazuhLogErrors += intval($stats['error'] ?? 0);
                }
            }

            list($vulnCode, $vulnResp) = wazuhRequest('/manager/logs?search=vulnerability&limit=25&sort=-timestamp', $wazuhToken);
            if ($vulnCode === 200 && isset($vulnResp['data']['affected_items'])) {
                $wazuhVulnLogs = $vulnResp['data']['affected_items'];
                foreach ($wazuhVulnLogs as $log) {
                    $tag = strtolower($log['tag'] ?? '');
                    $level = strtolower($log['level'] ?? '');
                    if (strpos($tag, 'vulnerability-scanner') !== false) {
                        $wazuhVulnScannerEvents++;
                    }
                    if (strpos($tag, 'content-updater') !== false && $level === 'error') {
                        $wazuhVulnFeedErrors++;
                    }
                    if ($level === 'info') {
                        $wazuhVulnInfo++;
                    }
                }
            }

            list($fimCode, $fimResp) = wazuhRequest('/manager/logs?search=syscheck&limit=50&sort=-timestamp', $wazuhToken);
            if ($fimCode === 200 && isset($fimResp['data']['affected_items'])) {
                $fimLogs = $fimResp['data']['affected_items'];
                foreach ($fimLogs as $log) {
                    $level = strtolower($log['level'] ?? 'info');
                    $tag = strtolower($log['tag'] ?? '');
                    $desc = $log['description'] ?? '';
                    if ($level === 'warning') $wazuhFimWarnings++;
                    elseif ($level === 'error' || $level === 'critical') $wazuhFimErrors++;
                    else $wazuhFimEvents++;
                    if (count($wazuhFimRecent) < 10) {
                        $wazuhFimRecent[] = [
                            'timestamp' => $log['timestamp'] ?? '',
                            'tag' => $log['tag'] ?? '',
                            'level' => $log['level'] ?? '',
                            'description' => $desc
                        ];
                    }
                }
            }

            if (!empty($wazuhAgents)) {
                foreach ($wazuhAgents as $agent) {
                    $agentId = $agent['id'] ?? '';
                    if (!$agentId || $agentId === '000') continue;
                    list($usersCode, $usersResp) = wazuhRequest('/syscollector/' . $agentId . '/users?limit=100', $wazuhToken);
                    if ($usersCode !== 200 || !isset($usersResp['data']['affected_items'])) continue;
                    $agentName = $agent['name'] ?? $agentId;
                    foreach ($usersResp['data']['affected_items'] as $userRow) {
                        $u = $userRow['user'] ?? [];
                        $uname = $u['name'] ?? '';
                        if ($uname === '') continue;
                        $uid = intval($u['id'] ?? -1);
                        $locked = strtolower($u['password_status'] ?? '') === 'locked';
                        $privileged = in_array($uid, [0, 500, 1000], true) || in_array(strtolower($uname), ['root', 'administrator', 'administrador', 'admin'], true) || strpos(strtolower($u['groups'] ?? ''), 'administr') !== false;
                        $wazuhUserRiskTotal++;
                        if ($privileged) $wazuhUserRiskPrivileged++;
                        if ($locked) $wazuhUserRiskLocked++;
                        if (count($wazuhUserRiskItems) < 15 && ($privileged || $locked)) {
                            $wazuhUserRiskItems[] = [
                                'agent' => $agentName,
                                'id' => $agentId,
                                'user' => $uname,
                                'uid' => $uid,
                                'shell' => $u['shell'] ?? '',
                                'status' => $locked ? 'locked' : 'active',
                                'groups' => $u['groups'] ?? '',
                                'password_status' => $u['password_status'] ?? ''
                            ];
                        }
                    }
                }
            }

            list($clusterCode, $clusterResp) = wazuhRequest('/cluster/status', $wazuhToken);
            if ($clusterCode === 200 && isset($clusterResp['data'])) {
                $clusterData = $clusterResp['data'];
                $wazuhClusterRunning = !empty($clusterData['running']) || !empty($clusterData['enabled']) || !empty($clusterData['status']);
                $wazuhClusterName = $clusterData['name'] ?? ($clusterData['cluster_name'] ?? '');
            }

            list($managerCode, $managerResp) = wazuhRequest('/manager/stats', $wazuhToken);
            if ($managerCode === 200 && is_array($managerResp)) {
                $wazuhManager = $managerResp;
            }

            list($remotedCode, $remotedResp) = wazuhRequest('/manager/stats/remoted', $wazuhToken);
            if ($remotedCode === 200 && is_array($remotedResp)) {
                $wazuhRemoted = $remotedResp;
            }

            list($analysisdCode, $analysisdResp) = wazuhRequest('/manager/stats/analysisd', $wazuhToken);
            if ($analysisdCode === 200 && is_array($analysisdResp)) {
                $wazuhAnalysisd = $analysisdResp;
            }

            list($securityCode, $securityResp) = wazuhRequest('/security/users/me', $wazuhToken);
            if ($securityCode === 200 && is_array($securityResp)) {
                $wazuhSecurity = $securityResp;
            }
        }
        
        $registeredPhones = array_filter($voip['phones'] ?? [], fn($p) => $p['status'] === 'registered');
        
        // Procesar sesiones por firewall
        $byFirewall = $sessions['by_firewall'] ?? [];
        $firewallStats = [];
        $totalSessions = 0;
        $totalBlocked = 0;
        foreach ($byFirewall as $key => $data) {
            $totalSessions += $data['total'] ?? 0;
            $totalBlocked += $data['blocked'] ?? 0;
            $firewallStats[] = [
                'name' => $data['name'] ?? $key,
                'key' => $key,
                'sessions' => $data['total'] ?? 0,
                'blocked' => $data['blocked'] ?? 0
            ];
        }
        
        // Procesar aplicaciones desde sesiones
        $sessionDetails = $sessions['details'] ?? [];
        $appCounts = [];
        $appFirewallCounts = [];
        foreach ($sessionDetails as $s) {
            $appId = $s['app_id'] ?? 0;
            $fwName = $s['firewall'] ?? 'Unknown';
            $proto = strtolower($s['protocol'] ?? 'tcp');
            $sport = $s['srcport'] ?? 0;
            $dport = $s['dstport'] ?? 0;
            
            $appName = 'Unknown';
            if ($appId > 0 && isset($apps['apps'][$appId])) {
                $appName = $apps['apps'][$appId]['name'];
            } elseif ($dport === 443 || $sport === 443) {
                $appName = 'HTTPS';
            } elseif ($dport === 80 || $sport === 80) {
                $appName = 'HTTP';
            } elseif ($dport === 53 || $sport === 53) {
                $appName = 'DNS';
            } elseif ($dport === 22 || $sport === 22) {
                $appName = 'SSH';
            } elseif ($dport === 25 || $sport === 25) {
                $appName = 'SMTP';
            } elseif ($dport === 3389 || $sport === 3389) {
                $appName = 'RDP';
            } elseif ($dport === 445 || $sport === 445) {
                $appName = 'SMB';
            } else {
                $appName = $proto . ':' . $dport;
            }
            
            if (!isset($appCounts[$appName])) {
                $appCounts[$appName] = 0;
            }
            $appCounts[$appName]++;
            
            if (!isset($appFirewallCounts[$appName])) {
                $appFirewallCounts[$appName] = [];
            }
            if (!isset($appFirewallCounts[$appName][$fwName])) {
                $appFirewallCounts[$appName][$fwName] = 0;
            }
            $appFirewallCounts[$appName][$fwName]++;
        }
        
        // Ordenar aplicaciones por cantidad
        arsort($appCounts);
        $topApps = array_slice($appCounts, 0, 15, true);
        
        // Preparar datos de aplicaciones
        $appsData = [];
        foreach ($topApps as $appName => $count) {
            $fwBreakdown = $appFirewallCounts[$appName] ?? [];
            $mainFw = array_keys($fwBreakdown)[0] ?? 'N/A';
            $appsData[] = [
                'name' => $appName,
                'count' => $count,
                'percentage' => $totalSessions > 0 ? round(($count / $totalSessions) * 100, 1) : 0,
                'firewall' => $mainFw,
                'firewalls_detail' => $fwBreakdown
            ];
        }
        
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
            'wazuh' => [
                'connected' => $wazuhApiOk,
                'cluster_running' => $wazuhClusterRunning,
                'cluster_name' => $wazuhClusterName,
                'manager' => $wazuhManager,
                'remoted' => $wazuhRemoted,
                'analysisd' => $wazuhAnalysisd,
                'security' => $wazuhSecurity,
            ],
            'wazuh_agents' => [
                'total' => $wazuhAgentsTotal,
                'active' => $wazuhAgentsActive,
                'disconnected' => $wazuhAgentsDisconnected,
                'pending' => $wazuhAgentsPending,
                'synced' => $wazuhAgentsSynced,
                'not_synced' => $wazuhAgentsNotSynced,
                'items' => array_slice(array_map(function($a) {
                    return [
                        'id' => $a['id'] ?? '',
                        'name' => $a['name'] ?? '',
                        'status' => strtolower($a['status'] ?? ''),
                        'ip' => $a['ip'] ?? '',
                        'last_keepalive' => $a['lastKeepAlive'] ?? ($a['last_keep_alive'] ?? ''),
                        'version' => $a['version'] ?? '',
                        'os' => $a['os']['name'] ?? ($a['os'] ?? '')
                    ];
                }, $wazuhAgents), 0, 20)
            ],
            'wazuh_os' => $wazuhAgentOS,
            'wazuh_os_counts' => $wazuhAgentOSCounts,
            'wazuh_outdated' => count($wazuhOutdatedAgents),
            'wazuh_outdated_agents' => array_slice(array_map(function($a) {
                return [
                    'id' => $a['id'] ?? '',
                    'name' => $a['name'] ?? '',
                    'ip' => $a['ip'] ?? '',
                    'manager' => $a['manager'] ?? '',
                    'version' => $a['os']['version'] ?? ''
                ];
            }, $wazuhOutdatedAgents), 0, 10),
            'wazuh_log_errors' => $wazuhLogErrors,
            'wazuh_logs_summary' => $wazuhLogsSummary,
            'wazuh_vuln' => [
                'scanner_events' => $wazuhVulnScannerEvents,
                'feed_errors' => $wazuhVulnFeedErrors,
                'info' => $wazuhVulnInfo,
                'recent' => array_slice(array_map(function($l) {
                    return [
                        'timestamp' => $l['timestamp'] ?? '',
                        'tag' => $l['tag'] ?? '',
                        'level' => $l['level'] ?? '',
                        'description' => $l['description'] ?? ''
                    ];
                }, $wazuhVulnLogs), 0, 10)
            ],
            'wazuh_fim' => [
                'events' => $wazuhFimEvents,
                'warnings' => $wazuhFimWarnings,
                'errors' => $wazuhFimErrors,
                'recent' => $wazuhFimRecent
            ],
            'wazuh_user_risk' => [
                'total' => $wazuhUserRiskTotal,
                'privileged' => $wazuhUserRiskPrivileged,
                'locked' => $wazuhUserRiskLocked,
                'items' => $wazuhUserRiskItems
            ],
            'firewalls' => $firewallStats,
            'firewall' => [
                'status' => 'unknown',
                'sessions' => $totalSessions,
                'blocked' => $totalBlocked,
                'hostname' => '',
                'model' => '',
                'serial' => ''
            ],
            'applications' => $appsData,
            'network' => [
                'total_sessions' => $totalSessions,
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
