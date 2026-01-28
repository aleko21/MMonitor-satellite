<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    // Creiamo la pagina delle impostazioni
    $settings = new admin_settingpage('local_mmonitor', get_string('pluginname', 'local_mmonitor'));

    // Posizioniamo il plugin sotto il tab "Server"
    $ADMIN->add('server', $settings);

    // 1. Secret Key
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/secret_key',
        'Secret Key',
        'Chiave segreta per proteggere l\'accesso esterno ai file JSON. Puoi usare lettere, numeri e simboli (es. _ - .).',
        '', 
        PARAM_TEXT // <--- MODIFICA: Ora accetta anche simboli come _
    ));

    // 2. VPS IP (Whitelist) - Supporto Multi-IP
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/vps_ip',
        'IP Autorizzati (Whitelist)',
        'Inserisci gli indirizzi IP autorizzati a scaricare i dati. <strong>Puoi inserirne più di uno separandoli con una virgola</strong> (es: <code>192.168.1.5, 10.0.0.2</code>).<br>Usa <code>0.0.0.0</code> per disabilitare il controllo IP (Sconsigliato).',
        '0.0.0.0',
        PARAM_TEXT // Accetta virgole e spazi
    ));

    // 3. Log Retention
    $settings->add(new admin_setting_configselect(
        'local_mmonitor/log_retention',
        'Ritenzione Log (Giorni)',
        'Per quanti giorni conservare i file JSON storici nel server?',
        7,
        [1 => '1 Giorno', 3 => '3 Giorni', 7 => '7 Giorni', 30 => '30 Giorni']
    ));
}