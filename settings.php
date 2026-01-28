<?php
defined('MOODLE_INTERNAL') || die;

if ($hassiteconfig) {
    
    // -------------------------------------------------------------------------
    // 1. LINK DIRETTO NEL MENU (Voce "MMonitor Dashboard")
    // -------------------------------------------------------------------------
    // Questo aggiunge una voce cliccabile direttamente nel menu Server
    $ADMIN->add('server', new admin_externalpage(
        'local_mmonitor_dashboard', // Nome univoco interno
        get_string('pluginname', 'local_mmonitor') . ' - Dashboard', // Etichetta Menu
        new moodle_url('/local/mmonitor/index.php') // Dove porta il link
    ));


    // -------------------------------------------------------------------------
    // 2. PAGINA CONFIGURAZIONE (Voce "MMonitor Settings")
    // -------------------------------------------------------------------------
    $settings = new admin_settingpage(
        'local_mmonitor', 
        get_string('pluginname', 'local_mmonitor') . ' - Configurazione'
    );
    
    // Aggiungiamo la pagina sotto "Server"
    $ADMIN->add('server', $settings);


    // --- PULSANTE DI NAVIGAZIONE INTERNO ---
    // Aggiungiamo un "finto" titolo che in realtà è un pulsante HTML per andare alla Dashboard
    $dashboard_url = new moodle_url('/local/mmonitor/index.php');
    $button_html = html_writer::link($dashboard_url, '<i class="fa fa-tachometer"></i> APRI DASHBOARD LIVE', [
        'class' => 'btn btn-primary btn-lg',
        'style' => 'margin-bottom: 20px; width: 100%; text-align:center; font-weight:bold;'
    ]);

    $settings->add(new admin_setting_heading(
        'local_mmonitor/header_nav',
        '', // Nessun titolo testuale
        $button_html // Mostriamo il pulsante HTML
    ));


    // --- IMPOSTAZIONI ---

    // 1. Secret Key
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/secret_key',
        'Secret Key',
        'Chiave segreta per proteggere l\'accesso esterno ai file JSON. Usa una stringa complessa.',
        '', 
        PARAM_ALPHANUM
    ));

    // 2. VPS IP (Whitelist)
    $settings->add(new admin_setting_configtext(
        'local_mmonitor/vps_ip',
        'IP Autorizzati (Whitelist)',
        'Inserisci gli indirizzi IP autorizzati a scaricare i dati. <strong>Puoi inserirne più di uno separandoli con una virgola</strong> (es: <code>192.168.1.5, 10.0.0.2</code>).<br>Usa <code>0.0.0.0</code> per disabilitare il controllo IP (Sconsigliato).',
        '0.0.0.0',
        PARAM_TEXT
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