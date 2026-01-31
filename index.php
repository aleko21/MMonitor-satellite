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

// --- FUNZIONI UTILITY ---
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

// --- DATI LIVE ---
$cpu_percent = get_local_cpu_usage();
$ram_data = get_local_ram_usage();
$load_fallback = sys_getloadavg();
$load_display = $load_fallback ? implode(' / ', $load_fallback) : 'N/A';

$fiveminutesago = time() - 300;
try {
    // MODIFICA: Aggiunto "AND userid > 0" per contare solo gli utenti loggati (escludendo guest/login page)
    $users_live = $DB->count_records_select('sessions', 'timemodified > ? AND userid > 0', [$fiveminutesago]);
} catch (Exception $e) { 
    $users_live = '?'; 
}

$moodle_release = $CFG->release;
$php_version = phpversion();

// Salute
$lastcron = get_config('tool_task', 'lastcronstart');
$cron_delay = time() - $lastcron;
$is_cron_ok = ($cron_delay < 300);

$disk_free = disk_free_space($CFG->dataroot);
$disk_total = disk_total_space($CFG->dataroot);
$disk_usage_pct = ($disk_total > 0) ? round((($disk_total - $disk_free) / $disk_total) * 100, 1) : 0;
$disk_free_gb = round($disk_free / 1073741824, 1);

// --- DATI JSON ---
$secret = get_config('local_mmonitor', 'secret_key');
$dir = $CFG->dataroot . '/mmonitor_data';
$latest_path = $dir . "/latest_{$secret}.json";

$plugins_list = [];
$stats_platform = null; 
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
            if (isset($json->server_status->stats)) {
                $stats_platform = $json->server_status->stats;
            }
            if ($timestamp > 0) {
                $minutes_ago = floor((time() - $timestamp) / 60);
            }
        }
    }
}
?>

