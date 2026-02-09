<?php
defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {

    // --- FIX: CONTROLLO DI SICUREZZA ---
    // Se Moodle non ha creato l'oggetto $settings automaticamente, lo creiamo noi manualmente.
    if (!isset($settings)) {
        $settings = new admin_settingpage('local_mmonitor', get_string('pluginname', 'local_mmonitor'));
        // Lo agganciamo alla categoria "Plugin Locali"
        $ADMIN->add('localplugins', $settings);
    }
    // -----------------------------------

    // --- 1. CONFIGURAZIONE GENERALE ---
    $settings->add(new admin_setting_heading(
        'local_mmonitor/general_settings',
        get_string('general_settings', 'local_mmonitor'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_mmonitor/secret_key',
        get_string('secret_key', 'local_mmonitor'),
        get_string('secret_key_desc', 'local_mmonitor'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_mmonitor/vps_ip',
        get_string('vps_ip', 'local_mmonitor'),
        get_string('vps_ip_desc', 'local_mmonitor'),
        '127.0.0.1',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configselect(
        'local_mmonitor/log_retention',
        get_string('log_retention', 'local_mmonitor'),
        get_string('log_retention_desc', 'local_mmonitor'),
        7,
        [
            1  => get_string('days_1', 'local_mmonitor'),
            3  => get_string('days_3', 'local_mmonitor'),
            7  => get_string('days_7', 'local_mmonitor'),
            14 => get_string('days_14', 'local_mmonitor'),
            30 => get_string('days_30', 'local_mmonitor')
        ]
    ));

    // --- 2. CONFIGURAZIONE AVANZATA RISORSE ---
    $settings->add(new admin_setting_heading(
        'local_mmonitor/advanced_settings',
        get_string('advanced_settings', 'local_mmonitor'),
        get_string('advanced_info', 'local_mmonitor') 
    ));

    $settings->add(new admin_setting_configtext(
        'local_mmonitor/manual_ram_mb',
        get_string('manual_ram_mb', 'local_mmonitor'),
        get_string('manual_ram_mb_desc', 'local_mmonitor'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_mmonitor/manual_disk_gb',
        get_string('manual_disk_gb', 'local_mmonitor'),
        get_string('manual_disk_gb_desc', 'local_mmonitor'),
        0,
        PARAM_INT
    ));
}
