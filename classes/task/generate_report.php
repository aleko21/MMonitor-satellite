<?php
namespace local_mmonitor\task;

defined('MOODLE_INTERNAL') || die();

class generate_report extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('task_name', 'local_mmonitor');
    }

    public function execute() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/adminlib.php');

        // 1. Recupero impostazioni
        $vps_ip    = get_config('local_mmonitor', 'vps_ip');
        $secret    = get_config('local_mmonitor', 'secret_key');
        $retention = get_config('local_mmonitor', 'log_retention');

        // 2. Calcolo Utenti Concorrenti (ultimi 5 minuti)
        $fiveminutesago = time() - 300;
        $concurrent_users = $DB->count_records_select('sessions', 'timemodified > ?', [$fiveminutesago]);

        // 3. Raccolta dati Core e Plugin
        $pluginman = \core_plugin_manager::instance();
        $data = [
            'metadata' => [
                'timestamp' => time(),
                'site_url'  => $CFG->wwwroot,
                'moodle_release' => $CFG->release,
            ],
            'server_status' => [
                'load' => sys_getloadavg(),
                'concurrent_users' => $concurrent_users, // NUOVO DATO
            ],
            'plugins_report' => []
        ];

        foreach ($pluginman->get_plugins() as $type => $list) {
            foreach ($list as $name => $plugin) {
                $update_info = $plugin->available_updates();
                $is_local = (strpos($plugin->rootdir, '/local/') !== false);
                $is_addon = (!$plugin->is_standard());

                if ($update_info || $is_local || $is_addon) {
                    $data['plugins_report'][] = [
                        'full_name' => $type . '_' . $name,
                        'version'   => $plugin->versiondb,
                        'display'   => $plugin->displayversion,
                        'update_available' => $update_info ? $update_info[0]->version : null,
                    ];
                }
            }
        }

        // 4. Gestione Cartella e File
        $dir = $CFG->dirroot . '/monitor_data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json_content = json_encode($data, JSON_PRETTY_PRINT);

        // Salvataggio file con TIMESTAMP (per lo storico)
        $filename = "status_{$secret}_" . date('Ymd_Hi') . ".json";
        file_put_contents($dir . '/' . $filename, $json_content);

        // Salvataggio file LATEST (per la VPS)
        // Usiamo un nome che includa comunque il segreto per sicurezza
        $latest_file = "latest_{$secret}.json";
        file_put_contents($dir . '/' . $latest_file, $json_content);

        // 5. Blindaggio .htaccess (copre tutti i .json nella cartella)
        $htaccess = "Options -Indexes\n<Files \"*.json\">\n  Require ip $vps_ip\n</Files>";
        file_put_contents($dir . '/.htaccess', $htaccess);

        // 6. Rotazione log vecchi
        $files = glob($dir . "/status_{$secret}_*.json");
        foreach ($files as $file) {
            if (time() - filemtime($file) > ($retention * 86400)) {
                unlink($file);
            }
        }
        
        \mtrace("MMonitor: Creati " . $filename . " e " . $latest_file . ". Utenti concorrenti: " . $concurrent_users);
    }
}
