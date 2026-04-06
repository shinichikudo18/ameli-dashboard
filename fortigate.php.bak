<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$apiToken = 'q7N88NNwff4n0d0hs0769Gd03j9gcq';
$vdom = $_GET['vdom'] ?? 'root';
$endpoint = $_GET['endpoint'] ?? 'wifi/client';

$monitorEndpoints = ['wifi/client', 'system/dhcp', 'firewall/session'];
$isMonitor = in_array($endpoint, $monitorEndpoints);

$baseUrl = $isMonitor ? 'https://1.2.3.4/api/v2/monitor' : 'https://1.2.3.4/api/v2/cmdb';
$url = $baseUrl . '/' . $endpoint . '?vdom=' . $vdom;

if (isset($_GET['start'])) $url .= '&start=' . $_GET['start'];
if (isset($_GET['count'])) $url .= '&count=' . $_GET['count'];

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $apiToken
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(500);
    echo json_encode(['error' => $error]);
    exit;
}

http_response_code($httpCode);
echo $response;
