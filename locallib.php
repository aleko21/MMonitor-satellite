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
            
            // Somma RSS processi utente (più affidabile per evitare OOM)
            $cmd = 'ps -u $(whoami) -o rss --no-headers | awk \'{sum+=$1} END {print sum/1024}\'';
            $used_mb = (float)shell_exec($cmd);
            $used_mb = round($used_mb, 0);
            
            return [
                'total'   => $manual_limit_mb,
                'used'    => $used_mb,
                'percent' => round(($used_mb / $manual_limit_mb) * 100, 1)
            ];
        }

        // 2. Docker / Container
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
            $parts = preg_split('/\s+/', trim($output));
            if (isset($parts[1]) && isset($parts[2]) && $parts[1] > 0) {
                return [
                    'total'   => (int)$parts[1],
                    'used'    => (int)$parts[2],
                    'percent' => round(($parts[2] / $parts[1]) * 100, 1)
                ];
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

        // --- CASO 1: Hosting Condiviso (Limite Manuale Attivo) ---
        if ($manual_limit_gb > 0) {
            $total_gb = $manual_limit_gb;
            $used_bytes = 0;

            if (function_exists('shell_exec')) {
                // TENTATIVO A: Quota (Fallito nel tuo test, ma lo lasciamo per compatibilità futura)
                $quota_out = shell_exec("quota -u $(whoami) -w 2>/dev/null");
                if ($quota_out && preg_match_all('/\d+/', $quota_out, $matches)) {
                    foreach ($matches[0] as $num) {
                        if ($num > 0) {
                            $used_bytes = $num * 1024;
                            break;
                        }
                    }
                }

                // TENTATIVO B: DU su HOME DIRECTORY (La soluzione per te)
                if ($used_bytes == 0) {
                    // Cerchiamo di individuare la HOME dell'utente cPanel
                    // Di solito è /home/username
                    $path_to_scan = $CFG->dataroot; // Fallback

                    // 1. Proviamo a prendere la HOME dall'ambiente
                    $env_home = getenv('HOME');
                    if (!empty($env_home) && is_readable($env_home)) {
                        $path_to_scan = $env_home;
                    } 
                    // 2. Se fallisce, proviamo a dedurla dal percorso di dataroot
                    // Dataroot: /home/oidcdcmf/test.osel.it/.htm...
                    // Noi vogliamo: /home/oidcdcmf/
                    else {
                        $parts = explode('/', $CFG->dataroot);
                        // Se il percorso inizia con /home/username...
                        if (count($parts) > 3 && $parts[1] == 'home') {
                            $path_to_scan = "/{$parts[1]}/{$parts[2]}"; // Ricostruisce /home/username
                        }
                    }

                    // Eseguiamo DU sulla cartella ROOT dell'utente
                    // Questo includerà: mail, logs, public_html e moodledata
                    $out = shell_exec("du -sb " . escapeshellarg($path_to_scan) . " 2>/dev/null");
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
            // --- CASO 2: Server Dedicato (Standard) ---
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
