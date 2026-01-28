<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// 1. Controllo Accesso
require_login();
require_capability('moodle/site:config', context_system::instance());

// 2. Setup Pagina
$PAGE->set_url(new moodle_url('/local/mmonitor/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_mmonitor'));
$PAGE->set_heading(get_string('pluginname', 'local_mmonitor'));

echo $OUTPUT->header();

// --- FUNZIONI UTILITY LOCALI ---
function get_local_cpu_usage() {
    if (!function_exists('shell_exec')) return null;
    $output = shell_exec('ps ax -o pcpu --no-headers');
    if (empty($output)) return null;
    $lines = explode("\n", trim($output));
    $total_cpu = 0.0;
    foreach ($lines as $line) { $total_cpu += floatval($line); }
    return round($total_cpu, 1);
}

function get_local_ram_usage() {
    if (!function_exists('shell_exec')) return null;
    $output = shell_exec('free -m | grep Mem');
    if (empty($output)) return null;
    $parts = preg_split('/\s+/', trim($output));
    if (isset($parts[1]) && isset($parts[2]) && $parts[1] > 0) {
        return ['total' => $parts[1], 'used' => $parts[2], 'percent' => round(($parts[2] / $parts[1]) * 100, 1)];
    }
    return null;
}

// --- RECUPERO DATI LIVE ---
$cpu_percent = get_local_cpu_usage();
$ram_data = get_local_ram_usage();
$load_fallback = sys_getloadavg();
$load_display = $load_fallback ? implode(' / ', $load_fallback) : 'N/A';

$fiveminutesago = time() - 300;
try {
    $users_live = $DB->count_records_select('sessions', 'timemodified > ?', [$fiveminutesago]);
} catch (Exception $e) { $users_live = '?'; }

$moodle_release = $CFG->release;
$php_version = phpversion();

// Salute Sistema (Cron & Disco)
$lastcron = get_config('tool_task', 'lastcronstart');
$cron_delay = time() - $lastcron;
$is_cron_ok = ($cron_delay < 300);

$disk_free = disk_free_space($CFG->dataroot);
$disk_total = disk_total_space($CFG->dataroot);
$disk_usage_pct = ($disk_total > 0) ? round((($disk_total - $disk_free) / $disk_total) * 100, 1) : 0;
$disk_free_gb = round($disk_free / 1073741824, 1);

// --- RECUPERO DATI CACHED (JSON) ---
$secret = get_config('local_mmonitor', 'secret_key');
$dir = $CFG->dataroot . '/mmonitor_data';
$latest_path = $dir . "/latest_{$secret}.json";

$plugins_list = [];
$stats_platform = null; // Variabile per i nuovi dati
$minutes_ago = 'N/A';
$core_update_msg = null;

if (file_exists($latest_path)) {
    $content = file_get_contents($latest_path);
    if ($content) {
        $json = json_decode($content);
        if ($json) {
            $plugins_list = $json->plugins_report ?? [];
            $timestamp = $json->metadata->timestamp ?? 0;
            $core_update_msg = $json->metadata->core_update_available ?? null;
            
            // Recuperiamo le stats piattaforma se presenti
            if (isset($json->server_status->stats)) {
                $stats_platform = $json->server_status->stats;
            }

            if ($timestamp > 0) {
                $minutes_ago = floor((time() - $timestamp) / 60);
            }
        }
    }
}

