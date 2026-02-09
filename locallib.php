<?php
defined('MOODLE_INTERNAL') || die();

class local_mmonitor_helper {

    public static function get_cpu_usage() {
        if (!function_exists('shell_exec')) return 0.0;
        $output = shell_exec('ps ax -o pcpu --no-headers');
        if (empty($output)) return 0.0;
        
        $lines = explode("\n", trim($output));
        $total_cpu = 0.0;
        foreach ($lines as $line) {
            $total_cpu += floatval($line);
        }
        return round($total_cpu, 1);
    }

    public static function get_ram_usage() {
        $manual_limit_mb = (int)get_config('local_mmonitor', 'manual_ram_mb');

        if ($manual_limit_mb > 0) {
            if (!function_exists('shell_exec')) return null;
            // --no-headers fix per evitare errori stringa
            $cmd = 'ps -u $(whoami) -o rss --no-headers | awk \'{sum+=$1} END {print sum/1024}\'';
            $used_mb = (float)shell_exec($cmd);
            $used_mb = round($used_mb, 0);
            
            return ['total' => $manual_limit_mb, 'used' => $used_mb, 'percent' => round(($used_mb / $manual_limit_mb) * 100, 1)];
        }

        if (is_readable('/sys/fs/cgroup/memory/memory.limit_in_bytes')) {
            $limit_bytes = (float)file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
            $usage_bytes = (float)file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');
            if ($limit_bytes > 0 && $limit_bytes < 100000000000000) {
                $limit_mb = round($limit_bytes / 1048576);
                $usage_mb = round($usage_bytes / 1048576);
                return ['total' => $limit_mb, 'used' => $usage_mb, 'percent' => round(($usage_mb / $limit_mb) * 100, 1)];
            }
        }

        if (function_exists('shell_exec')) {
            $output = shell_exec('free -m | grep Mem');
            $parts = preg_split('/\s+/', trim($output));
            if (isset($parts[1]) && isset($parts[2]) && $parts[1] > 0) {
                return ['total' => (int)$parts[1], 'used' => (int)$parts[2], 'percent' => round(($parts[2] / $parts[1]) * 100, 1)];
            }
        }
        return null;
    }

    public static function get_disk_usage() {
        global $CFG;

        $manual_limit_gb = (float)get_config('local_mmonitor', 'manual_disk_gb');
        
        $total_gb = 0; $free_gb = 0; $percent = 0;

        // CASO A: Hosting Condiviso
        if ($manual_limit_gb > 0) {
            $total_gb = $manual_limit_gb;
            $used_bytes = 0;

            if (function_exists('shell_exec')) {
                // Tentativo 1: Quota
                $quota_out = shell_exec("quota -u $(whoami) -w 2>/dev/null");
                if ($quota_out && preg_match_all('/\d+/', $quota_out, $matches)) {
                    foreach ($matches[0] as $num) {
                        if ($num > 0) { $used_bytes = $num * 1024; break; }
                    }
                }

                // Tentativo 2: DU su Home (Safety Check)
                if ($used_bytes == 0) {
                    $path_to_scan = $CFG->dataroot; // Default sicuro
                    
                    // Logica ricostruzione HOME: Accetta solo se inizia per /home/
                    $parts = explode('/', $CFG->dataroot);
                    if (count($parts) > 3 && $parts[1] == 'home') {
                        $path_to_scan = "/{$parts[1]}/{$parts[2]}"; // Es. /home/oidcdcmf
                    }

                    // Eseguiamo DU (Timeout 10 secondi per evitare Error 500)
                    // Usiamo il comando `timeout` di linux se disponibile
                    $cmd = "timeout 10s du -sb " . escapeshellarg($path_to_scan) . " 2>/dev/null";
                    $out = shell_exec($cmd);
                    
                    if ($out) {
                        $parts = preg_split('/\s+/', trim($out));
                        if (isset($parts[0]) && is_numeric($parts[0])) {
                            $used_bytes = (float)$parts[0];
                        }
                    } else {
                        // Se timeout fallisce (o comando non esiste), fallback su dataroot standard
                        // Almeno non crasha il sito
                        $out_fallback = shell_exec("du -sb " . escapeshellarg($CFG->dataroot) . " 2>/dev/null");
                        $parts = preg_split('/\s+/', trim($out_fallback));
                        if (isset($parts[0]) && is_numeric($parts[0])) $used_bytes = (float)$parts[0];
                    }
                }
            }
            
            $used_gb = round($used_bytes / 1073741824, 1);
            $free_gb = $total_gb - $used_gb;
            if ($free_gb < 0) $free_gb = 0;
            if ($total_gb > 0) $percent = round(($used_gb / $total_gb) * 100, 1);

        } else {
            // CASO B: Server Dedicato
            $bytes_free = disk_free_space($CFG->dataroot);
            $bytes_total = disk_total_space($CFG->dataroot);
            $total_gb = round($bytes_total / 1073741824, 1);
            $free_gb = round($bytes_free / 1073741824, 1);
            if ($bytes_total > 0) $percent = round((($bytes_total - $bytes_free) / $bytes_total) * 100, 1);
        }

        return ['total_gb' => $total_gb, 'free_gb' => $free_gb, 'percent' => $percent];
    }
}