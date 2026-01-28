<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// 1. Controllo Accesso
require_login();
require_capability('moodle/site:config', context_system::instance());

// 2. Gestione Parametri
$period_days = optional_param('period', 7, PARAM_INT); 
$cutoff_timestamp = time() - ($period_days * 86400);

// 3. Setup Pagina
$PAGE->set_url(new moodle_url('/local/mmonitor/history.php', ['period' => $period_days]));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('pluginname', 'local_mmonitor') . ' - Storico');
$PAGE->set_heading('Analisi Storica Performance');

echo $OUTPUT->header();
?>
<style>
    /* --- FIX LAYOUT GRAFICI --- */

    /* 1. Il Contenitore Esterno (La Scatola) */
    /* Diamo un'altezza fissa e generosa. overflow:visible è CRUCIALE. */
    .mmonitor-chart-wrapper-large {
        position: relative;
        width: 100%;
        height: 500px; /* Molto alto per stare sicuri */
        overflow: visible !important; 
        margin-bottom: 30px;
    }

    .mmonitor-chart-wrapper-small {
        position: relative;
        width: 100%;
        height: 400px; /* Altezza standard */
        overflow: visible !important;
        margin-bottom: 30px;
    }

    /* 2. Il Grafico (Il Canvas) */
    /* TRUCCO: Forziamo il canvas a essere PIÙ BASSO del contenitore.
       Questo lascia uno spazio vuoto fisico in basso per le etichette. */
    .mmonitor-chart-wrapper-large canvas {
        max-height: 450px !important; /* 50px meno del contenitore */
        width: 100% !important;
    }

    .mmonitor-chart-wrapper-small canvas {
        max-height: 350px !important; /* 50px meno del contenitore */
        width: 100% !important;
    }

    /* Assicuriamoci che Moodle non nasconda nulla */
    .card-body {
        overflow: visible !important;
    }

    /* Stile tabella */
    .mmonitor-data-table-container {
        max-height: 500px;
        overflow-y: auto;
        border: 1px solid #dee2e6;
    }
    
    .chart-table-expand { display: none !important; }
</style>

<?php $dashboard_url = new moodle_url('/local/mmonitor/index.php'); ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <a href="<?php echo $dashboard_url; ?>" class="btn btn-secondary">
        <i class="fa fa-arrow-left"></i> Dashboard Live
    </a>

    <div class="btn-group" role="group">
        <?php 
        $periods = [1 => '24 Ore', 3 => '3 Giorni', 7 => '7 Giorni', 30 => '30 Giorni'];
        foreach ($periods as $days => $label) {
            $active = ($days == $period_days) ? 'active btn-primary' : 'btn-outline-primary';
            $url = new moodle_url('/local/mmonitor/history.php', ['period' => $days]);
            echo html_writer::link($url, $label, ['class' => 'btn ' . $active]);
        }
        ?>
    </div>
</div>

<?php
// 4. Recupero Dati
$secret = get_config('local_mmonitor', 'secret_key');
$dir = $CFG->dataroot . '/mmonitor_data'; 
$files = glob($dir . "/status_{$secret}_*.json");

$data_points = [];
$max_users_found = 0;

foreach ($files as $file) {
    if (filemtime($file) < $cutoff_timestamp) continue;
    $content = file_get_contents($file);
    if (!$content) continue;
    
    $json = json_decode($content);
    if (!$json || empty($json->metadata->timestamp)) continue;
    if ($json->metadata->timestamp < $cutoff_timestamp) continue;

    $ts = $json->metadata->timestamp;
    $cpu = $json->server_status->cpu_local_percent ?? ($json->server_status->load[0] ?? 0);
    $ram = $json->server_status->ram_usage->percent ?? 0;
    $users = intval($json->server_status->concurrent_users ?? 0);

    if ($users > $max_users_found) {
        $max_users_found = $users;
    }

    $date_format = ($period_days <= 1) ? '%H:%M' : '%d/%m %H:%M';
    $date_full   = userdate($ts, '%d/%m/%Y %H:%M:%S'); 

    $data_points[$ts] = [
        'cpu' => floatval($cpu),
        'ram' => floatval($ram),
        'users' => $users,
        'label' => userdate($ts, $date_format),
        'full_date' => $date_full
    ];
}

ksort($data_points);

$labels = [];
$series_cpu = [];
$series_ram = [];
$series_users = [];

foreach ($data_points as $point) {
    $labels[] = $point['label'];
    $series_cpu[] = $point['cpu'];
    $series_ram[] = $point['ram'];
    $series_users[] = $point['users'];
}

// --- CONFIGURAZIONE GRAFICI ---

