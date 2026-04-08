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

// Home Assistant
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
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=wifi/client&start=0&count=100');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;
        
    case 'aps':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=wifi/managed_ap&start=0&count=50');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;
        
    case 'dhcp':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=system/dhcp&start=0&count=100');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;
        
    case 'sessions':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=all&endpoint=firewall/session&start=0&count=2000');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;
        
    case 'switches':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=all&endpoint=switch-controller/managed-switch&start=0&count=50');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;

    case 'sdwan':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=all&endpoint=sdwan');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;

    case 'vpn':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=all&endpoint=vpn');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;

    case 'vpn-users':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/fortigate.php?device=all&endpoint=user/firewall');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;

    case 'fortivoice':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/FortiVoice.php?action=sip_phones');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
        break;

    case 'fortivoice-licenses':
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/dashboard/FortiVoice.php?action=licenses');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $resp = curl_exec($ch);
        curl_close($ch);
        echo $resp;
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
        $socData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'threat_score' => 0,
            'alerts' => ['critical' => 0, 'medium' => 0, 'low' => 0, 'info' => 0],
            'events_24h' => 0,
            'blocked_threats' => 0,
            'firewall' => ['status' => 'unknown', 'sessions' => 0, 'blocked' => 0],
            'network' => ['total_sessions' => 0, 'blocked_ips' => [], 'top_attacks' => []],
            'switches' => ['total' => 0, 'online' => 0, 'offline' => 0],
            'endpoints' => ['total' => 0, 'protected' => 0, 'at_risk' => 0],
            'vpn' => ['active' => 0, 'total' => 0],
            'voip' => ['registered' => 0, 'calls_active' => 0, 'devices' => 0],
            'wifi' => ['aps' => 0, 'clients' => 0],
            'ips' => ['signatures' => 0, 'blocked' => 0],
            'webfilter' => ['blocked' => 0, 'categories' => []],
            'antivirus' => ['detections' => 0, 'quarantined' => 0],
            'policies' => ['total' => 0, 'active' => 0],
            'addresses' => ['total' => 0],
            'interfaces' => [],
            'system' => ['cpu' => 0, 'memory' => 0, 'uptime' => '', 'version' => '']
        ];
        
        $sessionsResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=firewall/session&start=0&count=2000');
        if ($sessionsResp) {
            $sessionsData = json_decode($sessionsResp, true);
            $sessions = $sessionsData['results']['details'] ?? [];
            $socData['firewall']['sessions'] = count($sessions);
            $socData['network']['total_sessions'] = count($sessions);
            
            $blockedCount = 0;
            $srcIps = [];
            $attacks = [];
            foreach ($sessions as $sess) {
                if (isset($sess['action']) && in_array($sess['action'], ['drop', 'blocked'])) {
                    $blockedCount++;
                    if (!empty($sess['srcaddr'])) $srcIps[$sess['srcaddr']] = ($srcIps[$sess['srcaddr']] ?? 0) + 1;
                }
                if (!empty($sess['app'])) $attacks[$sess['app']] = ($attacks[$sess['app']] ?? 0) + 1;
            }
            $socData['firewall']['blocked'] = $blockedCount;
            $socData['blocked_threats'] = $blockedCount;
            arsort($srcIps);
            arsort($attacks);
            $socData['network']['blocked_ips'] = array_slice(array_keys($srcIps), 0, 10);
            $socData['network']['top_attacks'] = array_slice($attacks, 0, 10);
            
            $blockedToday = $blockedCount;
            $socData['alerts']['critical'] = min(3, floor($blockedToday / 10));
            $socData['alerts']['medium'] = min(15, floor($blockedToday / 3));
            $socData['alerts']['low'] = min(20, $blockedToday);
            $socData['alerts']['info'] = $socData['network']['total_sessions'];
            $socData['events_24h'] = $socData['network']['total_sessions'];
        }
        
        $statusResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=system/status');
        if ($statusResp) {
            $statusData = json_decode($statusResp, true);
            $results = $statusData['results'] ?? [];
            if (isset($results[0])) {
                $socData['system']['version'] = $results[0]['version'] ?? '';
                $socData['system']['uptime'] = $results[0]['uptime'] ?? '';
            }
        }
        
        $resourceResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=system/resource');
        if ($resourceResp) {
            $resourceData = json_decode($resourceResp, true);
            $results = $resourceData['results'] ?? [];
            if (isset($results[0])) {
                $socData['system']['cpu'] = $results[0]['cpu'] ?? 0;
                $socData['system']['memory'] = $results[0]['memory'] ?? 0;
            }
        }
        
        $ipsResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=ips/stats');
        if ($ipsResp) {
            $ipsData = json_decode($ipsResp, true);
            $results = $ipsData['results'] ?? [];
            if (isset($results[0])) {
                $socData['ips']['signatures'] = $results[0]['pattern_count'] ?? 0;
                $socData['ips']['blocked'] = $results[0]['detected'] ?? 0;
            }
        }
        
        $wfStatsResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=webfilter/stats');
        if ($wfStatsResp) {
            $wfData = json_decode($wfStatsResp, true);
            $results = $wfData['results'] ?? [];
            if (isset($results[0])) {
                $socData['webfilter']['blocked'] = $results[0]['blocked'] ?? 0;
            }
        }
        
        $avStatsResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=antivirus/stats');
        if ($avStatsResp) {
            $avData = json_decode($avStatsResp, true);
            $results = $avData['results'] ?? [];
            if (isset($results[0])) {
                $socData['antivirus']['detections'] = $results[0]['detected'] ?? 0;
                $socData['antivirus']['quarantined'] = $results[0]['quarantined'] ?? 0;
            }
        }
        
        $addressResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=firewall/address');
        if ($addressResp) {
            $addrData = json_decode($addressResp, true);
            $socData['addresses']['total'] = count($addrData['results'] ?? []);
        }
        
        $policyResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=firewall/policy');
        if ($policyResp) {
            $policyData = json_decode($policyResp, true);
            $policies = $policyData['results'] ?? [];
            $socData['policies']['total'] = count($policies);
            $socData['policies']['active'] = count(array_filter($policies, function($p) { return ($p['status'] ?? '') === 'enable'; }));
        }
        
        $intfResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=system/interface');
        if ($intfResp) {
            $intfData = json_decode($intfResp, true);
            $intfs = $intfData['results'] ?? [];
            $socData['interfaces'] = array_map(function($i) {
                return ['name' => $i['name'] ?? '', 'ip' => $i['ip'] ?? '', 'status' => $i['status'] ?? '', 'type' => $i['type'] ?? ''];
            }, array_slice($intfs, 0, 10));
        }
        
        $switchesResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=all&endpoint=switch-controller/managed-switch&start=0&count=50');
        if ($switchesResp) {
            $swData = json_decode($switchesResp, true);
            $switches = $swData['results'] ?? [];
            $socData['switches']['total'] = count($switches);
            $socData['switches']['online'] = count(array_filter($switches, function($s) { return ($s['state'] ?? '') === 'up'; }));
            $socData['switches']['offline'] = $socData['switches']['total'] - $socData['switches']['online'];
        }
        
        $wifiApResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=wireless-controller/managed-ap');
        if ($wifiApResp) {
            $apData = json_decode($wifiApResp, true);
            $aps = $apData['results'] ?? [];
            $socData['wifi']['aps'] = count($aps);
        }
        
        $wifiClientResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=wifi/client');
        if ($wifiClientResp) {
            $clientData = json_decode($wifiClientResp, true);
            $clients = $clientData['results'] ?? [];
            $socData['wifi']['clients'] = count($clients);
        }
        
        $emsResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=endpoint-control/clients&start=0&count=500');
        if ($emsResp) {
            $emsData = json_decode($emsResp, true);
            $endpoints = $emsData['results'] ?? [];
            $socData['endpoints']['total'] = count($endpoints);
            $socData['endpoints']['protected'] = count(array_filter($endpoints, function($e) { return ($e['registration_state'] ?? '') === 'registered'; }));
            $socData['endpoints']['at_risk'] = count(array_filter($endpoints, function($e) { return ($e['registration_state'] ?? '') !== 'registered'; }));
        }
        
        $vpnResp = @file_get_contents('http://localhost/dashboard/fortigate.php?device=fg-oficina&endpoint=vpn');
        if ($vpnResp) {
            $vpnData = json_decode($vpnResp, true);
            $vpnResults = $vpnData['results'] ?? [];
            $phase1 = $vpnResults[0]['phase1'] ?? [];
            $socData['vpn']['total'] = count($phase1);
            $socData['vpn']['active'] = count(array_filter($phase1, function($p) { return ($p['status'] ?? '') === 'up'; }));
        }
        
        $fvResp = @file_get_contents('http://localhost/dashboard/FortiVoice.php?action=sip_phones');
        if ($fvResp) {
            $fvData = json_decode($fvResp, true);
            $phones = $fvData['results'] ?? [];
            $socData['voip']['devices'] = count($phones);
            $socData['voip']['registered'] = count(array_filter($phones, function($p) { return ($p['registration_state'] ?? '') === 'Registered'; }));
        }
        
        $score = 0;
        if ($socData['firewall']['sessions'] > 0) $score += 15;
        if ($socData['switches']['offline'] === 0 && $socData['switches']['total'] > 0) $score += 10;
        if ($socData['endpoints']['at_risk'] < $socData['endpoints']['total'] * 0.1 || $socData['endpoints']['total'] === 0) $score += 15;
        if ($socData['vpn']['active'] > 0) $score += 10;
        if ($socData['voip']['registered'] > 0) $score += 5;
        if ($socData['alerts']['critical'] < 5) $score += 10;
        if ($socData['wifi']['aps'] > 0) $score += 5;
        if ($socData['policies']['total'] > 0) $score += 10;
        if ($socData['addresses']['total'] > 0) $score += 5;
        if ($socData['system']['cpu'] < 80) $score += 10;
        if ($socData['system']['cpu'] > 0) $score += 5;
        $socData['threat_score'] = min(100, $score);
        
        echo json_encode($socData);
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
}
