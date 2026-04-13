<?php
error_reporting(0);
ini_set('display_errors', 0);

$baseDir = __DIR__;

function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

// Configuración de múltiples FortiGates
$fortigates = [
    'fg-oficina' => ['ip' => '1.2.3.4', 'token' => 'q7N88NNwff4n0d0hs0769Gd03j9gcq', 'name' => 'FG Oficina'],
    'fg-data' => ['ip' => '1.2.3.5', 'token' => 'rzyhGgcHtsst87nr9jtQ3k0rtrcrfn', 'name' => 'FG Data']
];

function fgRequest($fgKey, $endpoint, $params = '') {
    global $fortigates;
    $fg = $fortigates[$fgKey];
    $url = "https://{$fg['ip']}/api/v2/monitor/{$endpoint}?vdom=root{$params}";
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
    $url = "https://{$fg['ip']}/api/v2/cmdb/{$path}?vdom=root";
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
    foreach ($clientList as &$c) { $c['firewall'] = $fg['name']; $c['firewall_key'] = $fgKey; }
    foreach ($apList as &$a) { $a['firewall'] = $fg['name']; $a['firewall_key'] = $fgKey; }
    $allWifiClients = array_merge($allWifiClients, $clientList);
    $allWifiAps = array_merge($allWifiAps, $apList);
    $wifiByFirewall[$fgKey] = ['name' => $fg['name'], 'clients' => count($clientList), 'aps' => count($apList)];
}
saveJson($baseDir . '/data/wifi.json', ['timestamp' => $timestamp, 'clients' => $allWifiClients, 'aps' => $allWifiAps, 'by_firewall' => $wifiByFirewall]);

// VPN - usar cmdb (monitor endpoint no disponible en este FortiGate)
$vpnCmdb = fgRequestCmdb($fgMain, 'vpn.ipsec/phase1-interface');
$vpnData = $vpnCmdb['results'] ?? [];
foreach ($vpnData as &$vpn) {
    $vpn['status'] = (($vpn['auto-negotiate'] ?? '') === 'enable') ? 'up' : 'down';
}
saveJson($baseDir . '/data/vpn.json', ['timestamp' => $timestamp, 'data' => $vpnData]);

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
        foreach ($newSessions as &$s) { $s['firewall'] = $fg['name']; $s['firewall_key'] = $fgKey; }
        $fgSessions = array_merge($fgSessions, $newSessions);
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

// Switches - usar cmdb en lugar de monitor
$switches = fgRequestCmdb($fgMain, 'switch-controller/managed-switch');
$swData = $switches['results'] ?? [];
$swTotal = count($swData);
$swOnline = 0;
foreach ($swData as $s) {
    if (($s['dynamically-discovered'] ?? 0) === 1) $swOnline++;
}
saveJson($baseDir . '/data/switches.json', ['timestamp' => $timestamp, 'total' => $swTotal, 'online' => $swOnline, 'data' => $swData]);

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

// Guardar en historial
$historyFile = $baseDir . '/data/metrics_history.json';
$history = loadJson($historyFile);
$history[] = $metrics;
// Mantener solo últimos 7 días (144 entradas si es cada hora)
$history = array_slice($history, -1008);
saveJson($historyFile, $history);

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
    'threat_score' => min(100, $score)
]);