// --- INTERFACCIA DASHBOARD ---
?>
<div class="container-fluid">

    <div class="d-flex justify-content-end mb-3">
        <a href="<?php echo new moodle_url('/local/mmonitor/history.php'); ?>" class="btn btn-outline-primary">
            <i class="fa fa-line-chart"></i> Vedi Storico Grafici
        </a>
    </div>

    <div class="row mb-3">
        <div class="col-12">
            <div class="card shadow-sm">
                <div class="card-body d-flex justify-content-between align-items-center py-3">
                    <div>
                        <h4 class="m-0 d-inline-block mr-3" style="font-weight:600;">Moodle <?php echo $moodle_release; ?></h4>
                        <?php if ($core_update_msg): ?>
                            <span class="badge badge-danger p-2"><i class="fa fa-arrow-circle-up"></i> Aggiornamento: <?php echo $core_update_msg; ?></span>
                        <?php else: ?>
                            <a href="<?php echo new moodle_url('/admin/index.php'); ?>" class="btn btn-sm btn-outline-secondary" target="_blank"><i class="fa fa-check-circle"></i> Stato Sistema</a>
                        <?php endif; ?>
                    </div>
                    <div class="text-right">
                        <span class="badge badge-light border p-2">PHP <?php echo $php_version; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6">
            <div class="alert <?php echo $is_cron_ok ? 'alert-success' : 'alert-danger'; ?> d-flex justify-content-between align-items-center mb-0 shadow-sm" style="height: 100%;">
                <span>
                    <i class="fa <?php echo $is_cron_ok ? 'fa-heartbeat' : 'fa-exclamation-triangle'; ?> mr-2"></i>
                    <strong>Stato Cron:</strong> 
                    <?php if ($is_cron_ok): ?> Attivo (<?php echo $cron_delay; ?>s fa) <?php else: ?> FERMO DA <?php echo floor($cron_delay / 60); ?> MINUTI! <?php endif; ?>
                </span>
                <a href="<?php echo new moodle_url('/admin/tool/task/scheduledtasks.php'); ?>" class="btn btn-sm btn-light border">Gestisci</a>
            </div>
        </div>
        <div class="col-md-6">
            <div class="alert <?php echo ($disk_usage_pct < 90) ? 'alert-info' : 'alert-danger'; ?> mb-0 shadow-sm">
                <div class="d-flex justify-content-between">
                    <span><i class="fa fa-hdd-o mr-2"></i> <strong>Moodledata:</strong> <?php echo $disk_usage_pct; ?>% Usato</span>
                    <small><?php echo $disk_free_gb; ?> GB Liberi</small>
                </div>
                <div class="progress mt-2" style="height: 6px;">
                    <div class="progress-bar bg-<?php echo ($disk_usage_pct < 90) ? 'white' : 'danger'; ?>" style="width: <?php echo $disk_usage_pct; ?>%; opacity: 0.8;"></div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if ($stats_platform): ?>
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-left-secondary">
                <div class="card-body d-flex align-items-center">
                    <div class="mr-3 text-secondary"><i class="fa fa-users fa-2x"></i></div>
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">Utenti Totali</div>
                        <div class="h5 mb-0 font-weight-bold text-dark"><?php echo number_format($stats_platform->total_users, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-left-secondary">
                <div class="card-body d-flex align-items-center">
                    <div class="mr-3 text-secondary"><i class="fa fa-graduation-cap fa-2x"></i></div>
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">Corsi Totali</div>
                        <div class="h5 mb-0 font-weight-bold text-dark"><?php echo number_format($stats_platform->total_courses, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-left-secondary">
                <div class="card-body d-flex align-items-center">
                    <div class="mr-3 text-secondary"><i class="fa fa-folder-open fa-2x"></i></div>
                    <div>
                        <div class="text-xs font-weight-bold text-uppercase text-muted mb-1">Categorie</div>
                        <div class="h5 mb-0 font-weight-bold text-dark"><?php echo number_format($stats_platform->total_categories, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3 shadow-sm">
                <div class="card-header">Utilizzo CPU (Locale)</div>
                <div class="card-body">
                    <?php if ($cpu_percent !== null): ?>
                        <h2 class="card-title"><?php echo $cpu_percent; ?>%</h2>
                        <div class="progress" style="height: 6px; background-color: rgba(255,255,255,0.3);">
                            <div class="progress-bar bg-white" style="width: <?php echo min($cpu_percent, 100); ?>%"></div>
                        </div>
                        <p class="card-text mt-2"><small>Somma processi container</small></p>
                    <?php else: ?>
                        <h2 class="card-title"><?php echo $load_display; ?></h2>
                        <p class="card-text"><small>Load Avg</small></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3 shadow-sm">
                <div class="card-header">Utilizzo RAM</div>
                <div class="card-body">
                    <?php if ($ram_data): ?>
                        <h2 class="card-title"><?php echo $ram_data['percent']; ?>%</h2>
                        <p class="card-text mb-1"><?php echo $ram_data['used']; ?> MB / <?php echo $ram_data['total']; ?> MB</p>
                        <div class="progress" style="height: 6px; background-color: rgba(255,255,255,0.3);">
                            <div class="progress-bar bg-white" style="width: <?php echo $ram_data['percent']; ?>%"></div>
                        </div>
                    <?php else: ?>
                        <h2 class="card-title">N/A</h2>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3 shadow-sm">
                <div class="card-header">Utenti Attivi (5 min)</div>
                <div class="card-body">
                    <h2 class="card-title"><?php echo $users_live; ?></h2>
                    <p class="card-text"><small>Sessioni LIVE</small></p>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Plugin Custom / Aggiornamenti</h3>
                <span class="badge badge-light border">Cache plugin: <?php echo $minutes_ago; ?> min fa</span>
            </div>
            
            <?php if (empty($plugins_list)): ?>
                <div class="alert alert-info">Tutto pulito o dati non ancora generati.</div>
            <?php else: ?>
                <table class="table table-striped table-hover shadow-sm bg-white">
                    <thead><tr><th>Plugin</th><th>Versione</th><th>Stato</th></tr></thead>
                    <tbody>
                        <?php foreach ($plugins_list as $p): ?>
                        <tr>
                            <td>
                                <strong><?php echo $p->full_name; ?></strong>
                                <small class="text-muted ml-2">(<?php echo ucfirst($p->type ?? 'plugin'); ?>)</small>
                            </td>
                            <td>
                                <?php echo !empty($p->display) ? $p->display : '<span class="text-muted" style="font-family:monospace;">' . $p->version . '</span>'; ?>
                            </td>
                            <td>
                                <?php if (!empty($p->update_available)): ?>
                                    <span class="badge badge-danger"><i class="fa fa-arrow-up"></i> Upd: <?php echo $p->update_available; ?></span>
                                <?php else: ?>
                                    <span class="badge badge-success"><i class="fa fa-check"></i> Aggiornato</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();