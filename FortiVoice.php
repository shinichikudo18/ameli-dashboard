<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = $_GET['action'] ?? 'sip_phones';

$fvUrl = 'https://192.168.100.6';
$fvUser = 'ameli';
$fvPass = 'AdmAgnov!2025';

function fv_request($url, $endpoint, $cookieFile, $headers = [], $post = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$code, $resp];
}

function fv_login($fvUrl, $fvUser, $fvPass) {
    $cookieFile = tempnam(sys_get_temp_dir(), 'fvcookie_');
    list($code, $resp) = fv_request(
        $fvUrl,
        '/api/v1/VoiceadminLogin/',
        $cookieFile,
        ['Content-Type: application/json'],
        ['name' => $fvUser, 'password' => $fvPass]
    );

    $data = json_decode($resp, true);
    if ($code >= 200 && $code < 300) {
        return [$data ?: [], $cookieFile];
    }

    @unlink($cookieFile);
    return [null, null];
}

list($login, $cookieFile) = fv_login($fvUrl, $fvUser, $fvPass);
if (!$login || !$cookieFile) {
    http_response_code(502);
    echo json_encode(['error' => 'FortiVoice login failed']);
    exit;
}

switch ($action) {
    case 'login':
        echo json_encode($login);
        break;

    case 'licenses':
        list($code, $resp) = fv_request(
            $fvUrl,
            '/api/v1/SysStatusLicinfo',
            $cookieFile,
            ['Content-Type: application/json'],
            ['reqAction' => 22]
        );
        http_response_code($code ?: 200);
        echo $resp;
        break;

    case 'sip_phones':
    default:
        list($code, $resp) = fv_request(
            $fvUrl,
            '/api/v1/DeviceSip_phone?reqAction=1&mdomain=system&startIndex=0&pageSize=500&extraParam=',
            $cookieFile
        );
        http_response_code($code ?: 200);
        echo $resp;
        break;
}

@unlink($cookieFile);
