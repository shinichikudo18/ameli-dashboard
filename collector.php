<?php
error_reporting(0);
ini_set('display_errors', 0);

$baseDir = __DIR__;
$historyDir = $baseDir . '/data/history';

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

function saveHistory($dataType, $data, $timestamp) {
    global $historyDir, $baseDir;
    
    if (!is_dir($historyDir)) {
        mkdir($historyDir, 0755, true);
    }
    
    $date = date('Y-m-d', strtotime($timestamp));
    $file = $historyDir . '/' . $dataType . '_' . $date . '.json';
    $hour = date('H', strtotime($timestamp));
    
    $existing = file_exists($file) ? json_decode(file_get_contents($file), true) : [];
    $existing[$hour] = $data;
    
    file_put_contents($file, json_encode($existing, JSON_PRETTY_PRINT));
}

function cleanupHistory($daysToKeep = 30) {
    global $historyDir;
    
    if (!is_dir($historyDir)) return;
    
    $cutoff = strtotime('-' . $daysToKeep . ' days');
    $files = glob($historyDir . '/*.json');
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoff) {
            unlink($file);
        }
    }
}

function getHistory($dataType, $days = 7) {
    global $historyDir;
    $history = [];
    
    for ($i = 0; $i < $days; $i++) {
        $date = date('Y-m-d', strtotime('-' . $i . ' days'));
        $file = $historyDir . '/' . $dataType . '_' . $date . '.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            foreach ($data as $hour => $item) {
                $history[$date . ' ' . $hour . ':00'] = $item;
            }
        }
    }
    
    return $history;
}

// Configuración de múltiples FortiGates
$fortigates = [
    'fg-oficina' => ['ip' => '1.2.3.4', 'port' => 443, 'token' => 'gn73prsykchcqGH3Qjz4Gp0x3rrsqb', 'name' => 'FG Oficina'],
    'fg-data' => ['ip' => '1.2.3.5', 'port' => 443, 'token' => 'rzyhGgcHtsst87nr9jtQ3k0rtrcrfn', 'name' => 'FG Data'],
    'fg-gtd' => ['ip' => '1.2.3.6', 'port' => 10443, 'token' => 'r8845G3tkp1gznGp7761HxhtphxN9p', 'name' => 'FG-GTD']
];

function fgRequest($fgKey, $endpoint, $params = '') {
    global $fortigates;
    $fg = $fortigates[$fgKey];
    $port = $fg['port'] ?? 443;
    $url = "https://{$fg['ip']}:{$port}/api/v2/monitor/{$endpoint}?vdom=root{$params}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fg['token']]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? json_decode($resp, true) : null;
}

function fgRequestCmdb($fgKey, $path) {
    global $fortigates;
    $fg = $fortigates[$fgKey];
    $port = $fg['port'] ?? 443;
    $url = "https://{$fg['ip']}:{$port}/api/v2/cmdb/{$path}?vdom=root";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fg['token']]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? json_decode($resp, true) : null;
}

$timestamp = date('Y-m-d H:i:s');

// Usar fg-oficina como principal para datos básicos
$fgMain = 'fg-oficina';

// System Status
$status = fgRequest($fgMain, 'system/status');
$hostname = $status['results'][0]['hostname'] ?? '';
$model = $status['results'][0]['model'] ?? '';
$serial = $status['results'][0]['serial'] ?? '';
$version = $status['results'][0]['version'] ?? '';
$uptime = $status['results'][0]['uptime'] ?? '';

// System Resource (CPU/Memory)
$resource = fgRequest($fgMain, 'system/resource');
$cpu = $resource['results'][0]['cpu'] ?? 0;
$memory = $resource['results'][0]['memory'] ?? 0;

// Interfaces
$intf = fgRequest($fgMain, 'system/interface');
$interfaces = $intf['results'] ?? [];
saveJson($baseDir . '/data/interfaces.json', ['timestamp' => $timestamp, 'data' => $interfaces]);

