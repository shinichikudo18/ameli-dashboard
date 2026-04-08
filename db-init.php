<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$dbPath = __DIR__ . '/dashboard.db';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Crear tablas si no existen
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_status (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        hostname TEXT,
        model TEXT,
        serial TEXT,
        version TEXT,
        uptime TEXT,
        cpu INTEGER,
        memory INTEGER,
        sessions INTEGER,
        sessions_blocked INTEGER
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_interfaces (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        name TEXT,
        ip TEXT,
        status TEXT,
        type TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_dhcp (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip TEXT,
        mac TEXT,
        hostname TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_wifi (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        aps INTEGER,
        clients INTEGER
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_vpn (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        vpn_name TEXT,
        status TEXT,
        remote_ip TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_policies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        name TEXT,
        srcintf TEXT,
        dstintf TEXT,
        status TEXT,
        schedule TEXT,
        action TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortigate_addresses (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        name TEXT,
        type TEXT,
        subnet TEXT,
        interface TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS fortivoice_phones (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        name TEXT,
        number TEXT,
        mac TEXT,
        status TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS homeassistant_entities (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        entity_id TEXT,
        state TEXT,
        domain TEXT,
        friendly_name TEXT
    )");
    
    $pdo->exec("CREATE TABLE IF NOT EXISTS switches_status (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        switch_name TEXT,
        status TEXT,
        ports INTEGER
    )");
    
    // Tabla de métricas SOC agregadas
    $pdo->exec("CREATE TABLE IF NOT EXISTS soc_metrics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        threat_score INTEGER,
        alerts_critical INTEGER,
        alerts_medium INTEGER,
        alerts_low INTEGER,
        alerts_info INTEGER,
        events_24h INTEGER,
        blocked_threats INTEGER,
        dhcp_devices INTEGER,
        interfaces_count INTEGER,
        voip_registered INTEGER,
        wifi_clients INTEGER,
        wifi_aps INTEGER,
        policies_count INTEGER,
        addresses_count INTEGER,
        cpu INTEGER,
        memory INTEGER,
        sessions INTEGER,
        vpn_active INTEGER,
        vpn_total INTEGER,
        switches_online INTEGER,
        switches_total INTEGER,
        endpoints_protected INTEGER,
        endpoints_total INTEGER
    )");
    
    echo json_encode(['status' => 'ok', 'message' => 'Database initialized']);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}