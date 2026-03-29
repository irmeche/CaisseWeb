<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/lang.php';

$pageTitle  = __('vendeurs_titre');
$activePage = 'vendeurs';
$assetBase  = '../';

$pdo = getDB();

// Période par défaut : mois en cours
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$dateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');

// Performances vendeurs
$stmtVendeurs = $pdo->prepare("
    SELECT loginVendeur,
           COUNT(*) AS nb_ventes,
           SUM(totale) AS ca,
           SUM(marge) AS marge_totale,
           AVG(totale) AS panier_moyen,
           MAX(dateVente) AS derniere_vente
    FROM vente
    WHERE loginVendeur IS NOT NULL
      AND DATE(dateVente) BETWEEN ? AND ?
    GROUP BY loginVendeur
    ORDER BY ca DESC
");
$stmtVendeurs->execute(array($dateDebut, $dateFin));
$vendeurs = $stmtVendeurs->fetchAll();

// KPI globaux
$stmtKpi = $pdo->prepare("
    SELECT COUNT(DISTINCT loginVendeur) AS nb_vendeurs,
           COALESCE(SUM(totale), 0) AS ca_total,
           COALESCE(SUM(marge), 0) AS marge_totale
    FROM vente
    WHERE loginVendeur IS NOT NULL
      AND DATE(dateVente) BETWEEN ? AND ?
");
$stmtKpi->execute(array($dateDebut, $dateFin));
$kpi = $stmtKpi->fetch();

// Données graphique
$labelsVendeurs = array();
$dataCA         = array();
$dataMarge      = array();
foreach ($vendeurs as $v) {
    $labelsVendeurs[] = $v['loginVendeur'];
    $dataCA[]         = round((float)$v['ca'], 2);
    $dataMarge[]      = round((float)$v['marge_totale'], 2);
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Performance Vendeurs</h4>
    <a href="../ajax/export.php?type=ventes&date_debut=<?= urlencode($dateDebut) ?>&date_fin=<?= urlencode($dateFin) ?>"
       class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
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
                <a href="vendeurs.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Reset
                </a>
            </div>
        </form>
    </div>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-person-badge"></i></div>
                <div>
                    <div class="text-muted small">Vendeurs actifs</div>
                    <div class="fs-5 fw-bold"><?= (int)$kpi['nb_vendeurs'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small">CA total période</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['ca_total'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="bi bi-percent"></i></div>
                <div>
                    <div class="text-muted small">Marge totale</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['marge_totale'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <!-- Table performance -->
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-table me-1 text-primary"></i>Performance par vendeur
            </div>
            <div class="card-body p-0">
                <?php if (empty($vendeurs)): ?>
                    <p class="text-muted text-center py-4">Aucune vente sur cette période.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Vendeur</th>
                                <th class="text-center">Ventes</th>
                                <th class="text-end">CA</th>
                                <th class="text-end">Marge</th>
                                <th class="text-end">Taux marge</th>
                                <th class="text-end">Panier moy.</th>
                                <th class="text-center">Dernière vente</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendeurs as $v): ?>
                            <?php
                            $ca        = (float)$v['ca'];
                            $marge     = (float)$v['marge_totale'];
                            $tauxMarge = $ca > 0 ? ($marge / $ca * 100) : 0;
                            $tauxClass = $tauxMarge >= 20 ? 'text-success' : ($tauxMarge >= 10 ? 'text-warning' : 'text-danger');
                            ?>
                            <tr>
                                <td>
                                    <span class="badge bg-secondary me-1"><i class="bi bi-person-fill"></i></span>
                                    <?= htmlspecialchars($v['loginVendeur']) ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-primary rounded-pill"><?= (int)$v['nb_ventes'] ?></span>
                                </td>
                                <td class="text-end fw-semibold"><?= number_format($ca, 2, ',', ' ') ?> DA</td>
                                <td class="text-end text-success"><?= number_format($marge, 2, ',', ' ') ?> DA</td>
                                <td class="text-end fw-bold <?= $tauxClass ?>">
                                    <?= number_format($tauxMarge, 1, ',', '') ?> %
                                </td>
                                <td class="text-end text-muted small">
                                    <?= number_format((float)$v['panier_moyen'], 2, ',', ' ') ?> DA
                                </td>
                                <td class="text-center small text-muted">
                                    <?= $v['derniere_vente'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($v['derniere_vente']))) : '—' ?>
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

    <!-- Graphique CA par vendeur -->
    <div class="col-xl-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-bar-chart me-1 text-success"></i>CA par vendeur
            </div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <?php if (empty($vendeurs)): ?>
                    <p class="text-muted text-center small">Aucune donnée à afficher.</p>
                <?php else: ?>
                <canvas id="chartVendeurs" style="max-height:300px;"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($vendeurs)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
(function() {
    var labels = <?= json_encode($labelsVendeurs) ?>;
    var dataCA  = <?= json_encode($dataCA) ?>;
    var dataMg  = <?= json_encode($dataMarge) ?>;

    var ctx = document.getElementById('chartVendeurs').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'CA (DA)',
                    data: dataCA,
                    backgroundColor: 'rgba(13, 110, 253, 0.7)',
                    borderColor: 'rgba(13, 110, 253, 1)',
                    borderWidth: 1
                },
                {
                    label: 'Marge (DA)',
                    data: dataMg,
                    backgroundColor: 'rgba(25, 135, 84, 0.7)',
                    borderColor: 'rgba(25, 135, 84, 1)',
                    borderWidth: 1
                }
            ]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'top' }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) { return value.toFixed(0) + ' DA'; }
                    }
                }
            }
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
