<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'dashboard';
$base = 'https://ameli-pbx.agnov.cl:10443';
$user = 'admin';
$pass = 'AdmAgnov!2024';

function fbx_request($url, $cookieFile, $postData = null, $headers = []) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($postData !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}

function fbx_login($base, $user, $pass) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'fbx_');
    $post = http_build_query([
        'username' => $user,
        'password' => $pass,
        'submit' => 'Login'
    ]);
    list($code, $resp) = fbx_request(
        $base . '/admin/config.php',
        $cookieFile,
        $post,
        ['Content-Type: application/x-www-form-urlencoded']
    );
    if ($code >= 200 && $code < 400 && $resp !== false) {
        return $cookieFile;
    }
    @unlink($cookieFile);
    return null;
}

function fbx_fetch($base, $cookieFile, $path, $headers = []) {
    list($code, $resp) = fbx_request($base . $path, $cookieFile, null, $headers);
    return [$code, $resp ?: ''];
}

function fbx_parse_extensions($json, &$techCounts) {
    $items = json_decode($json, true);
    if (!is_array($items)) $items = [];
    $map = [];
    foreach ($items as &$item) {
        $ext = (string)($item['extension'] ?? $item['id'] ?? '');
        $tech = strtolower((string)($item['tech'] ?? 'unknown'));
        if (!isset($techCounts[$tech])) $techCounts[$tech] = 0;
        $techCounts[$tech]++;
        if ($ext !== '') {
            $map[$ext] = $item;
        }
    }
    return [$items, $map];
}

function fbx_parse_trunks($html) {
    $rows = [];
    if (preg_match_all('/<tr id="[^"]*">\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>.*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $rows[] = [
                'name' => html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES),
                'tech' => html_entity_decode(trim(strip_tags($m[2])), ENT_QUOTES),
                'callerid' => html_entity_decode(trim(strip_tags($m[3])), ENT_QUOTES),
                'status' => html_entity_decode(trim(strip_tags($m[4])), ENT_QUOTES),
            ];
        }
    }
    return $rows;
}

function fbx_parse_queues($html) {
    $rows = [];
    if (preg_match_all('/<tr id="[^"]*">\s*<td>(.*?)<\/td>\s*<td>(.*?)<\/td>.*?<\/tr>/si', $html, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $rows[] = [
                'queue' => html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES),
                'description' => html_entity_decode(trim(strip_tags($m[2])), ENT_QUOTES),
            ];
        }
    }
    return $rows;
}

function fbx_parse_asterisk($html) {
    $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
    $peerRows = [];
    $available = 0;
    $unavailable = 0;
    if (preg_match_all('/Endpoint:\s+([0-9]+\/[0-9]+)\s+(.+?)\s+\d+\s+of/mi', $plain, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $endpoint = trim($m[1]);
            $status = trim(preg_replace('/\s+/', ' ', $m[2]));
            $isAvailable = stripos($status, 'Unavailable') === false;
            if ($isAvailable) $available++; else $unavailable++;
            $peerRows[] = [
                'endpoint' => $endpoint,
                'status' => $status,
                'available' => $isAvailable,
            ];
        }
    }
    $ariAvailable = stripos($plain, 'The Asterisk REST Interface is not able to connect') === false;
    $activeCalls = max(0, preg_match_all('/^Channel:/m', $plain, $tmp) - 1);
    return [
        'available' => $available,
        'unavailable' => $unavailable,
        'active_calls' => $activeCalls,
        'ari_available' => $ariAvailable,
        'ari_message' => $ariAvailable ? 'OK' : 'ARI not connected',
        'peers' => $peerRows,
        'raw' => $plain,
    ];
}

$cookieFile = fbx_login($base, $user, $pass);
if (!$cookieFile) {
    http_response_code(502);
    echo json_encode(['error' => 'FreePBX login failed']);
    exit;
}

list($codeExt, $extJson) = fbx_fetch($base, $cookieFile, '/admin/ajax.php?module=core&command=getExtensionGrid&type=all', [
    'X-Requested-With: XMLHttpRequest',
    'Referer: ' . $base . '/admin/config.php?display=extensions',
]);
list($codeTrunk, $trunksHtml) = fbx_fetch($base, $cookieFile, '/admin/config.php?display=trunks');
list($codeQueue, $queuesHtml) = fbx_fetch($base, $cookieFile, '/admin/config.php?display=queues');
list($codeAst, $astHtml) = fbx_fetch($base, $cookieFile, '/admin/config.php?display=asteriskinfo');

$techCounts = [];
list($extensions, $extMap) = fbx_parse_extensions($extJson, $techCounts);
$trunks = fbx_parse_trunks($trunksHtml);
$queues = fbx_parse_queues($queuesHtml);
$asterisk = fbx_parse_asterisk($astHtml);

foreach ($extensions as &$ext) {
    $num = (string)($ext['extension'] ?? $ext['id'] ?? '');
    $peer = $extMap[$num] ?? null;
    if ($peer) {
        $ext['peer_available'] = true;
    }
}

$dashboard = [
    'extensions' => $extensions,
    'trunks' => $trunks,
    'queues' => $queues,
    'peers' => $asterisk['peers'],
    'asterisk' => [
        'ari_available' => $asterisk['ari_available'],
        'ari_message' => $asterisk['ari_message'],
        'active_calls' => $asterisk['active_calls'],
    ],
    'summary' => [
        'extensions_total' => count($extensions),
        'pjsip_extensions' => $techCounts['pjsip'] ?? 0,
        'dahdi_extensions' => $techCounts['dahdi'] ?? 0,
        'iax2_extensions' => $techCounts['iax2'] ?? 0,
        'virtual_extensions' => $techCounts['virtual'] ?? 0,
        'custom_extensions' => $techCounts['custom'] ?? 0,
        'trunks_total' => count($trunks),
        'queues_total' => count($queues),
        'peers_available' => $asterisk['available'],
        'peers_unavailable' => $asterisk['unavailable'],
        'active_calls' => $asterisk['active_calls'],
        'tech_counts' => $techCounts,
    ],
];

echo json_encode($dashboard);
@unlink($cookieFile);
