<?php
defined('MOODLE_INTERNAL') || die();

class local_mmonitor_helper {

    /**
     * Calcola l'utilizzo della CPU.
     */
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

    /**
     * Calcola l'utilizzo della RAM.
     * Priorità: 1. Manuale (Settings) -> 2. Cgroups (Docker) -> 3. Standard (Free)
     */
    public static function get_ram_usage() {
        // 1. Hosting Condiviso (Limite Manuale)
        $manual_limit_mb = (int)get_config('local_mmonitor', 'manual_ram_mb');

        if ($manual_limit_mb > 0) {
            if (!function_exists('shell_exec')) return null;
            
            // Calcola somma RSS dei processi dell'utente corrente
            // ps -u $(whoami) prende solo i processi dell'utente web
            $cmd = 'ps -u $(whoami) -o rss | awk \'{sum+=$1} END {print sum/1024}\'';
            $used_mb = (float)shell_exec($cmd);
            $used_mb = round($used_mb, 0);
            
            return [
                'total'   => $manual_limit_mb,
                'used'    => $used_mb,
                'percent' => round(($used_mb / $manual_limit_mb) * 100, 1)
            ];
        }

        // 2. Docker / Container (Cgroups)
        if (is_readable('/sys/fs/cgroup/memory/memory.limit_in_bytes')) {
            $limit_bytes = (float)file_get_contents('/sys/fs/cgroup/memory/memory.limit_in_bytes');
            $usage_bytes = (float)file_get_contents('/sys/fs/cgroup/memory/memory.usage_in_bytes');
            
            // Evitiamo numeri "illimitati" assurdi
            if ($limit_bytes > 0 && $limit_bytes < 100000000000000) {
                $limit_mb = round($limit_bytes / 1048576);
                $usage_mb = round($usage_bytes / 1048576);
                return [
                    'total'   => $limit_mb,
                    'used'    => $usage_mb,
                    'percent' => round(($usage_mb / $limit_mb) * 100, 1)
                ];
            }
        }

        // 3. Server Standard (free -m)
        if (function_exists('shell_exec')) {
            $output = shell_exec('free -m | grep Mem');
            if ($output) {
                $parts = preg_split('/\s+/', trim($output));
                if (isset($parts[1]) && isset($parts[2]) && $parts[1] > 0) {
                    return [
                        'total'   => (int)$parts[1],
                        'used'    => (int)$parts[2],
                        'percent' => round(($parts[2] / $parts[1]) * 100, 1)
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Calcola l'utilizzo del DISCO (Dataroot).
     * Priorità: 1. Manuale (Quota) -> 2. Standard (Filesystem)
     */
    public static function get_disk_usage() {
        global $CFG;

        $manual_limit_gb = (float)get_config('local_mmonitor', 'manual_disk_gb');
        
        $total_gb = 0;
        $free_gb = 0;
        $percent = 0;

        // 1. Hosting Condiviso (Quota Manuale)
        if ($manual_limit_gb > 0) {
            $total_gb = $manual_limit_gb;
            
            $used_bytes = 0;
            if (function_exists('shell_exec')) {
                // du -sb calcola la dimensione reale della cartella
                $out = shell_exec("du -sb " . escapeshellarg($CFG->dataroot) . " 2>/dev/null");
                $used_bytes = (float)intval(preg_split('/\s+/', trim($out))[0]);
            }
            $used_gb = round($used_bytes / 1073741824, 1);
            
            $free_gb = $total_gb - $used_gb;
            if ($free_gb < 0) $free_gb = 0;
            
            if ($total_gb > 0) {
                $percent = round(($used_gb / $total_gb) * 100, 1);
            }

        } else {
            // 2. Server Standard / Dedicato
            $bytes_free = disk_free_space($CFG->dataroot);
            $bytes_total = disk_total_space($CFG->dataroot);
            
            $total_gb = round($bytes_total / 1073741824, 1);
            $free_gb = round($bytes_free / 1073741824, 1);
            
            if ($bytes_total > 0) {
                $percent = round((($bytes_total - $bytes_free) / $bytes_total) * 100, 1);
            }
        }

        return [
            'total_gb' => $total_gb,
            'free_gb'  => $free_gb,
            'percent'  => $percent
        ];
    }
}