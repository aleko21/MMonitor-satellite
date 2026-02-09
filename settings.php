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
        // 1. Hosting Condiviso (Limite Manuale)
        $manual_limit_mb = (int)get_config('local_mmonitor', 'manual_ram_mb');

        if ($manual_limit_mb > 0) {
            if (!function_exists('shell_exec')) return null;
            
            // FIX: Aggiunto --no-headers per evitare di sommare la stringa "RSS"
            $cmd = 'ps -u $(whoami) -o rss --no-headers | awk \'{sum+=$1} END {print sum/1024}\'';
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

        // 3. Server Standard
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

    public static function get_disk_usage() {
        global $CFG;

        $manual_limit_gb = (float)get_config('local_mmonitor', 'manual_disk_gb');
        
        $total_gb = 0;
        $free_gb = 0;
        $percent = 0;

        // --- CASO 1: Hosting Condiviso (Quota Manuale Impostata) ---
        if ($manual_limit_gb > 0) {
            $total_gb = $manual_limit_gb;
            $used_bytes = 0;

            if (function_exists('shell_exec')) {
                // TENTATIVO A: Comando QUOTA (Il più preciso per Cpanel/Linux Users)
                // Restituisce usage e limits diretti dal filesystem
                $quota_out = shell_exec("quota -u $(whoami) -w 2>/dev/null");
                
                // Parsing dell'output di quota (varia tra sistemi, ma cerchiamo il primo numero grande)
                // Output tipico: /dev/sda1  123456  250000 ...
                if ($quota_out && preg_match_all('/\d+/', $quota_out, $matches)) {
                    // Di solito il primo numero è "blocchi usati" (1K blocks)
                    // Cerchiamo un numero sensato (maggiore di 0)
                    foreach ($matches[0] as $num) {
                        if ($num > 0) {
                            $used_bytes = $num * 1024; // Convertiamo blocchi in bytes
                            break;
                        }
                    }
                }

                // TENTATIVO B: Fallback su DU (Disk Usage) se Quota fallisce
                if ($used_bytes == 0) {
                    // Usiamo HOME invece di dataroot per catturare anche codice e DB (approssimato)
                    // $home = getenv('HOME') ?: $CFG->dataroot; 
                    // Nota: Usiamo dataroot per sicurezza per evitare timeout su home giganti
                    $path = $CFG->dataroot;
                    
                    // -s (summary), -b (bytes). Redirigiamo stderr per evitare crash su permessi
                    $out = shell_exec("du -sb " . escapeshellarg($path) . " 2>/dev/null");
                    $parts = preg_split('/\s+/', trim($out));
                    if (isset($parts[0]) && is_numeric($parts[0])) {
                        $used_bytes = (float)$parts[0];
                    }
                }
            }
            
            $used_gb = round($used_bytes / 1073741824, 1);
            
            // Calcoli finali
            $free_gb = $total_gb - $used_gb;
            if ($free_gb < 0) $free_gb = 0;
            
            if ($total_gb > 0) {
                $percent = round(($used_gb / $total_gb) * 100, 1);
            }

        } else {
            // --- CASO 2: Server Dedicato / VPS (Standard) ---
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
