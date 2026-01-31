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
        $secret    = get_config('local_mmonitor', 'secret_key');
        $retention = get_config('local_mmonitor', 'log_retention');

        // 2. Metriche Server (CPU Locale, RAM, Load)
        $cpu_local = $this->get_local_cpu_usage();
        $ram_usage = $this->get_local_ram_usage();
        $load_avg  = sys_getloadavg(); 

       // 3. Utenti Concorrenti (ultimi 5 min)
        $fiveminutesago = time() - 300;
        try {
            // MODIFICA: Aggiunto "AND userid > 0" per contare solo chi ha fatto login
            $concurrent_users = $DB->count_records_select('sessions', 'timemodified > ? AND userid > 0', [$fiveminutesago]);
        } catch (\Exception $e) {
            $concurrent_users = -1;
        }

        // 4. Info Core Moodle & Aggiornamenti
        $core_update_msg = null;
        try {
            $pluginman = \core_plugin_manager::instance();
            $updates = $pluginman->get_available_update_info('core');
            if (!empty($updates)) {
                $latest = reset($updates);
                $core_update_msg = $latest->release;
            }
        } catch (\Throwable $e) {
            $core_update_msg = null;
        }

        // 5. Monitoraggio Cron & Disco
        $lastcron = get_config('tool_task', 'lastcronstart');
        $cron_delay = time() - $lastcron;

        $disk_free = disk_free_space($CFG->dataroot);
        $disk_total = disk_total_space($CFG->dataroot);
        $disk_usage_percent = 0;
        if ($disk_total > 0) {
            $disk_usage_percent = round((($disk_total - $disk_free) / $disk_total) * 100, 1);
        }
        $disk_free_gb = round($disk_free / 1073741824, 1);
        $disk_total_gb = round($disk_total / 1073741824, 1);

        // 6. NUOVO: Statistiche Piattaforma (Utenti, Corsi, Categorie)
        // Usiamo count_records che è ottimizzato
        try {
            // Contiamo utenti non cancellati
            $total_users = $DB->count_records('user', ['deleted' => 0]);
            
            // Contiamo i corsi (meno 1 perché il sito stesso è tecnicamente un corso)
            $total_courses = $DB->count_records('course') - 1;
            if ($total_courses < 0) $total_courses = 0;

            // Contiamo le categorie
            $total_categories = $DB->count_records('course_categories');
        } catch (\Exception $e) {
            $total_users = 0;
            $total_courses = 0;
            $total_categories = 0;
        }

        // 7. Raccolta dati Plugin
        $pluginman = \core_plugin_manager::instance();
        $plugins = $pluginman->get_plugins();
        $plugins_report = [];

        foreach ($plugins as $type => $list) {
            foreach ($list as $name => $plugin) {
                $update_info = $plugin->available_updates();
                $is_local = (strpos($plugin->rootdir, '/local/') !== false);
                $is_addon = (!$plugin->is_standard());

                if ($update_info || $is_local || $is_addon) {
                    $plugins_report[] = [
                        'full_name' => $type . '_' . $name,
                        'version'   => $plugin->versiondb,
                        'display'   => $plugin->release,
                        'type'      => $is_local ? 'local' : ($plugin->is_standard() ? 'standard' : 'addon'),
                        'update_available' => $update_info ? $update_info[0]->version : null,
                    ];
                }
            }
        }

        // --- COSTRUZIONE ARRAY DATI COMPLETO ---
        $data = [
            'metadata' => [
                'timestamp' => time(),
                'site_url'  => $CFG->wwwroot,
                'moodle_release' => $CFG->release,
                'core_update_available' => $core_update_msg
            ],
            'server_status' => [
                'cpu_local_percent' => $cpu_local,
                'ram_usage'         => $ram_usage,
                'load_average'      => $load_avg,
                'concurrent_users'  => $concurrent_users,
                'php_version'       => phpversion(),
                'cron_delay_sec'    => $cron_delay,
                'disk_usage'        => [
                    'free_gb' => $disk_free_gb,
                    'total_gb' => $disk_total_gb,
                    'percent' => $disk_usage_percent
                ],
                // NUOVI DATI PIATTAFORMA
                'stats' => [
                    'total_users'      => $total_users,
                    'total_courses'    => $total_courses,
                    'total_categories' => $total_categories
                ]
            ],
            'plugins_report' => $plugins_report
        ];

        // 8. Salvataggio File in MOODLEDATA
        $dir = $CFG->dataroot . '/mmonitor_data';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        $json_content = json_encode($data, JSON_PRETTY_PRINT);
        $filename = "status_{$secret}_" . date('Ymd_Hi') . ".json";
        $latest_file = "latest_{$secret}.json";

        file_put_contents($dir . '/' . $filename, $json_content);
        file_put_contents($dir . '/' . $latest_file, $json_content);

        // 9. Pulizia Log Vecchi
        $files = glob($dir . "/status_{$secret}_*.json");
        foreach ($files as $file) {
            if (time() - filemtime($file) > ($retention * 86400)) {
                unlink($file);
            }
        }
        
        \mtrace("MMonitor Report: CPU {$cpu_local}%, Users {$total_users}, Courses {$total_courses}");
    }

    private function get_local_cpu_usage() {
        if (!function_exists('shell_exec')) return null;
        $output = shell_exec('ps ax -o pcpu --no-headers');
        if (empty($output)) return null;
        $lines = explode("\n", trim($output));
        $total_cpu = 0.0;
        foreach ($lines as $line) {
            $total_cpu += floatval($line);
        }
        return round($total_cpu, 1);
    }

    private function get_local_ram_usage() {
        if (!function_exists('shell_exec')) return null;
        $output = shell_exec('free -m | grep Mem');
        if (empty($output)) return null;
        $parts = preg_split('/\s+/', trim($output));
        if (isset($parts[1]) && isset($parts[2]) && $parts[1] > 0) {
            return [
                'total' => (int)$parts[1],
                'used'  => (int)$parts[2],
                'percent' => round(($parts[2] / $parts[1]) * 100, 1)
            ];
        }
        return null;
    }
}