// DHCP
$dhcp = fgRequest($fgMain, 'system/dhcp');
$dhcpData = $dhcp['results'] ?? [];
saveJson($baseDir . '/data/dhcp.json', ['timestamp' => $timestamp, 'data' => $dhcpData]);

// WiFi
$wifiClients = fgRequest($fgMain, 'wifi/client');
$wifiAps = fgRequest($fgMain, 'wireless-controller/managed-ap');
$clients = count($wifiClients['results'] ?? []);
$aps = count($wifiAps['results'] ?? []);

// Recolectar WiFi de ambos firewalls
$allWifiClients = [];
$allWifiAps = [];
$wifiByFirewall = [];
foreach ($fortigates as $fgKey => $fg) {
    $fgClients = fgRequest($fgKey, 'wifi/client');
    $fgAps = fgRequest($fgKey, 'wifi/managed_ap');
    $clientList = $fgClients['results'] ?? [];
    $apList = $fgAps['results'] ?? [];
    $fwClients = [];
    foreach ($clientList as $c) { $c['firewall'] = $fg['name']; $c['firewall_key'] = $fgKey; $fwClients[] = $c; }
    $fwAps = [];
    foreach ($apList as $a) { $a['firewall'] = $fg['name']; $a['firewall_key'] = $fgKey; $fwAps[] = $a; }
    $allWifiClients = array_merge($allWifiClients, $fwClients);
    $allWifiAps = array_merge($allWifiAps, $fwAps);
    $wifiByFirewall[$fgKey] = ['name' => $fg['name'], 'clients' => count($fwClients), 'aps' => count($fwAps)];
}
saveJson($baseDir . '/data/wifi.json', ['timestamp' => $timestamp, 'clients' => $allWifiClients, 'aps' => $allWifiAps, 'by_firewall' => $wifiByFirewall]);

// VPN - usar cmdb (monitor endpoint no disponible en este FortiGate)
$vpnCmdb = fgRequestCmdb($fgMain, 'vpn.ipsec/phase1-interface');
$vpnData = $vpnCmdb['results'] ?? [];
$vpnList = [];
foreach ($vpnData as $vpn) {
    $vpn['status'] = (($vpn['auto-negotiate'] ?? '') === 'enable') ? 'up' : 'down';
    $vpnList[] = $vpn;
}
saveJson($baseDir . '/data/vpn.json', ['timestamp' => $timestamp, 'data' => $vpnList]);

// Policies
$policy = fgRequestCmdb($fgMain, 'firewall/policy');
$policyData = $policy['results'] ?? [];
saveJson($baseDir . '/data/policies.json', ['timestamp' => $timestamp, 'data' => $policyData]);

// Addresses
$addr = fgRequestCmdb($fgMain, 'firewall/address');
$addrData = $addr['results'] ?? [];
saveJson($baseDir . '/data/addresses.json', ['timestamp' => $timestamp, 'data' => $addrData]);

// Sessions de AMBOS firewalls - pedir en bloques de 1000
$allSessions = [];
$firewallStats = [];
foreach ($fortigates as $fgKey => $fg) {
    $fgSessions = [];
    for ($start = 0; $start <= 5000; $start += 1000) {
        $sessions = fgRequest($fgKey, 'firewall/session', '&count=1000&start=' . $start);
        $newSessions = $sessions['results']['details'] ?? [];
        if (empty($newSessions)) break;
        $batchSessions = [];
        foreach ($newSessions as $s) { $s['firewall'] = $fg['name']; $s['firewall_key'] = $fgKey; $batchSessions[] = $s; }
        $fgSessions = array_merge($fgSessions, $batchSessions);
        if (count($newSessions) < 1000) break;
    }
    $blocked = 0;
    foreach ($fgSessions as $s) { if (in_array($s['action'] ?? '', ['drop', 'blocked'])) $blocked++; }
    $firewallStats[$fgKey] = ['name' => $fg['name'], 'total' => count($fgSessions), 'blocked' => $blocked];
    $allSessions = array_merge($allSessions, $fgSessions);
}
$totalSessions = count($allSessions);
$blockedSessions = 0;
foreach ($allSessions as $s) { if (in_array($s['action'] ?? '', ['drop', 'blocked'])) $blockedSessions++; }
saveJson($baseDir . '/data/sessions.json', ['timestamp' => $timestamp, 'total' => $totalSessions, 'blocked' => $blockedSessions, 'details' => $allSessions, 'by_firewall' => $firewallStats]);

