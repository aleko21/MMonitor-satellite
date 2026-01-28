<?php
// Non carichiamo tutta l'interfaccia grafica, ma solo la configurazione base per velocità
define('AJAX_SCRIPT', true);
require_once('../../config.php');

// 1. Recupero Configurazione
$expected_secret = get_config('local_mmonitor', 'secret_key');
$allowed_ip      = get_config('local_mmonitor', 'vps_ip');

// 2. Controllo IP (Il Portinaio)
$request_ip = getremoteaddr(); // Funzione sicura di Moodle per leggere l'IP reale

// Se l'IP non corrisponde e non è "0.0.0.0" (che significa "tutti ammessi")
if ($allowed_ip !== '0.0.0.0' && $request_ip !== $allowed_ip) {
    header('HTTP/1.0 403 Forbidden');
    die('Error 403: Unauthorized IP (' . $request_ip . ')');
}

// 3. Controllo Segreto (Passato via GET)
$secret = optional_param('secret', '', PARAM_ALPHANUM);

if ($secret !== $expected_secret) {
    header('HTTP/1.0 401 Unauthorized');
    die('Error 401: Invalid Secret Key');
}

// 4. Recupero e Invio File
$file_path = $CFG->dataroot . '/mmonitor_data/latest_' . $secret . '.json';

if (file_exists($file_path)) {
    header('Content-Type: application/json');
    readfile($file_path);
} else {
    header('HTTP/1.0 404 Not Found');
    die('Error 404: Report not generated yet. Run cron.');
}