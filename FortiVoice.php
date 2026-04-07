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

$fvUrl = 'https://192.168.100.6';
$fvUser = 'ameli';
$fvPass = 'AdmAgnov!2025';

function fvRequest($url, $endpoint, $user, $pass, $post = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    if ($post) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));
    }
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $httpCode, 'data' => json_decode($resp, true)];
}

switch ($action) {
    case 'login':
        $result = fvRequest($fvUrl, '/api/v1/VoiceadminLogin', $fvUser, $fvPass, ['username' => $fvUser, 'password' => $fvPass]);
        if ($result['code'] === 200 && isset($result['data']['token'])) {
            echo json_encode(['token' => $result['data']['token'], 'session' => $result['data']['session']]);
        } else {
            http_response_code($result['code'] ?: 401);
            echo json_encode(['error' => 'Login failed']);
        }
        break;

    case 'devices':
    case 'sip_phones':
        $login = fvRequest($fvUrl, '/api/v1/VoiceadminLogin', $fvUser, $fvPass, ['username' => $fvUser, 'password' => $fvPass]);
        if (!isset($login['data']['token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Login failed']);
            exit;
        }
        $token = $login['data']['token'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fvUrl . '/api/v1/DeviceSip_phone');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $token, 'Content-Type: application/json']);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        http_response_code($httpCode);
        echo $resp;
        break;

    case 'licenses':
        $login = fvRequest($fvUrl, '/api/v1/VoiceadminLogin', $fvUser, $fvPass, ['username' => $fvUser, 'password' => $fvPass]);
        if (!isset($login['data']['token'])) {
            http_response_code(401);
            echo json_encode(['error' => 'Login failed']);
            exit;
        }
        $token = $login['data']['token'];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fvUrl . '/api/v1/SysStatusLicinfo');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: ' . $token, 'Content-Type: application/json']);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        http_response_code($httpCode);
        echo $resp;
        break;

    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action', 'available' => ['login', 'devices', 'sip_phones', 'licenses']]);
}