// Extraer aplicaciones únicas de las sesiones
$appDb = [];
$appNameMap = [
    34527 => ['name' => 'Instagram', 'category' => 'Social.Media', 'risk' => 'medium'],
    47013 => ['name' => 'SSL.TLS', 'category' => 'Security', 'risk' => 'low'],
    41469 => ['name' => 'Microsoft.Portal', 'category' => 'Collaboration', 'risk' => 'low'],
    41468 => ['name' => 'Microsoft.Office365', 'category' => 'Collaboration', 'risk' => 'low'],
    43541 => ['name' => 'Microsoft.Teams', 'category' => 'Collaboration', 'risk' => 'low'],
    39999 => ['name' => 'SSL', 'category' => 'Security', 'risk' => 'low'],
    52452 => ['name' => 'Windows.Notification', 'category' => 'Update', 'risk' => 'low'],
    17405 => ['name' => 'Facebook', 'category' => 'Social.Media', 'risk' => 'medium'],
    50216 => ['name' => 'MQTT', 'category' => 'IoT', 'risk' => 'medium'],
    42533 => ['name' => 'Google', 'category' => 'Search.Engine', 'risk' => 'low'],
    38098 => ['name' => 'Apple', 'category' => 'Update', 'risk' => 'low'],
    28057 => ['name' => 'WhatsApp', 'category' => 'Messaging', 'risk' => 'medium'],
    52618 => ['name' => 'Apple.iCloud', 'category' => 'Cloud', 'risk' => 'low'],
    43540 => ['name' => 'Google.Assistant', 'category' => 'IoT', 'risk' => 'low'],
    24466 => ['name' => 'HTTP', 'category' => 'Web', 'risk' => 'medium'],
    31077 => ['name' => 'YouTube', 'category' => 'Media', 'risk' => 'medium'],
    18155 => ['name' => 'Netflix', 'category' => 'Media', 'risk' => 'medium'],
    16195 => ['name' => 'DNS', 'category' => 'Network', 'risk' => 'low'],
    16270 => ['name' => 'NTP', 'category' => 'Network', 'risk' => 'low'],
    40169 => ['name' => 'HTTP.Proxy', 'category' => 'Proxy', 'risk' => 'medium'],
    41475 => ['name' => 'Microsoft.Authentication', 'category' => 'Authentication', 'risk' => 'low'],
    15832 => ['name' => 'Facebook', 'category' => 'Social.Media', 'risk' => 'medium'],
    16190 => ['name' => 'Spotify', 'category' => 'Media', 'risk' => 'medium'],
    56688 => ['name' => 'SSH', 'category' => 'Remote.Access', 'risk' => 'medium'],
    47816 => ['name' => 'RDP', 'category' => 'Remote.Access', 'risk' => 'medium'],
    33053 => ['name' => 'SMB', 'category' => 'File.Sharing', 'risk' => 'medium'],
    15816 => ['name' => 'Google.Drive', 'category' => 'Cloud', 'risk' => 'low'],
    42662 => ['name' => 'Dropbox', 'category' => 'Cloud', 'risk' => 'medium'],
    55465 => ['name' => 'Slack', 'category' => 'Collaboration', 'risk' => 'low'],
    23382 => ['name' => 'Zoom', 'category' => 'Video.Conf', 'risk' => 'medium'],
    39164 => ['name' => 'Telegram', 'category' => 'Messaging', 'risk' => 'medium'],
    54419 => ['name' => 'Twitter', 'category' => 'Social.Media', 'risk' => 'medium'],
    54381 => ['name' => 'LinkedIn', 'category' => 'Social.Media', 'risk' => 'medium'],
    42537 => ['name' => 'Office365', 'category' => 'Collaboration', 'risk' => 'low'],
    16573 => ['name' => 'Gmail', 'category' => 'Email', 'risk' => 'low'],
    54418 => ['name' => 'OneDrive', 'category' => 'Cloud', 'risk' => 'low'],
    54507 => ['name' => 'Teams', 'category' => 'Collaboration', 'risk' => 'low'],
    39243 => ['name' => 'Salesforce', 'category' => 'Business', 'risk' => 'low'],
    41540 => ['name' => 'AWS', 'category' => 'Cloud', 'risk' => 'medium'],
    43714 => ['name' => 'Azure', 'category' => 'Cloud', 'risk' => 'medium'],
    38900 => ['name' => 'GitHub', 'category' => 'Development', 'risk' => 'low'],
    16035 => ['name' => 'VPN.IPSec', 'category' => 'VPN', 'risk' => 'low'],
    52685 => ['name' => 'FortiClient', 'category' => 'Security', 'risk' => 'low'],
    38131 => ['name' => 'Windows.Update', 'category' => 'Update', 'risk' => 'low'],
    42768 => ['name' => 'Adobe', 'category' => 'Software', 'risk' => 'medium'],
    38924 => ['name' => 'Teamspeak', 'category' => 'VoIP', 'risk' => 'medium'],
    40568 => ['name' => 'WinRM', 'category' => 'Remote.Management', 'risk' => 'high'],
    34640 => ['name' => 'PostgreSQL', 'category' => 'Database', 'risk' => 'high'],
    34789 => ['name' => 'MySQL', 'category' => 'Database', 'risk' => 'high'],
];
foreach ($allSessions as $s) {
    if (!empty($s['apps'])) {
        $app = $s['apps'][0];
        $appId = $app['id'] ?? 0;
        if ($appId > 0 && !isset($appDb[$appId])) {
            $appDb[$appId] = $appNameMap[$appId] ?? ['name' => 'App-' . $appId, 'category' => 'Unknown', 'risk' => 'medium'];
        }
    }
}
saveJson($baseDir . '/data/apps.json', ['timestamp' => $timestamp, 'apps' => $appDb]);

