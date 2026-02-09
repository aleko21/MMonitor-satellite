<?php
namespace local_mmonitor\task;

defined('MOODLE_INTERNAL') || die();

// Importiamo la libreria locale
require_once($CFG->dirroot . '/local/mmonitor/locallib.php');

class generate_report extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('task_name', 'local_mmonitor');
    }

    public function execute() {
        global $CFG, $DB;
        require_once($CFG->libdir . '/adminlib.php');

        // 1. Configurazione
        $secret    = get_config('local_mmonitor', 'secret_key');
        $retention = get_config('local_mmonitor', 'log_retention');

        // 2. Metriche Hardware (tramite locallib)
        // Nota: Usiamo la backslash \local_mmonitor_helper perché siamo in un namespace
        $cpu_local = \local_mmonitor_helper::get_cpu_usage();
        $ram_data  = \local_mmonitor_helper::get_ram_usage();
        $disk_data = \local_mmonitor_helper::get_disk_usage();
        $load_avg  = sys_getloadavg();

        // 3. Utenti Concorrenti (ultimi 5 min)
        $fiveminutesago = time() - 300;
        try {
            $concurrent_users = $DB->count_records_select('sessions', 'timemodified > ? AND userid > 0', [$fiveminutesago]);
        } catch (\Exception $e) {
            $concurrent_users = -1;
        }

        // 4. Info Core & Aggiornamenti
        $core_update_msg = null;
        
        // Helper per formattazione MAJOR/MINOR
        $format_version_msg = function($new_ver_str) use ($CFG) {
            $curr_parts = explode('.', $CFG->release);
            $curr_branch = (isset($curr_parts[0]) && isset($curr_parts[1])) ? $curr_parts[0] . '.' . $curr_parts[1] : '0.0';
            $new_parts = explode('.', $new_ver_str);
            $new_branch = (isset($new_parts[0]) && isset($new_parts[1])) ? $new_parts[0] . '.' . $new_parts[1] : '0.0';

            if (version_compare($new_branch, $curr_branch, '>')) {
                return "MAJOR: " . $new_ver_str;
            } else {
                return "MINOR: " . $new_ver_str;
            }
        };

        // Logica recupero aggiornamenti
        try {
            $raw_updates = get_config('core', 'available_updates');
            if ($raw_updates) {
                $updates = unserialize($raw_updates);
                if (!empty($updates) && is_array($updates)) {
                    foreach ($updates as $update) {
                        if (isset($update->version)) {
                            $core_update_msg = $format_version_msg($update->release);
                            break; 
                        }
                    }
                }
            }
            
            // Fallback vecchio metodo
            if (empty($core_update_msg)) {
                $raw_response = get_config('core_plugin', 'recentresponse');
                if ($raw_response) {
                    $data = json_decode($raw_response);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        $data = unserialize($raw_response);
                    }
                    if (isset($data->updates->core) && is_array($data->updates->core)) {
                        foreach ($data->updates->core as $update) {
                            if (isset($update->version) && $update->version > $CFG->version) {
                                $core_update_msg = $format_version_msg($update->release);
                                break;
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            $core_update_msg = "Error checking updates";
        }

        // 5. Monitoraggio Cron
        $lastcron = get_config('tool_task', 'lastcronstart');
        $cron_delay = time() - $lastcron;

        // 6. Statistiche Piattaforma
        try {
            $total_users = $DB->count_records('user', ['deleted' => 0]);
            $total_courses = $DB->count_records('course') - 1;
            if ($total_courses < 0) $total_courses = 0;
            $total_categories = $DB->count_records('course_categories');
        } catch (\Exception $e) {
            $total_users = 0; $total_courses = 0; $total_categories = 0;
        }

        // 7. Plugin Report
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

        // 8. Costruzione JSON
        $data = [
            'metadata' => [
                'timestamp' => time(),
                'site_url'  => $CFG->wwwroot,
                'moodle_release' => $CFG->release,
                'core_update_available' => $core_update_msg
            ],
            'server_status' => [
                'cpu_local_percent' => $cpu_local,
                'ram_usage'         => $ram_data,
                'load_average'      => $load_avg,
                'concurrent_users'  => $concurrent_users,
                'php_version'       => phpversion(),
                'cron_delay_sec'    => $cron_delay,
                'disk_usage'        => [
                    'free_gb'  => $disk_data['free_gb'],
                    'total_gb' => $disk_data['total_gb'],
                    'percent'  => $disk_data['percent']
                ],
                'stats' => [
                    'total_users'      => $total_users,
                    'total_courses'    => $total_courses,
                    'total_categories' => $total_categories
                ]
            ],
            'plugins_report' => $plugins_report
        ];

        // 9. Salvataggio
        $dir = $CFG->dataroot . '/mmonitor_data';
        if (!is_dir($dir)) mkdir($dir, 0700, true);

        $json_content = json_encode($data, JSON_PRETTY_PRINT);
        $filename = "status_{$secret}_" . date('Ymd_Hi') . ".json";
        $latest_file = "latest_{$secret}.json";

        file_put_contents($dir . '/' . $filename, $json_content);
        file_put_contents($dir . '/' . $latest_file, $json_content);

        // Pulizia
        $files = glob($dir . "/status_{$secret}_*.json");
        foreach ($files as $file) {
            if (time() - filemtime($file) > ($retention * 86400)) {
                unlink($file);
            }
        }
        
        \mtrace("MMonitor Report Generated. CPU: {$cpu_local}%");
    }
}