<style>
    /* --- NUOVO STILE VISIVO --- */

    /* 1. Card "Lift" Effect */
    /* Le card si sollevano al passaggio del mouse */
    .hover-lift {
        transition: transform 0.2s ease, box-shadow 0.2s ease;
        border: none !important; /* Rimuoviamo i bordi standard */
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1); /* Ombra più morbida e diffusa */
    }
    
    .hover-lift:hover {
        transform: translateY(-5px); /* Si sposta in su */
        box-shadow: 0 0.5rem 2rem 0 rgba(58, 59, 69, 0.2) !important;
    }

    /* 2. Icone "Bubble" */
    /* Cerchi colorati dietro le icone */
    .icon-box {
        width: 50px;
        height: 50px;
        border-radius: 50%; /* Cerchio perfetto */
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
    }

    /* 3. Tipografia */
    .stat-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        font-weight: 700;
        color: #858796;
        margin-bottom: 0.25rem;
    }
    
    .stat-value {
        font-size: 1.8rem;
        font-weight: 700;
        color: #2e343a; 
        line-height: 1.2;
    }

    /* 4. Colori Specifici (Soft) */
    /* Usiamo opacità per lo sfondo delle icone */
    .bg-soft-primary { background-color: rgba(13, 110, 253, 0.1); color: var(--primary, #0d6efd); }
    .bg-soft-success { background-color: rgba(25, 135, 84, 0.1); color: var(--success, #198754); }
    .bg-soft-danger  { background-color: rgba(220, 53, 69, 0.1); color: var(--danger, #dc3545); }
    .bg-soft-warning { background-color: rgba(255, 193, 7, 0.15); color: #b48608; } /* Warning più scuro per testo */
    .bg-soft-info    { background-color: rgba(13, 202, 240, 0.1); color: var(--info, #0dcaf0); }
    
    /* Stats Platform Specifics */
    .border-left-accent {
        border-left: 4px solid transparent;
    }
    .border-l-primary { border-left-color: var(--primary, #0d6efd); }
    .border-l-warning { border-left-color: #ffc107; }
    .border-l-info    { border-left-color: #0dcaf0; }

</style>

<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div></div>
        <a href="<?php echo new moodle_url('/local/mmonitor/history.php'); ?>" class="btn btn-white shadow-sm text-primary font-weight-bold">
            <i class="fa fa-line-chart mr-2"></i> Vedi Storico Grafici
        </a>
    </div>

    <div class="row mb-4">
        <div class="col-12">
            <div class="card hover-lift shadow-sm p-2">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <div class="icon-box bg-soft-primary mr-3">
                            <i class="fa fa-graduation-cap"></i>
                        </div>
                        <div>
                            <h4 class="m-0 font-weight-bold text-dark">Moodle <?php echo $moodle_release; ?></h4>
                            <?php if ($core_update_msg): ?>
                                <span class="badge badge-danger mt-1">Aggiornamento disponibile: <?php echo $core_update_msg; ?></span>
                            <?php else: ?>
                                <small class="text-success font-weight-bold"><i class="fa fa-check"></i> Core Aggiornato</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="text-right d-none d-sm-block">
                        <span class="badge badge-light border px-3 py-2 text-muted" style="font-size: 0.9em;">PHP <?php echo $php_version; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        
        <div class="col-md-4 mb-4">
            <div class="card hover-lift h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="stat-label">Carico CPU</div>
                        <div class="icon-box bg-soft-danger">
                            <i class="fa fa-microchip"></i>
                        </div>
                    </div>
                    <?php if ($cpu_percent !== null): ?>
                        <div class="d-flex align-items-baseline">
                            <div class="stat-value mr-2"><?php echo $cpu_percent; ?>%</div>
                        </div>
                        <div class="progress mt-3" style="height: 6px; border-radius: 3px;">
                            <div class="progress-bar bg-danger" style="width: <?php echo min($cpu_percent, 100); ?>%;"></div>
                        </div>
                    <?php else: ?>
                        <div class="stat-value"><?php echo $load_display; ?></div>
                        <small>Load Avg</small>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card hover-lift h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="stat-label">Memoria RAM</div>
                        <div class="icon-box bg-soft-info">
                            <i class="fa fa-memory"></i>
                        </div>
                    </div>
                    <?php if ($ram_data): ?>
                        <div class="d-flex align-items-baseline">
                            <div class="stat-value mr-2"><?php echo $ram_data['percent']; ?>%</div>
                        </div>
                        <small class="text-muted"><?php echo $ram_data['used']; ?> MB usati su <?php echo $ram_data['total']; ?> MB</small>
                        <div class="progress mt-3" style="height: 6px; border-radius: 3px;">
                            <div class="progress-bar bg-info" style="width: <?php echo $ram_data['percent']; ?>%;"></div>
                        </div>
                    <?php else: ?>
                        <div class="stat-value">N/A</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card hover-lift h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-3">
                        <div class="stat-label text-success">Utenti Online</div>
                        <div class="icon-box bg-soft-success">
                            <i class="fa fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value text-success"><?php echo $users_live; ?></div>
                    <small class="text-muted">Sessioni attive (5 min)</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-6 mb-3">
            <div class="card border-0 shadow-sm <?php echo $is_cron_ok ? 'border-left-success' : 'border-left-danger'; ?>" style="border-left: 4px solid;">
                <div class="card-body py-3 d-flex align-items-center">
                    <div class="mr-3">
                        <i class="fa <?php echo $is_cron_ok ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger'; ?> fa-2x"></i>
                    </div>
                    <div>
                        <div class="text-uppercase font-weight-bold <?php echo $is_cron_ok ? 'text-success' : 'text-danger'; ?>" style="font-size: 0.8rem;">Stato Cron</div>
                        <div class="font-weight-bold text-dark">
                            <?php if ($is_cron_ok): ?> Eseguito <?php echo $cron_delay; ?> sec fa <?php else: ?> FERMO DA <?php echo floor($cron_delay / 60); ?> MINUTI! <?php endif; ?>
                        </div>
                    </div>
                    <a href="<?php echo new moodle_url('/admin/tool/task/scheduledtasks.php'); ?>" class="btn btn-sm btn-light ml-auto">Check</a>
                </div>
            </div>
        </div>
        <div class="col-md-6 mb-3">
            <div class="card border-0 shadow-sm <?php echo ($disk_usage_pct < 90) ? 'border-left-info' : 'border-left-danger'; ?>" style="border-left: 4px solid;">
                <div class="card-body py-3 d-flex align-items-center">
                    <div class="mr-3">
                        <i class="fa fa-hdd-o text-info fa-2x"></i>
                    </div>
                    <div class="w-100">
                        <div class="d-flex justify-content-between">
                            <div class="text-uppercase font-weight-bold text-info" style="font-size: 0.8rem;">Disco (Moodledata)</div>
                            <span class="font-weight-bold text-dark"><?php echo $disk_usage_pct; ?>%</span>
                        </div>
                        <div class="progress mt-2" style="height: 4px;">
                             <div class="progress-bar bg-info" style="width: <?php echo $disk_usage_pct; ?>%;"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($stats_platform): ?>
    <div class="row mb-4">
        
        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-left-accent border-l-primary h-100 py-2">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-soft-primary mr-3">
                        <i class="fa fa-id-card"></i>
                    </div>
                    <div>
                        <div class="stat-label text-primary mb-0">Utenti Totali</div>
                        <div class="stat-value text-dark"><?php echo number_format($stats_platform->total_users, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-left-accent border-l-warning h-100 py-2">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-soft-warning mr-3">
                        <i class="fa fa-graduation-cap"></i>
                    </div>
                    <div>
                        <div class="stat-label text-warning mb-0" style="color: #b48608;">Corsi Totali</div>
                        <div class="stat-value text-dark"><?php echo number_format($stats_platform->total_courses, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-3">
            <div class="card shadow-sm border-left-accent border-l-info h-100 py-2">
                <div class="card-body d-flex align-items-center">
                    <div class="icon-box bg-soft-info mr-3">
                        <i class="fa fa-folder-open"></i>
                    </div>
                    <div>
                        <div class="stat-label text-info mb-0">Categorie</div>
                        <div class="stat-value text-dark"><?php echo number_format($stats_platform->total_categories, 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <div class="row mt-4">
        <div class="col-12">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom-0 py-3 d-flex justify-content-between align-items-center">
                    <h5 class="m-0 font-weight-bold text-dark">Plugin & Aggiornamenti</h5>
                    <span class="badge badge-light">Cache: <?php echo $minutes_ago; ?> min fa</span>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($plugins_list)): ?>
                        <div class="text-center p-5">
                            <div class="icon-box bg-soft-success mx-auto mb-3" style="width: 80px; height: 80px; font-size: 2rem;">
                                <i class="fa fa-check"></i>
                            </div>
                            <h6 class="text-muted">Nessun plugin locale o aggiornamento in sospeso.</h6>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light text-muted" style="font-size: 0.85rem; text-transform: uppercase;">
                                    <tr>
                                        <th class="border-top-0 pl-4">Plugin</th>
                                        <th class="border-top-0">Versione</th>
                                        <th class="border-top-0 text-center">Stato</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($plugins_list as $p): ?>
                                    <tr>
                                        <td class="pl-4 align-middle">
                                            <div class="font-weight-bold text-dark"><?php echo $p->full_name; ?></div>
                                            <small class="text-muted"><?php echo ucfirst($p->type ?? 'plugin'); ?></small>
                                        </td>
                                        <td class="align-middle">
                                            <?php echo !empty($p->display) ? $p->display : '<code class="text-muted">' . $p->version . '</code>'; ?>
                                        </td>
                                        <td class="align-middle text-center">
                                            <?php if (!empty($p->update_available)): ?>
                                                <span class="badge badge-danger px-2 py-1"><i class="fa fa-arrow-up"></i> v. <?php echo $p->update_available; ?></span>
                                            <?php else: ?>
                                                <span class="badge badge-success px-2 py-1"><i class="fa fa-check"></i> OK</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
echo $OUTPUT->footer();