// Switches - obtener de todos los firewalls
$allSwitches = [];
$switchesByFw = [];
foreach ($fortigates as $fgKey => $fg) {
    $switches = fgRequestCmdb($fgKey, 'switch-controller/managed-switch');
    $swData = $switches['results'] ?? [];
    $fwSwitches = [];
    foreach ($swData as $s) {
        $s['firewall'] = $fg['name'];
        $s['firewall_key'] = $fgKey;
        $fwSwitches[] = $s;
    }
    $allSwitches = array_merge($allSwitches, $fwSwitches);
    $switchesByFw[$fgKey] = ['name' => $fg['name'], 'count' => count($fwSwitches)];
}
$swTotal = count($allSwitches);
$swOnline = 0;
foreach ($allSwitches as $s) {
    if (($s['dynamically-discovered'] ?? 0) === 1) $swOnline++;
}
saveJson($baseDir . '/data/switches.json', ['timestamp' => $timestamp, 'total' => $swTotal, 'online' => $swOnline, 'data' => $allSwitches, 'by_firewall' => $switchesByFw]);

// FortiVoice Phones - usar API correcta con login/cookie
$fvUrl = 'http://192.168.100.2';
$fvUser = 'admin';
$fvPass = 'AdmFVOice!2025';

$phonesData = [];
$cookieFile = tempnam(sys_get_temp_dir(), 'fv');

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fvUrl . '/api/v1/VoiceadminLogin/');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['name' => $fvUser, 'password' => $fvPass]));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$loginResp = curl_exec($ch);
$loginCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($loginCode === 200) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $fvUrl . '/api/v1/DeviceSip_phone?reqAction=1&startIndex=0&pageSize=50');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $fvResp = curl_exec($ch);
    curl_close($ch);
    
    if ($fvResp) {
        $fvData = json_decode($fvResp, true);
        if (isset($fvData['data'])) {
            foreach ($fvData['data'] as $phone) {
                $regStatus = ($phone['registration_status'] ?? 0) === 1 ? 'registered' : 'unregistered';
                $phonesData[] = [
                    'name' => $phone['name'] ?? '',
                    'number' => $phone['phone_number'] ?? $phone['associated_number'] ?? '',
                    'mac' => $phone['mkey'] ?? '',
                    'status' => $regStatus,
                    'phone_type' => $phone['phone_type'] ?? ''
                ];
            }
        }
    }
}
@unlink($cookieFile);
$voipRegistered = count(array_filter($phonesData, fn($p) => $p['status'] === 'registered'));
saveJson($baseDir . '/data/voip.json', ['timestamp' => $timestamp, 'devices' => count($phonesData), 'registered' => $voipRegistered, 'phones' => $phonesData]);

