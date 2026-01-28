<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // 1. Creazione Pagina
    $settings = new admin_settingpage(
        'local_mmonitor', 
        get_string('pluginname', 'local_mmonitor')
    );
    $ADMIN->add('server', $settings);

    // 2. Link alla Dashboard
    // Costruiamo il link HTML usando le stringhe di lingua
    $url = new moodle_url('/local/mmonitor/index.php');
    $label = get_string('go_to_dashboard', 'local_mmonitor');
    $html_link = html_writer::link($url, $label, ['class' => 'btn btn-primary mb-3']);
    
    // Intestazione
    $settings->add(new admin_setting_heading(
        'local_mmonitor/dashboard_hdr',
        get_string('dashboard_title', 'local_mmonitor'),
        $html_link
    ));

    // 3. IP VPS
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/vps_ip',
        get_string('vps_ip', 'local_mmonitor'),
        get_string('vps_ip_desc', 'local_mmonitor'),
        '0.0.0.0'
    ));

    // 4. Chiave Segreta
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/secret_key',
        get_string('secret_key', 'local_mmonitor'),
        get_string('secret_key_desc', 'local_mmonitor'),
        'mmonitor_secret'
    ));

    // 5. Ritenzione Log
    // Definiamo l'array usando le chiavi del file lingua
    $options = [
        7  => get_string('days_7', 'local_mmonitor'),
        14 => get_string('days_14', 'local_mmonitor'),
        30 => get_string('days_30', 'local_mmonitor')
    ];

    $settings->add(new admin_setting_configselect(
        'local_mmonitor/log_retention',
        get_string('log_retention', 'local_mmonitor'),
        get_string('log_retention_desc', 'local_mmonitor'),
        7,
        $options
    ));
}