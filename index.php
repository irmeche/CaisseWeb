<?php
require_once 'config.php';
require_once 'includes/lang.php';

$pageTitle  = __('dashboard_titre');
$activePage = 'dashboard';
$assetBase  = '';

$pdo = getDB();

$periode = isset($_GET['periode']) ? $_GET['periode'] : 'today';
switch ($periode) {
    case '7days':
        $dateDebut    = date('Y-m-d', strtotime('-6 days'));
        $dateFin      = date('Y-m-d');
        $periodeLabel = __('periode_7j');
        break;
    case 'month':
        $dateDebut    = date('Y-m-01');
        $dateFin      = date('Y-m-d');
        $periodeLabel = __('periode_mois');
        break;
    case 'custom':
        $dateDebut    = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
        $dateFin      = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');
        $periodeLabel = __('periode_perso');
        break;
    default:
        $periode      = 'today';
        $dateDebut    = date('Y-m-d');
        $dateFin      = date('Y-m-d');
        $periodeLabel = __('periode_auj');
        break;
}

$stmtKpi = $pdo->prepare("
    SELECT COALESCE(SUM(totale), 0) AS ca_periode,
           COALESCE(SUM(marge),  0) AS marge_periode,
           COUNT(*)                 AS nb_ventes
    FROM vente WHERE DATE(dateVente) BETWEEN ? AND ?
");
$stmtKpi->execute(array($dateDebut, $dateFin));
$kpi = $stmtKpi->fetch();

$caMois = $pdo->query("
    SELECT COALESCE(SUM(totale), 0) FROM vente
    WHERE YEAR(dateVente) = YEAR(CURDATE()) AND MONTH(dateVente) = MONTH(CURDATE())
")->fetchColumn();

$valeurStock = (float)$pdo->query("SELECT COALESCE(SUM(stock * prixAchat), 0) FROM article")->fetchColumn();
$nbRupture   = (int)$pdo->query("SELECT COUNT(*) FROM article WHERE stock = 0")->fetchColumn();
$nbFaible    = (int)$pdo->query("SELECT COUNT(*) FROM article WHERE stock > 0 AND stock <= seuilAlerte")->fetchColumn();

$stmtTop = $pdo->prepare("
    SELECT av.nom, SUM(av.quantite) AS qte_vendue,
           SUM(av.quantite * av.prixVenteUnite) AS ca_article,
           SUM(av.quantite * (av.prixVenteUnite - av.prixAchatUnite)) AS marge_article
    FROM articlevente av JOIN vente v ON v.idVente = av.idVente
    WHERE DATE(v.dateVente) BETWEEN ? AND ?
    GROUP BY av.code, av.nom ORDER BY marge_article DESC LIMIT 5
");
$stmtTop->execute(array($dateDebut, $dateFin));
$topArticles = $stmtTop->fetchAll();

$alertesListe = $pdo->query("
    SELECT nom, stock, seuilAlerte FROM article
    WHERE stock <= seuilAlerte ORDER BY stock ASC LIMIT 10
")->fetchAll();

// Graphique CA 30 jours
$ca30Rows = $pdo->query("
    SELECT DATE(dateVente) AS jour, SUM(totale) AS ca
    FROM vente WHERE dateVente >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
    GROUP BY DATE(dateVente) ORDER BY jour
")->fetchAll();
$ca30Map = array();
foreach ($ca30Rows as $r) { $ca30Map[$r['jour']] = round((float)$r['ca'], 2); }
$labelsca30 = array(); $dataCa30 = array();
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime('-' . $i . ' days'));
    $labelsca30[] = date('d/m', strtotime($d));
    $dataCa30[]   = isset($ca30Map[$d]) ? $ca30Map[$d] : 0;
}

// Graphique ventes par heure
$heureRows = $pdo->query("
    SELECT HOUR(dateVente) AS heure, COUNT(*) AS nb,
           COALESCE(SUM(totale), 0) AS ca,
           COALESCE(SUM(marge),  0) AS marge
    FROM vente
    WHERE dateVente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY HOUR(dateVente) ORDER BY heure
")->fetchAll();
$heureMap = array();
foreach ($heureRows as $r) {
    $heureMap[(int)$r['heure']] = array(
        'nb'    => (int)$r['nb'],
        'ca'    => round((float)$r['ca'],    2),
        'marge' => round((float)$r['marge'], 2),
    );
}
$labelsHeure = array();
$dataHeureNb = array(); $dataHeureCa = array(); $dataHeureMarge = array();
for ($h = 0; $h <= 23; $h++) {
    $labelsHeure[]    = str_pad($h, 2, '0', STR_PAD_LEFT) . 'h';
    $dataHeureNb[]    = isset($heureMap[$h]) ? $heureMap[$h]['nb']    : 0;
    $dataHeureCa[]    = isset($heureMap[$h]) ? $heureMap[$h]['ca']    : 0;
    $dataHeureMarge[] = isset($heureMap[$h]) ? $heureMap[$h]['marge'] : 0;
}

// Graphique ventes par jour de la semaine
$jourRows = $pdo->query("
    SELECT DAYOFWEEK(dateVente) AS jour_num, COUNT(*) AS nb,
           COALESCE(SUM(totale), 0) AS ca,
           COALESCE(SUM(marge),  0) AS marge
    FROM vente
    WHERE dateVente >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DAYOFWEEK(dateVente)
")->fetchAll();
$jourMap = array();
foreach ($jourRows as $r) {
    $jourMap[(int)$r['jour_num']] = array(
        'nb'    => (int)$r['nb'],
        'ca'    => round((float)$r['ca'],    2),
        'marge' => round((float)$r['marge'], 2),
    );
}
// DAYOFWEEK : 1=Dim, 2=Lun, ..., 7=Sam — on affiche Lun→Dim
$joursNoms     = array(2 => 'Lun', 3 => 'Mar', 4 => 'Mer', 5 => 'Jeu', 6 => 'Ven', 7 => 'Sam', 1 => 'Dim');
$labelsJourSem = array();
$dataJourNb    = array(); $dataJourCa = array(); $dataJourMarge = array();
foreach (array(2, 3, 4, 5, 6, 7, 1) as $j) {
    $labelsJourSem[] = $joursNoms[$j];
    $dataJourNb[]    = isset($jourMap[$j]) ? $jourMap[$j]['nb']    : 0;
    $dataJourCa[]    = isset($jourMap[$j]) ? $jourMap[$j]['ca']    : 0;
    $dataJourMarge[] = isset($jourMap[$j]) ? $jourMap[$j]['marge'] : 0;
}

require_once 'includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2">
    <h4 class="fw-bold mb-0"><i class="bi bi-speedometer2 me-2 text-primary"></i><?= __('dashboard_titre') ?></h4>
    <span class="text-muted small"><?= date('d/m/Y') ?></span>
</div>

<!-- Sélecteur de période -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-2">
        <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
            <div class="btn-group btn-group-sm">
                <a href="?periode=today"  class="btn <?= $periode==='today'  ? 'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-calendar-day me-1"></i><?= __('periode_auj') ?></a>
                <a href="?periode=7days"  class="btn <?= $periode==='7days'  ? 'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-calendar-week me-1"></i><?= __('periode_7j') ?></a>
                <a href="?periode=month"  class="btn <?= $periode==='month'  ? 'btn-primary':'btn-outline-primary' ?>"><i class="bi bi-calendar-month me-1"></i><?= __('periode_mois') ?></a>
                <button type="button" class="btn <?= $periode==='custom' ? 'btn-primary':'btn-outline-primary' ?>"
                        onclick="document.getElementById('customDates').classList.toggle('d-none')">
                    <i class="bi bi-calendar-range me-1"></i><?= __('periode_perso') ?>
                </button>
            </div>
            <div id="customDates" class="d-flex gap-2 align-items-center <?= $periode!=='custom' ? 'd-none' : '' ?>">
                <input type="hidden" name="periode" value="custom">
                <input type="date" name="date_debut" class="form-control form-control-sm" value="<?= htmlspecialchars($dateDebut) ?>" style="width:145px">
                <span class="text-muted small">→</span>
                <input type="date" name="date_fin"   class="form-control form-control-sm" value="<?= htmlspecialchars($dateFin) ?>" style="width:145px">
                <button type="submit" class="btn btn-primary btn-sm">OK</button>
            </div>
        </form>
    </div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small"><?= __('ca_jour') ?> — <?= htmlspecialchars($periodeLabel) ?></div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['ca_periode'], 2, ',', ' ') ?> <?= __('devise') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="text-muted small"><?= __('ventes_jour') ?> — <?= htmlspecialchars($periodeLabel) ?></div>
                    <div class="fs-5 fw-bold"><?= (int)$kpi['nb_ventes'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="bi bi-calendar-month"></i></div>
                <div>
                    <div class="text-muted small"><?= __('ca_mois') ?></div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$caMois, 2, ',', ' ') ?> <?= __('devise') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="text-muted small"><?= __('marge_jour') ?> — <?= htmlspecialchars($periodeLabel) ?></div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['marge_periode'], 2, ',', ' ') ?> <?= __('devise') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-secondary bg-opacity-10 text-secondary"><i class="bi bi-boxes"></i></div>
                <div>
                    <div class="text-muted small"><?= __('valeur_stock') ?></div>
                    <div class="fs-5 fw-bold"><?= number_format($valeurStock, 2, ',', ' ') ?> <?= __('devise') ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($nbRupture > 0 || $nbFaible > 0): ?>
<div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill fs-5 mt-1"></i>
    <div>
        <?php if ($nbRupture > 0): ?><strong><?= $nbRupture ?> <?= __('articles_rupture') ?></strong> <?php endif; ?>
        <?php if ($nbFaible > 0): ?><?= $nbFaible ?> <?= __('articles_sous_seuil') ?><?php endif; ?>
        <a href="pages/stocks.php" class="alert-link ms-1"><?= __('gerer_stocks') ?> →</a>
    </div>
</div>
<?php endif; ?>

<!-- Graphiques -->
<div class="row g-4 mb-4">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-graph-up me-1 text-primary"></i><?= __('chart_ca_30j') ?>
            </div>
            <div class="card-body"><canvas id="chartCa30" style="max-height:260px;"></canvas></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex align-items-center justify-content-between gap-1">
                    <span class="fw-semibold small"><i class="bi bi-clock me-1 text-success"></i>Par heure</span>
                    <div class="btn-group btn-group-sm" id="toggleHeure">
                        <button class="btn btn-outline-secondary active" data-mode="nb">Nb</button>
                        <button class="btn btn-outline-secondary" data-mode="ca">CA</button>
                        <button class="btn btn-outline-secondary" data-mode="marge">Marge</button>
                    </div>
                </div>
            </div>
            <div class="card-body"><canvas id="chartHeure" style="max-height:260px;"></canvas></div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white border-bottom">
                <div class="d-flex align-items-center justify-content-between gap-1">
                    <span class="fw-semibold small"><i class="bi bi-calendar-week me-1 text-warning"></i>Par jour</span>
                    <div class="btn-group btn-group-sm" id="toggleJour">
                        <button class="btn btn-outline-secondary active" data-mode="nb">Nb</button>
                        <button class="btn btn-outline-secondary" data-mode="ca">CA</button>
                        <button class="btn btn-outline-secondary" data-mode="marge">Marge</button>
                    </div>
                </div>
            </div>
            <div class="card-body"><canvas id="chartJourSem" style="max-height:260px;"></canvas></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-bar-chart me-1 text-primary"></i><?= __('top_articles_auj') ?> — <?= htmlspecialchars($periodeLabel) ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topArticles)): ?>
                    <p class="text-muted text-center py-4 small"><?= __('aucune_vente_auj') ?></p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($topArticles as $i => $a): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span><span class="badge bg-secondary me-2"><?= $i+1 ?></span><?= htmlspecialchars($a['nom']) ?></span>
                        <span class="text-end">
                            <span class="badge bg-success rounded-pill"><?= number_format((float)$a['marge_article'],2,',',' ') ?> DA</span>
                            <span class="text-muted small ms-1"><?= (int)$a['qte_vendue'] ?> u.</span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-exclamation-triangle me-1 text-warning"></i><?= __('alertes_stock_titre') ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($alertesListe)): ?>
                    <p class="text-success text-center py-4 small"><i class="bi bi-check-circle me-1"></i><?= __('stocks_ok_msg') ?></p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($alertesListe as $a): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <span class="small"><?= htmlspecialchars($a['nom']) ?></span>
                        <?php if ((int)$a['stock'] === 0): ?>
                            <span class="badge badge-stock-empty px-2 py-1"><?= __('rupture') ?></span>
                        <?php else: ?>
                            <span class="badge badge-stock-low px-2 py-1"><?= (int)$a['stock'] ?> / <?= (int)$a['seuilAlerte'] ?></span>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <div class="card-footer bg-white border-top">
                <a href="pages/stocks.php" class="btn btn-sm btn-outline-warning w-100"><?= __('gerer_stocks') ?> <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var labels30 = <?= json_encode($labelsca30) ?>;
    var dataCA30 = <?= json_encode($dataCa30) ?>;
    var labelCA  = <?= json_encode(__('ca_label')) ?>;
    new Chart(document.getElementById('chartCa30').getContext('2d'), {
        type: 'line',
        data: { labels: labels30, datasets: [{ label: labelCA, data: dataCA30,
            borderColor:'rgba(13,110,253,1)', backgroundColor:'rgba(13,110,253,0.08)',
            fill:true, tension:0.3, pointRadius:3 }] },
        options: { responsive:true, plugins:{ legend:{display:false} },
            scales:{ y:{ beginAtZero:true }, x:{ ticks:{maxTicksLimit:10} } } }
    });

    // --- Graphe par heure ---
    var datasetsHeure = {
        nb:    { data: <?= json_encode($dataHeureNb) ?>,    bg: 'rgba(25,135,84,0.65)',   border: 'rgba(25,135,84,1)',   label: 'Nb ventes' },
        ca:    { data: <?= json_encode($dataHeureCa) ?>,    bg: 'rgba(13,110,253,0.65)',  border: 'rgba(13,110,253,1)',  label: 'CA (DA)' },
        marge: { data: <?= json_encode($dataHeureMarge) ?>, bg: 'rgba(255,193,7,0.65)',   border: 'rgba(255,193,7,1)',   label: 'Marge (DA)' }
    };
    var chartHeure = new Chart(document.getElementById('chartHeure').getContext('2d'), {
        type: 'bar',
        data: { labels: <?= json_encode($labelsHeure) ?>, datasets: [{
            label: 'Nb ventes', data: datasetsHeure.nb.data,
            backgroundColor: datasetsHeure.nb.bg, borderColor: datasetsHeure.nb.border, borderWidth: 1
        }]},
        options: { responsive: true, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } } }
    });
    document.getElementById('toggleHeure').addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var mode = btn.dataset.mode;
        var ds   = datasetsHeure[mode];
        chartHeure.data.datasets[0].data            = ds.data;
        chartHeure.data.datasets[0].backgroundColor = ds.bg;
        chartHeure.data.datasets[0].borderColor     = ds.border;
        chartHeure.data.datasets[0].label           = ds.label;
        chartHeure.options.scales.y.ticks = mode === 'nb'
            ? { stepSize: 1 }
            : { callback: function(v) { return v.toLocaleString('fr-FR') + ' DA'; } };
        chartHeure.update();
        this.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
    });

    // --- Graphe par jour ---
    var jourColors = [
        'rgba(13,110,253,0.65)', 'rgba(13,110,253,0.65)', 'rgba(13,110,253,0.65)',
        'rgba(13,110,253,0.65)', 'rgba(13,110,253,0.65)',
        'rgba(255,193,7,0.7)',   'rgba(220,53,69,0.65)'
    ];
    var datasetsJour = {
        nb:    { data: <?= json_encode($dataJourNb) ?>,    bg: jourColors, border: 1, label: 'Nb ventes' },
        ca:    { data: <?= json_encode($dataJourCa) ?>,    bg: jourColors, border: 1, label: 'CA (DA)' },
        marge: { data: <?= json_encode($dataJourMarge) ?>, bg: jourColors, border: 1, label: 'Marge (DA)' }
    };
    var chartJour = new Chart(document.getElementById('chartJourSem').getContext('2d'), {
        type: 'bar',
        data: { labels: <?= json_encode($labelsJourSem) ?>, datasets: [{
            label: 'Nb ventes', data: datasetsJour.nb.data,
            backgroundColor: jourColors, borderWidth: 1
        }]},
        options: { responsive: true, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } } }
    });
    document.getElementById('toggleJour').addEventListener('click', function(e) {
        var btn = e.target.closest('button');
        if (!btn) return;
        var mode = btn.dataset.mode;
        var ds   = datasetsJour[mode];
        chartJour.data.datasets[0].data  = ds.data;
        chartJour.data.datasets[0].label = ds.label;
        chartJour.options.scales.y.ticks = mode === 'nb'
            ? { stepSize: 1 }
            : { callback: function(v) { return v.toLocaleString('fr-FR') + ' DA'; } };
        chartJour.update();
        this.querySelectorAll('button').forEach(function(b) { b.classList.remove('active'); });
        btn.classList.add('active');
    });
})();
</script>

<?php require_once 'includes/footer.php'; ?>