// Calcular Threat Score
$score = 0;
if ($totalSessions > 0) $score += 10;
if ($swOnline === $swTotal && $swTotal > 0) $score += 8;
if ($clients > 0) $score += 5;
if (count($policyData) > 0) $score += 8;
if (count($addrData) > 0) $score += 5;
if ($cpu < 80 && $cpu > 0) $score += 8;
if (count($dhcpData) > 0) $score += 5;
if (count($interfaces) > 0) $score += 5;
if (!empty($hostname)) $score += 5;

// Guardar métricas actuales
$metrics = [
    'timestamp' => $timestamp,
    'threat_score' => min(100, $score),
    'alerts_critical' => 0,
    'alerts_medium' => 0,
    'alerts_low' => 0,
    'alerts_info' => $totalSessions,
    'events_24h' => $totalSessions,
    'blocked_threats' => $blockedSessions,
    'dhcp_devices' => count($dhcpData),
    'interfaces_count' => count($interfaces),
    'voip_registered' => $voipRegistered,
    'wifi_clients' => $clients,
    'wifi_aps' => $aps,
    'policies_count' => count($policyData),
    'addresses_count' => count($addrData),
    'cpu' => $cpu,
    'memory' => $memory,
    'sessions' => $totalSessions,
    'vpn_active' => count(array_filter($vpnData, fn($v) => ($v['status'] ?? '') === 'up')),
    'vpn_total' => count($vpnData),
    'switches_online' => $swOnline,
    'switches_total' => $swTotal,
    'endpoints_protected' => 0,
    'endpoints_total' => 0
];
saveJson($baseDir . '/data/metrics_current.json', $metrics);

// Guardar en historial (mantener 30 días)
$historyFile = $baseDir . '/data/metrics_history.json';
$history = loadJson($historyFile);
$history[] = $metrics;
$history = array_slice($history, -4320);
saveJson($historyFile, $history);

// Guardar historial por día para cada tipo de dato
saveHistory('metrics', $metrics, $timestamp);
saveHistory('sessions', ['total' => $totalSessions, 'blocked' => $blockedSessions, 'by_firewall' => $firewallStats], $timestamp);
saveHistory('wifi', ['clients' => $clients, 'aps' => $aps, 'by_firewall' => $wifiByFirewall], $timestamp);
saveHistory('switches', ['total' => $swTotal, 'online' => $swOnline, 'by_firewall' => $switchesByFw], $timestamp);
saveHistory('dhcp', ['count' => count($dhcpData)], $timestamp);
saveHistory('voip', ['devices' => count($phonesData), 'registered' => $voipRegistered], $timestamp);

// Limpiar archivos antiguos (máximo 30 días)
cleanupHistory(30);

echo json_encode([
    'status' => 'ok',
    'timestamp' => $timestamp,
    'hostname' => $hostname,
    'sessions' => $totalSessions,
    'dhcp' => count($dhcpData),
    'interfaces' => count($interfaces),
    'wifi_aps' => $aps,
    'wifi_clients' => $clients,
    'policies' => count($policyData),
    'addresses' => count($addrData),
    'cpu' => $cpu,
    'memory' => $memory,
    'threat_score' => min(100, $score),
    'history_days' => 30
]);