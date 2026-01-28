<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    // Creazione della pagina di impostazioni
    $settings = new admin_settingpage('local_mmonitor', get_string('pluginname', 'local_mmonitor'));
    $ADMIN->add('server', $settings);

    // --- LINK ALLA DASHBOARD ---
    $url = new moodle_url('/local/mmonitor/index.php');
    $link = html_writer::link($url, get_string('go_to_dashboard', 'local_mmonitor'), ['class' => 'btn btn-primary mb-3']);
    
    // FIX: Abbiamo dato un nome reale all'intestazione invece di stringa vuota
    $settings->add(new admin_setting_heading('local_mmonitor/dashboard_hdr', get_string('dashboard', 'local_mmonitor'), $link));

    // --- IMPOSTAZIONI DI SICUREZZA ---
    
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/vps_ip',
        get_string('vps_ip', 'local_mmonitor'),
        get_string('vps_ip_desc', 'local_mmonitor'),
        '0.0.0.0'
    ));

    $settings->add(new admin_setting_configtext(
        'local_mmonitor/secret_key',
        get_string('secret_key', 'local_mmonitor'),
        get_string('secret_key_desc', 'local_mmonitor'),
        'mmonitor_secret'
    ));

    // Definiamo le opzioni prima per chiarezza
    $options = [
        7  => get_string('7days', 'local_mmonitor'),
        14 => get_string('14days', 'local_mmonitor'),
        30 => get_string('30days', 'local_mmonitor')
    ];

    $settings->add(new admin_setting_configselect(
        'local_mmonitor/log_retention',
        get_string('log_retention', 'local_mmonitor'),
        get_string('log_retention_desc', 'local_mmonitor'),
        7,
        $options
    ));
}
