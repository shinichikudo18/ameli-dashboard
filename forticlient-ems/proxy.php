<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$endpoint = $_GET['endpoint'] ?? 'summary';
$emsBaseUrl = 'https://192.168.140.49/api/v1';
$emsUser = 'Administrator';
$emsPass = 'AdmAgnov!2025';

function emsRequest($baseUrl, $path, $method = 'GET', $payload = null, $headers = array(), $cookieFile = null) {
    $ch = curl_init();
    $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
    $body = null;
    $requestHeaders = $headers;

    if ($payload !== null) {
        $body = json_encode($payload);
        $requestHeaders[] = 'Content-Type: application/json';
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    if ($cookieFile) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    }
    if (strtoupper($method) !== 'GET') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
    }
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    if (!empty($requestHeaders)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return array($httpCode, $response, $error);
}

function emsDecode($response) {
    $decoded = json_decode(is_string($response) ? $response : '', true);
    return is_array($decoded) ? $decoded : array('raw' => $response);
}

function emsOk($decoded) {
    return isset($decoded['result']['retval']) && (int)$decoded['result']['retval'] === 1;
}

function emsNumericTotal($value) {
    if (is_numeric($value)) return (int)$value;
    if (!is_array($value)) return 0;
    $sum = 0;
    foreach ($value as $v) {
        $sum += emsNumericTotal($v);
    }
    return $sum;
}

$cookieFile = tempnam(sys_get_temp_dir(), 'ems_cookie_');
list($loginCode, $loginBody, $loginError) = emsRequest($emsBaseUrl, 'auth/signin', 'POST', array(
    'name' => $emsUser,
    'password' => $emsPass,
), array(), $cookieFile);

if ($loginError) {
    http_response_code(500);
    echo json_encode(array('error' => $loginError));
    @unlink($cookieFile);
    exit;
}

$login = emsDecode($loginBody ?: '');
if ($loginCode !== 200 || !emsOk($login)) {
    http_response_code($loginCode ?: 500);
    echo json_encode(array('error' => $login['result']['message'] ?? 'EMS login failed', 'raw' => $login));
    @unlink($cookieFile);
    exit;
}

$headers = array('Ems-Call-Type: 2');

function emsFetch($baseUrl, $path, $headers, $cookieFile) {
    return emsRequest($baseUrl, $path, 'GET', null, $headers, $cookieFile);
}

