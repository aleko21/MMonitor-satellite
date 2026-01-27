<?php
namespace local_mmonitor\task;

defined('MOODLE_INTERNAL') || die();

class generate_report extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('task_name', 'local_mmonitor');
    }

    public function execute() {
        global $CFG;
        require_once($CFG->libdir . '/adminlib.php');

        $vps_ip    = get_config('local_mmonitor', 'vps_ip');
        $secret    = get_config('local_mmonitor', 'secret_key');
        $retention = get_config('local_mmonitor', 'log_retention');

        $pluginman = \core_plugin_manager::instance();
        $plugins = $pluginman->get_plugins();

        $data = [
            'metadata' => [
                'timestamp' => time(),
                'site_url'  => $CFG->wwwroot,
                'moodle_release' => $CFG->release,
            ],
            'server_status' => [
                'load' => sys_getloadavg(),
            ],
            'plugins_report' => []
        ];

        foreach ($plugins as $type => $list) {
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

        $dir = $CFG->dirroot . '/monitor_data';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = "status_{$secret}_" . date('Ymd_Hi') . ".json";
        file_put_contents($dir . '/' . $filename, json_encode($data, JSON_PRETTY_PRINT));

        // Blindaggio .htaccess
        $htaccess = "Options -Indexes\n<Files \"*.json\">\n  Require ip $vps_ip\n</Files>";
        file_put_contents($dir . '/.htaccess', $htaccess);

        // Rotazione log
        $files = glob($dir . "/status_{$secret}_*.json");
        foreach ($files as $file) {
            if (time() - filemtime($file) > ($retention * 86400)) {
                unlink($file);
            }
        }
    }
}