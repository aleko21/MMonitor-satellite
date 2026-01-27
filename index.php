<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// 1. Controllo Accesso (Solo Admin possono vedere la dashboard)
require_login();
require_capability('moodle/site:config', context_system::instance());

// 2. Setup Pagina
$PAGE->set_url(new moodle_url('/local/mmonitor/index.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('dashboard', 'local_mmonitor'));
$PAGE->set_heading(get_string('dashboard_heading', 'local_mmonitor'));

echo $OUTPUT->header();

// 3. Caricamento Dati
$secret = get_config('local_mmonitor', 'secret_key');
$dir = $CFG->dirroot . '/monitor_data';
$latest_path = $dir . "/latest_{$secret}.json";

if (!file_exists($latest_path)) {
    echo $OUTPUT->notification("Nessun dato trovato. Esegui il task pianificato per generare il primo report.", 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$data = json_decode(file_get_contents($latest_path));

// --- INTERFACCIA DASHBOARD ---
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Carico Server (1/5/15 min)</div>
                <div class="card-body">
                    <h2 class="card-title"><?php echo implode(' / ', $data->server_status->load); ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Utenti Concorrenti (5m)</div>
                <div class="card-body">
                    <h2 class="card-title"><?php echo $data->server_status->concurrent_users; ?></h2>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Versione Moodle</div>
                <div class="card-body">
                    <h2 class="card-title"><?php echo $data->metadata->moodle_release; ?></h2>
                </div>
            </div>
        </div>
    </div>

    <div class="row mt-4">
        <div class="col-12">
            <h3>Monitoraggio Plugin Custom / Addons</h3>
            <table class="table table-striped table-hover">
                <thead>
                    <tr>
                        <th>Plugin</th>
                        <th>Versione DB</th>
                        <th>Versione Release</th>
                        <th>Stato Aggiornamento</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data->plugins_report as $p): ?>
                    <tr>
                        <td><strong><?php echo $p->full_name; ?></strong></td>
                        <td><?php echo $p->version; ?></td>
                        <td><span class="badge badge-secondary"><?php echo $p->display; ?></span></td>
                        <td>
                            <?php if ($p->update_available): ?>
                                <span class="badge badge-danger">Update Disponibile: <?php echo $p->update_available; ?></span>
                            <?php else: ?>
                                <span class="badge badge-success">Aggiornato</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php

echo $OUTPUT->footer();
