<?php
// Non carichiamo tutta l'interfaccia grafica per velocità, ma solo config
define('AJAX_SCRIPT', true);
require_once('../../config.php');

// 1. Recupero Configurazione
$expected_secret = get_config('local_mmonitor', 'secret_key');
$allowed_raw     = get_config('local_mmonitor', 'vps_ip');

// 2. Elaborazione Lista IP (Multi-IP support)
// Trasformiamo "1.1.1.1, 2.2.2.2" in un array pulito
$allowed_ips = explode(',', $allowed_raw);
$allowed_ips = array_map('trim', $allowed_ips); // Rimuove spazi vuoti extra

// 3. Controllo IP (Il Portinaio)
$request_ip = getremoteaddr(); // IP reale del chiamante

// Logica:
// - Se nella lista c'è "0.0.0.0", entra chiunque.
// - Altrimenti, l'IP richiedente DEVE essere nella lista.
$access_granted = false;

if (in_array('0.0.0.0', $allowed_ips)) {
    $access_granted = true;
} elseif (in_array($request_ip, $allowed_ips)) {
    $access_granted = true;
}

if (!$access_granted) {
    header('HTTP/1.0 403 Forbidden');
    die('Error 403: Unauthorized IP (' . $request_ip . ')');
}

// 4. Controllo Segreto (Passato via GET)
$secret = optional_param('secret', '', PARAM_ALPHANUMEXT);

if ($secret !== $expected_secret) {
    header('HTTP/1.0 401 Unauthorized');
    die('Error 401: Invalid Secret Key');
}

// 5. Recupero e Invio File
$file_path = $CFG->dataroot . '/mmonitor_data/latest_' . $secret . '.json';

if (file_exists($file_path)) {
    // Disabilita cache browser per essere sicuri di vedere il dato fresco
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Content-Type: application/json');
    
    readfile($file_path);
} else {
    header('HTTP/1.0 404 Not Found');
    die('Error 404: Report not generated yet. Run cron.');
}