switch ($endpoint) {
    case 'license':
        list($code, $body, $error) = emsFetch($emsBaseUrl, 'license/get', $headers, $cookieFile);
        break;
    case 'workgroups':
        list($code, $body, $error) = emsFetch($emsBaseUrl, 'workgroups/index', $headers, $cookieFile);
        break;
    case 'sites':
        list($code, $body, $error) = emsFetch($emsBaseUrl, 'sites/names/index', $headers, $cookieFile);
        break;
    case 'alerts':
        list($code, $body, $error) = emsFetch($emsBaseUrl, 'alerts/count_unread', $headers, $cookieFile);
        break;
    case 'summary':
    default:
        list($alertsCode, $alertsBody, $alertsError) = emsFetch($emsBaseUrl, 'alerts/count_unread', $headers, $cookieFile);
        list($licenseCode, $licenseBody, $licenseError) = emsFetch($emsBaseUrl, 'license/get', $headers, $cookieFile);
        list($groupsCode, $groupsBody, $groupsError) = emsFetch($emsBaseUrl, 'workgroups/index', $headers, $cookieFile);
        list($sitesCode, $sitesBody, $sitesError) = emsFetch($emsBaseUrl, 'sites/names/index', $headers, $cookieFile);
        list($fpCode, $fpBody, $fpError) = emsFetch($emsBaseUrl, 'forti_products/error_count', $headers, $cookieFile);
        list($profilesCode, $profilesBody, $profilesError) = emsFetch($emsBaseUrl, 'profiles/error_count', $headers, $cookieFile);
        list($ztCode, $ztBody, $ztError) = emsFetch($emsBaseUrl, 'tags/zero_trust/error_count', $headers, $cookieFile);
        list($assignedCode, $assignedBody, $assignedError) = emsFetch($emsBaseUrl, 'endpoints/assigned_endpoints', $headers, $cookieFile);
        list($sniCode, $sniBody, $sniError) = emsFetch($emsBaseUrl, 'system/cloud/sni_update_status', $headers, $cookieFile);

        $criticalErrors = array_filter(array($alertsError, $licenseError, $groupsError, $sitesError, $fpError, $profilesError, $ztError, $assignedError, $sniError));
        if (!empty($criticalErrors)) {
            http_response_code(500);
            echo json_encode(array('error' => reset($criticalErrors)));
            @unlink($cookieFile);
            exit;
        }

        $alerts = emsDecode($alertsBody ?: '');
        $license = emsDecode($licenseBody ?: '');
        $groups = emsDecode($groupsBody ?: '');
        $sites = emsDecode($sitesBody ?: '');
        $fp = emsDecode($fpBody ?: '');
        $profiles = emsDecode($profilesBody ?: '');
        $zt = emsDecode($ztBody ?: '');
        $assigned = emsDecode($assignedBody ?: '');
        $sni = emsDecode($sniBody ?: '');

        $groupList = $groups['data'] ?? array();
        $siteList = $sites['data'] ?? array();
        $assignedList = $assigned['data'] ?? array();
        if (!is_array($assignedList)) $assignedList = array();

        $allGroup = null;
        foreach ($groupList as $group) {
            if (($group['full_path'] ?? '') === 'All Groups' || ($group['name'] ?? '') === 'All Groups') {
                $allGroup = $group;
                break;
            }
        }
        if ($allGroup === null && !empty($groupList)) {
            $allGroup = $groupList[0];
        }

        $users = array();
        foreach ($assignedList as $item) {
            foreach (array('username', 'user_name', 'user', 'name') as $k) {
                if (!empty($item[$k])) {
                    $users[(string)$item[$k]] = array('name' => (string)$item[$k]);
                    break;
                }
            }
        }

        $licenseData = $license['data'] ?? array();
        $securityIssues = emsNumericTotal($fp['data'] ?? array()) + emsNumericTotal($profiles['data'] ?? array()) + emsNumericTotal($zt['data'] ?? array());
        $summary = array(
            'site' => $login['data']['site'] ?? 'Default',
            'alerts' => (int)($alerts['data']['count'] ?? 0),
            'devices' => (int)($allGroup['total_devices'] ?? 0),
            'groups' => count($groupList),
            'sites' => count($siteList),
            'epp_used' => (int)($licenseData['used']['epp'] ?? 0),
            'epp_seats' => (int)($licenseData['seats']['epp'] ?? 0),
            'endpoints' => count($assignedList),
            'users' => count($users),
            'security_issues' => $securityIssues
        );

        http_response_code(200);
        echo json_encode(array(
            'result' => array('retval' => 1, 'message' => 'OK'),
            'data' => array(
                'summary' => $summary,
                'license' => $licenseData,
                'workgroups' => $groupList,
                'sites' => $siteList,
                'assigned_endpoints' => $assignedList,
                'users' => array_values($users),
                'security' => array(
                    'forti_products_count' => emsNumericTotal($fp['data'] ?? array()),
                    'profiles_count' => emsNumericTotal($profiles['data'] ?? array()),
                    'zero_trust_count' => emsNumericTotal($zt['data'] ?? array()),
                    'sni_status' => $sni['data']['status'] ?? $sni['data']['message'] ?? ($sni['data'] ?? '-')
                ),
                'alerts' => $alerts['data'] ?? array()
            )
        ));
        @unlink($cookieFile);
        exit;
}

if ($error) {
    http_response_code(500);
    echo json_encode(array('error' => $error));
    @unlink($cookieFile);
    exit;
}

$decoded = emsDecode($body ?: '');
http_response_code($code ?: 200);
echo json_encode($decoded);
@unlink($cookieFile);
