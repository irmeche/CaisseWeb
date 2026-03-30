<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/lang.php';

$pageTitle  = __('stats_titre');
$activePage = 'stats';
$assetBase  = '../';

$pdo = getDB();

// Période — par défaut : les 30 derniers jours avec données
if (isset($_GET['date_debut']) || isset($_GET['date_fin'])) {
    $dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
    $dateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');
} else {
    $dernierDate = $pdo->query("SELECT DATE(MAX(dateVente)) FROM vente")->fetchColumn();
    if ($dernierDate) {
        $dateFin   = $dernierDate;
        $dateDebut = date('Y-m-d', strtotime($dernierDate . ' -29 days'));
    } else {
        $dateDebut = date('Y-m-01');
        $dateFin   = date('Y-m-d');
    }
}

// ── Graphique 1 : CA par jour sur la période ─────────────────────────────
$stmtCaJour = $pdo->prepare("
    SELECT DATE(dateVente) AS jour, SUM(totale) AS ca, SUM(marge) AS marge
    FROM vente
    WHERE DATE(dateVente) BETWEEN ? AND ?
    GROUP BY DATE(dateVente)
    ORDER BY jour
");
$stmtCaJour->execute(array($dateDebut, $dateFin));
$caJourRows = $stmtCaJour->fetchAll();

$labelsJour = array();
$dataCaJour = array();
$dataMargeJour = array();
foreach ($caJourRows as $r) {
    $labelsJour[]    = $r['jour'];
    $dataCaJour[]    = round((float)$r['ca'], 2);
    $dataMargeJour[] = round((float)$r['marge'], 2);
}

// ── Graphique 2 : Top 10 articles par CA ─────────────────────────────────
$stmtTop = $pdo->prepare("
    SELECT av.nom AS article_nom,
           SUM(av.quantite * av.prixVenteUnite) AS ca_article
    FROM articlevente av
    JOIN vente v ON v.idVente = av.idVente
    WHERE DATE(v.dateVente) BETWEEN ? AND ?
    GROUP BY av.code, av.nom
    ORDER BY ca_article DESC
    LIMIT 10
");
$stmtTop->execute(array($dateDebut, $dateFin));
$topRows = $stmtTop->fetchAll();

$labelsTop  = array();
$dataTop    = array();
foreach ($topRows as $r) {
    $labelsTop[] = $r['article_nom'];
    $dataTop[]   = round((float)$r['ca_article'], 2);
}

// ── Graphique 3 : Top 10 articles par marge ──────────────────────────────
$stmtTopMarge = $pdo->prepare("
    SELECT av.nom AS article_nom,
           SUM(av.quantite * (av.prixVenteUnite - av.prixAchatUnite)) AS marge_article
    FROM articlevente av
    JOIN vente v ON v.idVente = av.idVente
    WHERE DATE(v.dateVente) BETWEEN ? AND ?
    GROUP BY av.code, av.nom
    ORDER BY marge_article DESC
    LIMIT 10
");
$stmtTopMarge->execute(array($dateDebut, $dateFin));
$topMargeRows = $stmtTopMarge->fetchAll();

$labelsTopMarge = array();
$dataTopMarge   = array();
foreach ($topMargeRows as $r) {
    $labelsTopMarge[] = $r['article_nom'];
    $dataTopMarge[]   = round((float)$r['marge_article'], 2);
}

// ── Graphique 4 : Comparaison mois courant vs mois précédent ─────────────
$moisCourantDebut = date('Y-m-01');
$moisCourantFin   = date('Y-m-d');
$moisPrecDebut    = date('Y-m-01', strtotime('-1 month'));
$moisPrecFin      = date('Y-m-t', strtotime('-1 month'));

$stmtMoisCourant = $pdo->prepare("
    SELECT DAY(dateVente) AS jour, SUM(totale) AS ca
    FROM vente
    WHERE DATE(dateVente) BETWEEN ? AND ?
    GROUP BY DAY(dateVente)
    ORDER BY jour
");
$stmtMoisCourant->execute(array($moisCourantDebut, $moisCourantFin));
$moisCourantRows = $stmtMoisCourant->fetchAll();

$stmtMoisPrec = $pdo->prepare("
    SELECT DAY(dateVente) AS jour, SUM(totale) AS ca
    FROM vente
    WHERE DATE(dateVente) BETWEEN ? AND ?
    GROUP BY DAY(dateVente)
    ORDER BY jour
");
$stmtMoisPrec->execute(array($moisPrecDebut, $moisPrecFin));
$moisPrecRows = $stmtMoisPrec->fetchAll();

// Construire tableaux indexés par jour (1..31)
$dataMoisCourant = array_fill(1, 31, null);
$dataMoisPrec    = array_fill(1, 31, null);
foreach ($moisCourantRows as $r) { $dataMoisCourant[(int)$r['jour']] = round((float)$r['ca'], 2); }
foreach ($moisPrecRows    as $r) { $dataMoisPrec[(int)$r['jour']]    = round((float)$r['ca'], 2); }

$labelsJours     = range(1, 31);
$dataMoisCourantArr = array_values($dataMoisCourant);
$dataMoisPrecArr    = array_values($dataMoisPrec);

// ── Tableau récapitulatif par semaine ─────────────────────────────────────
$semaines = $pdo->query("
    SELECT YEARWEEK(dateVente) AS semaine,
           MIN(DATE(dateVente)) AS debut,
           MAX(DATE(dateVente)) AS fin,
           SUM(totale) AS ca,
           SUM(marge) AS marge,
           COUNT(*) AS nb_ventes
    FROM vente
    GROUP BY YEARWEEK(dateVente)
    ORDER BY semaine DESC
    LIMIT 12
")->fetchAll();

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-bar-chart-line me-2 text-primary"></i>Statistiques avancées</h4>
    <a href="../ajax/export.php?type=ventes&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>"
       class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV ventes
    </a>
</div>

<!-- Filtre période -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date début</label>
                <input type="date" name="date_debut" class="form-control"
                       value="<?= htmlspecialchars($dateDebut) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Date fin</label>
                <input type="date" name="date_fin" class="form-control"
                       value="<?= htmlspecialchars($dateFin) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
            </div>
            <div class="col-md-2">
                <a href="stats.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Graphiques ligne 1 -->
<div class="row g-4 mb-4">
    <!-- Graphique 1 : CA par jour -->
    <div class="col-xl-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-graph-up me-1 text-primary"></i>
                CA par jour — <?= htmlspecialchars($dateDebut) ?> au <?= htmlspecialchars($dateFin) ?>
            </div>
            <div class="card-body">
                <canvas id="chartCaJour" style="max-height:280px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Graphique 3 : Top 10 articles par marge -->
    <div class="col-xl-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-trophy me-1 text-success"></i>Top 10 articles par marge
            </div>
            <div class="card-body">
                <?php if (empty($topMargeRows)): ?>
                    <p class="text-muted text-center small">Aucune donnée.</p>
                <?php else: ?>
                <canvas id="chartTopMarge" style="max-height:280px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Graphiques ligne 2 -->
<div class="row g-4 mb-4">
    <!-- Graphique 2 : Top 10 articles -->
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-trophy me-1 text-warning"></i>Top 10 articles par CA
            </div>
            <div class="card-body">
                <?php if (empty($topRows)): ?>
                    <p class="text-muted text-center small">Aucune donnée.</p>
                <?php else: ?>
                <canvas id="chartTopArticles" style="max-height:300px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Graphique 4 : Comparaison mois -->
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-calendar-range me-1 text-success"></i>
                Mois courant vs mois précédent
            </div>
            <div class="card-body">
                <canvas id="chartComparaison" style="max-height:300px;"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Tableau récapitulatif par semaine -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold border-bottom">
        <i class="bi bi-calendar-week me-1 text-primary"></i>Récapitulatif par semaine (12 dernières semaines)
    </div>
    <div class="card-body p-0">
        <?php if (empty($semaines)): ?>
            <p class="text-muted text-center py-4">Aucune donnée.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Semaine</th>
                        <th>Début</th>
                        <th>Fin</th>
                        <th class="text-center">Nb ventes</th>
                        <th class="text-end">CA</th>
                        <th class="text-end">Marge</th>
                        <th class="text-end">Taux marge</th>
                        <th class="text-end">Panier moyen</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($semaines as $s): ?>
                    <?php
                    $ca    = (float)$s['ca'];
                    $marge = (float)$s['marge'];
                    $taux  = $ca > 0 ? ($marge / $ca * 100) : 0;
                    $panier = $s['nb_ventes'] > 0 ? ($ca / $s['nb_ventes']) : 0;
                    $tauxClass = $taux >= 20 ? 'text-success' : ($taux >= 10 ? 'text-warning' : 'text-danger');
                    ?>
                    <tr>
                        <td class="text-muted small"><?= htmlspecialchars($s['semaine']) ?></td>
                        <td class="small"><?= htmlspecialchars(date('d/m/Y', strtotime($s['debut']))) ?></td>
                        <td class="small"><?= htmlspecialchars(date('d/m/Y', strtotime($s['fin']))) ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= (int)$s['nb_ventes'] ?></span>
                        </td>
                        <td class="text-end fw-semibold"><?= number_format($ca, 2, ',', ' ') ?> DA</td>
                        <td class="text-end text-success"><?= number_format($marge, 2, ',', ' ') ?> DA</td>
                        <td class="text-end fw-bold <?= $tauxClass ?>"><?= number_format($taux, 1, ',', '') ?> %</td>
                        <td class="text-end text-muted small"><?= number_format($panier, 2, ',', ' ') ?> DA</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    // Graphique 1 : CA par jour
    var labelsJour   = <?= json_encode($labelsJour) ?>;
    var dataCaJour   = <?= json_encode($dataCaJour) ?>;
    var dataMgJour   = <?= json_encode($dataMargeJour) ?>;

    new Chart(document.getElementById('chartCaJour').getContext('2d'), {
        type: 'line',
        data: {
            labels: labelsJour,
            datasets: [
                {
                    label: 'CA (DA)',
                    data: dataCaJour,
                    borderColor: 'rgba(13,110,253,1)',
                    backgroundColor: 'rgba(13,110,253,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                },
                {
                    label: 'Marge (DA)',
                    data: dataMgJour,
                    borderColor: 'rgba(25,135,84,1)',
                    backgroundColor: 'rgba(25,135,84,0.1)',
                    fill: true,
                    tension: 0.3,
                    pointRadius: 3
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return v + ' DA'; } }
                }
            }
        }
    });

    // Graphique 2 : Top 10 articles
    <?php if (!empty($topRows)): ?>
    var labelsTop = <?= json_encode($labelsTop) ?>;
    var dataTop   = <?= json_encode($dataTop) ?>;

    new Chart(document.getElementById('chartTopArticles').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsTop,
            datasets: [{
                label: 'CA (DA)',
                data: dataTop,
                backgroundColor: 'rgba(255,193,7,0.7)',
                borderColor: 'rgba(255,193,7,1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return v + ' DA'; } }
                }
            }
        }
    });
    <?php endif; ?>

    // Graphique 3 : Top 10 articles par marge
    <?php if (!empty($topMargeRows)): ?>
    var labelsTopMarge = <?= json_encode($labelsTopMarge) ?>;
    var dataTopMarge   = <?= json_encode($dataTopMarge) ?>;

    new Chart(document.getElementById('chartTopMarge').getContext('2d'), {
        type: 'bar',
        data: {
            labels: labelsTopMarge,
            datasets: [{
                label: 'Marge (DA)',
                data: dataTopMarge,
                backgroundColor: 'rgba(25,135,84,0.7)',
                borderColor: 'rgba(25,135,84,1)',
                borderWidth: 1
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return v.toLocaleString('fr-FR') + ' DA'; } }
                }
            }
        }
    });
    <?php endif; ?>

    // Graphique 4 : Comparaison mois
    var joursLabels    = <?= json_encode($labelsJours) ?>;
    var dataCourant    = <?= json_encode($dataMoisCourantArr) ?>;
    var dataPrec       = <?= json_encode($dataMoisPrecArr) ?>;
    var moisCourantLib = '<?= date('F Y') ?>';
    var moisPrecLib    = '<?= date('F Y', strtotime('-1 month')) ?>';

    new Chart(document.getElementById('chartComparaison').getContext('2d'), {
        type: 'line',
        data: {
            labels: joursLabels,
            datasets: [
                {
                    label: moisCourantLib,
                    data: dataCourant,
                    borderColor: 'rgba(13,110,253,1)',
                    backgroundColor: 'rgba(13,110,253,0.05)',
                    fill: false,
                    tension: 0.3,
                    pointRadius: 2,
                    spanGaps: true
                },
                {
                    label: moisPrecLib,
                    data: dataPrec,
                    borderColor: 'rgba(108,117,125,0.8)',
                    backgroundColor: 'rgba(108,117,125,0.05)',
                    fill: false,
                    tension: 0.3,
                    pointRadius: 2,
                    borderDash: [5, 3],
                    spanGaps: true
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: function(v) { return v + ' DA'; } }
                }
            }
        }
    });
})();
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
