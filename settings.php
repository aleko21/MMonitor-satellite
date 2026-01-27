<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_mmonitor', get_string('pluginname', 'local_mmonitor'));
    $ADMIN->add('server', $settings);

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

    $settings->add(new admin_setting_configselect(
        'local_mmonitor/log_retention',
        get_string('log_retention', 'local_mmonitor'),
        '',
        7, [7 => '7 giorni', 14 => '14 giorni', 30 => '30 giorni']
    ));
}