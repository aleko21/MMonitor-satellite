<?php
defined('MOODLE_INTERNAL') || die();

// --- FIX IMPORTANTE: Dichiariamo CFG globale ---
global $CFG;

if ($hassiteconfig) {

    // 1. Link alla Dashboard
    $dashboard = new admin_externalpage(
        'local_mmonitor_dashboard',
        get_string('dashboard_title', 'local_mmonitor'),
        $CFG->wwwroot . '/local/mmonitor/index.php'
    );
    $ADMIN->add('server', $dashboard);

    // 2. Pagina Settings
    $settings_page = new admin_settingpage(
        'local_mmonitor_settings',
        get_string('pluginname', 'local_mmonitor') . ' (Settings)'
    );

    if ($ADMIN->fulltree) {
        // --- Configurazione Generale ---
        $settings_page->add(new admin_setting_heading('local_mmonitor/general_settings', get_string('general_settings', 'local_mmonitor'), ''));
        $settings_page->add(new admin_setting_configpasswordunmask('local_mmonitor/secret_key', get_string('secret_key', 'local_mmonitor'), get_string('secret_key_desc', 'local_mmonitor'), ''));
        $settings_page->add(new admin_setting_configtext('local_mmonitor/vps_ip', get_string('vps_ip', 'local_mmonitor'), get_string('vps_ip_desc', 'local_mmonitor'), '127.0.0.1', PARAM_TEXT));
        $settings_page->add(new admin_setting_configselect('local_mmonitor/log_retention', get_string('log_retention', 'local_mmonitor'), get_string('log_retention_desc', 'local_mmonitor'), 7, [1=>get_string('days_1', 'local_mmonitor'), 3=>get_string('days_3', 'local_mmonitor'), 7=>get_string('days_7', 'local_mmonitor'), 14=>get_string('days_14', 'local_mmonitor'), 30=>get_string('days_30', 'local_mmonitor')]));

        // --- Configurazione Avanzata ---
        $html_info = get_string('advanced_info', 'local_mmonitor');
        $settings_page->add(new admin_setting_heading('local_mmonitor/advanced_settings', get_string('advanced_settings', 'local_mmonitor'), $html_info));
        $settings_page->add(new admin_setting_configtext('local_mmonitor/manual_ram_mb', get_string('manual_ram_mb', 'local_mmonitor'), get_string('manual_ram_mb_desc', 'local_mmonitor'), 0, PARAM_INT));
        $settings_page->add(new admin_setting_configtext('local_mmonitor/manual_disk_gb', get_string('manual_disk_gb', 'local_mmonitor'), get_string('manual_disk_gb_desc', 'local_mmonitor'), 0, PARAM_INT));
    }

    $ADMIN->add('server', $settings_page);
    $settings = null;
}