if (empty($data_points)) {
    echo $OUTPUT->notification('Nessun dato trovato per il periodo selezionato.', 'warning');
} else {

    // 1. CORRELAZIONE
    $chart_combo = new \core\chart_line();
    $chart_combo->set_title('Visione d\'Insieme');
    $chart_combo->set_labels($labels);
    $yaxis_combo = $chart_combo->get_yaxis(0, true);
    $yaxis_combo->set_min(0);

    $s_users = new \core\chart_series('Utenti', $series_users);
    $s_users->set_color('#28a745');
    $s_users->set_type(\core\chart_series::TYPE_LINE);
    $s_users->set_smooth(false); 
    $s_users->set_fill(true); 
    $chart_combo->add_series($s_users);

    $s_cpu = new \core\chart_series('CPU %', $series_cpu);
    $s_cpu->set_color('#dc3545');
    $s_cpu->set_type(\core\chart_series::TYPE_LINE);
    $s_cpu->set_smooth(false);
    $chart_combo->add_series($s_cpu);

    $s_ram = new \core\chart_series('RAM %', $series_ram);
    $s_ram->set_color('#6f42c1');
    $s_ram->set_type(\core\chart_series::TYPE_LINE);
    $s_ram->set_smooth(false);
    $chart_combo->add_series($s_ram);

    // 2. HARDWARE
    $chart_hw = new \core\chart_line();
    $chart_hw->set_title('Dettaglio Hardware');
    $chart_hw->set_labels($labels);
    $yaxis_hw = $chart_hw->get_yaxis(0, true);
    $yaxis_hw->set_min(0);
    $yaxis_hw->set_max(100); 

    $s_cpu_hw = new \core\chart_series('CPU %', $series_cpu);
    $s_cpu_hw->set_color('#002366'); 
    $s_cpu_hw->set_smooth(false);
    $chart_hw->add_series($s_cpu_hw);

    $s_ram_hw = new \core\chart_series('RAM %', $series_ram);
    $s_ram_hw->set_color('#b91c1c'); 
    $s_ram_hw->set_smooth(false);
    $chart_hw->add_series($s_ram_hw);

    // 3. UTENTI
    $chart_users = new \core\chart_line();
    $chart_users->set_title('Traffico Utenti');
    $chart_users->set_labels($labels);
    $yaxis_users = $chart_users->get_yaxis(0, true);
    $yaxis_users->set_min(0);
    $yaxis_users->set_stepsize(1);
    $user_chart_max = ($max_users_found < 5) ? 5 : ceil($max_users_found * 1.2);
    $yaxis_users->set_max($user_chart_max);

    $s_users_only = new \core\chart_series('Sessioni Attive', $series_users);
    $s_users_only->set_color('#198754'); 
    $s_users_only->set_smooth(false);
    $chart_users->add_series($s_users_only);

    // --- RENDERING HTML ---
    ?>
    <div class="container-fluid">
        
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow-sm border-left-primary">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h4 class="m-0 font-weight-bold text-primary">Triangolazione Risorse</h4>
                        <span class="badge badge-light border">Ultimi <?php echo $periods[$period_days]; ?></span>
                    </div>
                    <div class="card-body p-3">
                        <div class="mmonitor-chart-wrapper-large">
                            <?php echo $OUTPUT->render($chart_combo); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="m-0 font-weight-bold" style="color: #002366;">Carico Sistema (CPU/RAM)</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="mmonitor-chart-wrapper-small">
                            <?php echo $OUTPUT->render($chart_hw); ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h5 class="m-0 font-weight-bold" style="color: #198754;">Traffico Utenti</h5>
                    </div>
                    <div class="card-body p-3">
                        <div class="mmonitor-chart-wrapper-small">
                            <?php echo $OUTPUT->render($chart_users); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow-sm">
                    <div class="card-header bg-white">
                        <h4 class="m-0">Dati Grezzi (Verifica Valori)</h4>
                    </div>
                    <div class="card-body p-0">
                        <div class="mmonitor-data-table-container">
                            <table class="table table-striped table-hover mb-0 text-center">
                                <thead class="thead-light">
                                    <tr>
                                        <th class="sticky-top bg-light">Data e Ora</th>
                                        <th class="sticky-top bg-light">Utenti</th>
                                        <th class="sticky-top bg-light">CPU %</th>
                                        <th class="sticky-top bg-light">RAM %</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $reversed_data = array_reverse($data_points);
                                    foreach ($reversed_data as $pt): 
                                    ?>
                                    <tr>
                                        <td><?php echo $pt['full_date']; ?></td>
                                        <td>
                                            <span class="badge badge-success"><?php echo $pt['users']; ?></span>
                                        </td>
                                        <td><?php echo $pt['cpu']; ?>%</td>
                                        <td><?php echo $pt['ram']; ?>%</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php
}

echo $OUTPUT->footer();