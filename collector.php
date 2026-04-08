<?php
error_reporting(0);
ini_set('display_errors', 0);

$baseDir = __DIR__;

// Helper para guardar JSON
function saveJson($file, $data) {
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

function loadJson($file) {
    return file_exists($file) ? json_decode(file_get_contents($file), true) ?? [] : [];
}

// Configuración FortiGate
$fgIp = '1.2.3.4';
$fgToken = 'q7N88NNwff4n0d0hs0769Gd03j9gcq';

function fgRequest($endpoint, $params = '') {
    global $fgIp, $fgToken;
    $url = "https://{$fgIp}/api/v2/monitor/{$endpoint}?vdom=root{$params}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fgToken]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? json_decode($resp, true) : null;
}

function fgRequestCmdb($path) {
    global $fgIp, $fgToken;
    $url = "https://{$fgIp}/api/v2/cmdb/{$path}?vdom=root";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $fgToken]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code === 200 ? json_decode($resp, true) : null;
}

$timestamp = date('Y-m-d H:i:s');

// System Status
$status = fgRequest('system/status');
$hostname = $status['results'][0]['hostname'] ?? '';
$model = $status['results'][0]['model'] ?? '';
$serial = $status['results'][0]['serial'] ?? '';
$version = $status['results'][0]['version'] ?? '';
$uptime = $status['results'][0]['uptime'] ?? '';

// System Resource (CPU/Memory)
$resource = fgRequest('system/resource');
$cpu = $resource['results'][0]['cpu'] ?? 0;
$memory = $resource['results'][0]['memory'] ?? 0;

// Interfaces
$intf = fgRequest('system/interface');
$interfaces = $intf['results'] ?? [];
saveJson($baseDir . '/data/interfaces.json', ['timestamp' => $timestamp, 'data' => $interfaces]);

// DHCP
$dhcp = fgRequest('system/dhcp');
$dhcpData = $dhcp['results'] ?? [];
saveJson($baseDir . '/data/dhcp.json', ['timestamp' => $timestamp, 'data' => $dhcpData]);

// WiFi
$wifiClients = fgRequest('wifi/client');
$wifiAps = fgRequest('wireless-controller/managed-ap');
$clients = count($wifiClients['results'] ?? []);
$aps = count($wifiAps['results'] ?? []);
saveJson($baseDir . '/data/wifi.json', ['timestamp' => $timestamp, 'aps' => $aps, 'clients' => $clients]);

// VPN
$vpn = fgRequest('vpn/ipsec/phase1-interface');
$vpnData = $vpn['results'] ?? [];
saveJson($baseDir . '/data/vpn.json', ['timestamp' => $timestamp, 'data' => $vpnData]);

// Policies
$policy = fgRequestCmdb('firewall/policy');
$policyData = $policy['results'] ?? [];
saveJson($baseDir . '/data/policies.json', ['timestamp' => $timestamp, 'data' => $policyData]);

// Addresses
$addr = fgRequestCmdb('firewall/address');
$addrData = $addr['results'] ?? [];
saveJson($baseDir . '/data/addresses.json', ['timestamp' => $timestamp, 'data' => $addrData]);

// Sessions
$sessions = fgRequest('firewall/session', '&count=2000');
$sessionDetails = $sessions['results']['details'] ?? [];
$totalSessions = count($sessionDetails);
$blockedSessions = 0;
foreach ($sessionDetails as $s) {
    if (in_array($s['action'] ?? '', ['drop', 'blocked'])) $blockedSessions++;
}
saveJson($baseDir . '/data/sessions.json', ['timestamp' => $timestamp, 'total' => $totalSessions, 'blocked' => $blockedSessions]);

// Switches
$switches = fgRequest('switch-controller/managed-switch');
$swData = $switches['results'] ?? [];
$swTotal = count($swData);
$swOnline = 0;
foreach ($swData as $s) {
    if (($s['state'] ?? '') === 'up') $swOnline++;
}
saveJson($baseDir . '/data/switches.json', ['timestamp' => $timestamp, 'total' => $swTotal, 'online' => $swOnline, 'data' => $swData]);

// FortiVoice Phones
$fvUrl = 'http://192.168.100.2';
$fvUser = 'admin';
$fvPass = 'AdmFVOice!2025';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $fvUrl . '/api/v2/phone/device');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, $fvUser . ':' . $fvPass);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
$fvResp = curl_exec($ch);
curl_close($ch);

$phonesData = [];
if ($fvResp) {
    $fvData = json_decode($fvResp, true);
    if (isset($fvData['results'])) {
        foreach ($fvData['results'] as $phone) {
            $accounts = json_decode($phone['accounts'] ?? '[]', true);
            $regStatus = 0;
            $number = '';
            $name = '';
            foreach ($accounts as $acc) {
                if (($acc['registration_status'] ?? 0) === 1) {
                    $regStatus = 1;
                    $number = $acc['associated_number'] ?? '';
                    $name = base64_decode($acc['associated_display_name'] ?? '');
                }
            }
            $phonesData[] = ['name' => $name, 'number' => $number, 'mac' => $phone['mkey'] ?? '', 'status' => $regStatus ? 'registered' : 'unregistered'];
        }
    }
}
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