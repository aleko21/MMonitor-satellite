<?php
$string['pluginname'] = 'MMonitor Satellite';

// Stringhe Dashboard
$string['dashboard_title'] = 'Cruscotto MMonitor';
$string['dashboard_heading'] = 'Stato del Satellite Moodle';
$string['go_to_dashboard'] = 'Vai alla Dashboard Visuale';
$string['task_name'] = 'MMonitor: Generazione Report Stato';

// Impostazioni Generali
$string['general_settings'] = 'Configurazione Generale';
$string['vps_ip'] = 'Whitelist IP Monitoraggio';
$string['vps_ip_desc'] = 'Per sicurezza, inserisci qui l\'indirizzo IP del server che effettua il monitoraggio. Le richieste da altri IP verranno bloccate. <br>Usa <code>0.0.0.0</code> per permettere l\'accesso a qualsiasi IP (es. IP dinamici o reti Docker interne).';
$string['secret_key'] = 'Chiave Segreta (Token)';
$string['secret_key_desc'] = 'Copia questa chiave e inseriscila nel tuo pannello di monitoraggio (Telegraf/Grafana). Chi non possiede questa chiave non può leggere i dati.';
$string['log_retention'] = 'Ritenzione dei Log';
$string['log_retention_desc'] = 'Per quanti giorni conservare lo storico JSON nella cartella moodledata.';

// Opzioni Menu a Tendina
$string['days_1'] = '1 Giorno';
$string['days_3'] = '3 Giorni';
$string['days_7'] = '7 Giorni';
$string['days_14'] = '14 Giorni';
$string['days_30'] = '30 Giorni';

// Impostazioni Avanzate (Calibrazione)
$string['advanced_settings'] = 'Calibrazione Risorse Hardware';
$string['advanced_info'] = '
<div style="background-color: #e7f1ff; border-left: 5px solid #0d6efd; padding: 15px; margin-bottom: 20px;">
    <h4 style="margin-top:0;">🛑 Leggi prima di configurare!</h4>
    <p>MMonitor cerca di rilevare automaticamente le risorse del server. Tuttavia, in base al tuo tipo di hosting, potrebbe "vedere troppo".</p>
    
    <strong>CASO A: VPS Dedicata o Server Fisico (Es. AWS EC2, DigitalOcean, Server in Ufficio)</strong><br>
    Il sistema operativo ha accesso esclusivo all\'hardware. <br>
    ✅ <b>Lascia i campi qui sotto a 0 (o vuoti).</b> Il rilevamento automatico funzionerà perfettamente.
    <hr style="margin: 10px 0; border-color: #b6d4fe;">
    
    <strong>CASO B: Hosting Condiviso (Es. Aruba, SiteGround, cPanel, Plesk)</strong><br>
    Il tuo sito condivide il server con altri 100 clienti. Il sistema operativo vede 64GB di RAM e 10TB di disco, ma il tuo piano ne prevede solo 2GB e 50GB. <br>
    ⚠️ <b>Azione Richiesta:</b> Inserisci qui sotto i limiti esatti del tuo piano commerciale, altrimenti i grafici mostreranno valori errati (es. "1% usato" quando sei in realtà pieno).
    <hr style="margin: 10px 0; border-color: #b6d4fe;">

    <strong>CASO C: Container Docker / Kubernetes</strong><br>
    Solitamente MMonitor rileva i limiti del container (Cgroups). Se però noti che i grafici mostrano la RAM dell\'host fisico invece di quella del container: <br>
    ⚠️ <b>Azione Richiesta:</b> Inserisci manualmente i limiti del container qui sotto.
</div>';

$string['manual_ram_mb'] = 'Override Limite RAM (MB)';
$string['manual_ram_mb_desc'] = 'Imposta questo valore <b>SOLO</b> se rientri nel "CASO B" o "CASO C". <br>Inserisci la quantità di RAM in <b>Megabyte</b> assegnata al tuo account.<br><i>Esempi: 2GB = <code>2048</code>, 4GB = <code>4096</code>, 8GB = <code>8192</code>.</i><br>Lascia <b>0</b> per usare il rilevamento automatico.';

$string['manual_disk_gb'] = 'Override Limite Disco (GB)';
$string['manual_disk_gb_desc'] = 'Imposta questo valore <b>SOLO</b> se hai una quota disco specifica diversa da quella del disco fisico.<br>Inserisci la quantità di spazio in <b>Gigabyte</b>.<br><i>Esempio: Se hai un piano da 250GB, scrivi <code>250</code>.</i><br>Lascia <b>0</b> per usare il rilevamento automatico.';