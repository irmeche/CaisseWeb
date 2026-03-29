<?php
require_once '../config.php';
require_once '../includes/lang.php';

$pageTitle  = __('marges_titre');
$activePage = 'marges';
$assetBase  = '../';

$pdo = getDB();

// ── Filtres ──────────────────────────────────────────────────
$categorie  = trim(isset($_GET['categorie'])  ? $_GET['categorie']  : '');
$dateDebut  = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$dateFin    = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');
$triGet     = isset($_GET['tri'])  ? $_GET['tri']  : '';
$triColonne = in_array($triGet, array('marge_pct', 'marge_brute', 'ca', 'nom')) ? $triGet : 'marge_pct';
$sensGet    = isset($_GET['sens']) ? $_GET['sens'] : 'desc';
$triSens    = $sensGet === 'asc' ? 'ASC' : 'DESC';

// ── Marges théoriques par article (catalogue) ────────────────
$whereArt  = array('a.prixAchat > 0');
$paramsArt = array();
if ($categorie !== '') {
    $whereArt[]  = 'a.categorie = ?';
    $paramsArt[] = $categorie;
}
$whereArtSQL = 'WHERE ' . implode(' AND ', $whereArt);

$articles = $pdo->prepare("
    SELECT a.code,
           a.nom,
           a.categorie,
           a.prixAchat,
           a.prixVente,
           a.stock,
           (a.prixVente - a.prixAchat)                               AS marge_brute,
           ROUND((a.prixVente - a.prixAchat) / a.prixVente * 100, 2) AS marge_pct,
           COALESCE(SUM(av.quantite * av.prixVenteUnite), 0)         AS ca,
           COALESCE(SUM(av.quantite), 0)                             AS qte_vendue,
           COALESCE(SUM(av.quantite * (av.prixVenteUnite - av.prixAchatUnite)), 0) AS marge_realisee
    FROM article a
    LEFT JOIN articlevente av ON av.code = a.code
    LEFT JOIN vente v ON v.idVente = av.idVente
        AND DATE(v.dateVente) BETWEEN ? AND ?
    $whereArtSQL
    GROUP BY a.code, a.nom, a.categorie, a.prixAchat, a.prixVente, a.stock
    ORDER BY $triColonne $triSens
");
$articles->execute(array_merge(array($dateDebut, $dateFin), $paramsArt));
$articles = $articles->fetchAll();

// ── Marge réalisée totale sur la période ─────────────────────
$margePeriode = $pdo->prepare("
    SELECT COALESCE(SUM(marge), 0) AS marge_totale,
           COALESCE(SUM(totale), 0) AS ca_total,
           COUNT(*) AS nb_ventes
    FROM vente
    WHERE DATE(dateVente) BETWEEN ? AND ?
");
$margePeriode->execute(array($dateDebut, $dateFin));
$periode = $margePeriode->fetch();

// ── Synthèse par catégorie ───────────────────────────────────
$synthese = $pdo->query("
    SELECT categorie,
           COUNT(*) AS nb,
           ROUND(AVG((prixVente - prixAchat) / prixVente * 100), 2) AS marge_moy
    FROM article
    WHERE prixAchat > 0 AND categorie IS NOT NULL AND categorie <> ''
    GROUP BY categorie
    ORDER BY marge_moy DESC
")->fetchAll();

$categories = $pdo->query("
    SELECT DISTINCT categorie FROM article
    WHERE categorie IS NOT NULL AND categorie <> '' AND prixAchat > 0
    ORDER BY categorie
")->fetchAll(PDO::FETCH_COLUMN);

function triUrl($col, $courant, $sensActuel) {
    $sens = ($courant === $col && $sensActuel === 'DESC') ? 'asc' : 'desc';
    return '?' . http_build_query(array_merge($_GET, array('tri' => $col, 'sens' => $sens)));
}
function triIcon($col, $courant, $sens) {
    if ($col !== $courant) return '<i class="bi bi-chevron-expand text-muted small"></i>';
    return $sens === 'DESC'
        ? '<i class="bi bi-chevron-down text-primary small"></i>'
        : '<i class="bi bi-chevron-up text-primary small"></i>';
}

require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-percent me-2 text-primary"></i>Calcul de marges</h4>
</div>

<!-- KPI période -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card kpi-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="text-muted small">CA période</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$periode['ca_total'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card kpi-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="text-muted small">Marge réalisée</div>
                    <div class="fs-5 fw-bold text-success"><?= number_format((float)$periode['marge_totale'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card kpi-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-percent"></i></div>
                <div>
                    <div class="text-muted small">Taux de marge</div>
                    <div class="fs-5 fw-bold">
                        <?= $periode['ca_total'] > 0
                            ? round($periode['marge_totale'] / $periode['ca_total'] * 100, 1) . '%'
                            : '—'
                        ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Synthèse par catégorie -->
<?php if (!empty($synthese)): ?>
<div class="row g-2 mb-4">
    <?php foreach ($synthese as $s): $m = (float)$s['marge_moy']; ?>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-2 px-3">
                <div class="text-muted small"><?= htmlspecialchars($s['categorie']) ?></div>
                <span class="fw-bold <?= $m >= 30 ? 'text-success' : ($m >= 10 ? 'text-warning' : 'text-danger') ?>">
                    <?= $m ?>%
                </span>
                <span class="text-muted small ms-1">(<?= (int)$s['nb'] ?> art.)</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Du</label>
                <input type="date" name="date_debut" class="form-control" value="<?= htmlspecialchars($dateDebut) ?>">
            </div>
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Au</label>
                <input type="date" name="date_fin" class="form-control" value="<?= htmlspecialchars($dateFin) ?>">
            </div>
            <div class="col-sm-3">
                <label class="form-label small text-muted mb-1">Catégorie</label>
                <select name="categorie" class="form-select">
                    <option value=""><?= __('toutes') ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $categorie === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <input type="hidden" name="tri" value="<?= htmlspecialchars($triColonne) ?>">
            <input type="hidden" name="sens" value="<?= strtolower($triSens) ?>">
            <div class="col-sm-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary"><?= __('filtrer') ?></button>
                <a href="marges.php" class="btn btn-outline-secondary"><?= __('reset') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau marges -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><a href="<?= triUrl('nom', $triColonne, $triSens) ?>" class="text-dark text-decoration-none">
                            Article <?= triIcon('nom', $triColonne, $triSens) ?>
                        </a></th>
                        <th>Catégorie</th>
                        <th class="text-end">P. achat (DA)</th>
                        <th class="text-end">P. vente (DA)</th>
                        <th class="text-end"><a href="<?= triUrl('marge_brute', $triColonne, $triSens) ?>" class="text-dark text-decoration-none">
                            Marge unit. <?= triIcon('marge_brute', $triColonne, $triSens) ?>
                        </a></th>
                        <th class="text-end"><a href="<?= triUrl('marge_pct', $triColonne, $triSens) ?>" class="text-dark text-decoration-none">
                            Marge % <?= triIcon('marge_pct', $triColonne, $triSens) ?>
                        </a></th>
                        <th class="text-end"><a href="<?= triUrl('ca', $triColonne, $triSens) ?>" class="text-dark text-decoration-none">
                            CA période <?= triIcon('ca', $triColonne, $triSens) ?>
                        </a></th>
                        <th class="text-end">Marge réalisée</th>
                        <th class="text-center">Vendu</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($articles)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">Aucun article avec prix d'achat renseigné</td></tr>
                    <?php else: ?>
                    <?php foreach ($articles as $a):
                        $marge = (float)$a['marge_pct'];
                        $mc = $marge >= 30 ? 'success' : ($marge >= 10 ? 'warning' : 'danger');
                    ?>
                    <tr>
                        <td>
                            <div class="fw-semibold"><?= htmlspecialchars($a['nom']) ?></div>
                            <div class="text-muted small font-monospace"><?= htmlspecialchars($a['code']) ?></div>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(isset($a['categorie']) ? $a['categorie'] : '—') ?></span></td>
                        <td class="text-end"><?= number_format((float)$a['prixAchat'], 2, ',', ' ') ?></td>
                        <td class="text-end"><?= number_format((float)$a['prixVente'], 2, ',', ' ') ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float)$a['marge_brute'], 2, ',', ' ') ?> DA</td>
                        <td class="text-end">
                            <span class="badge bg-<?= $mc ?> <?= $mc === 'warning' ? 'text-dark' : '' ?>">
                                <?= $marge ?>%
                            </span>
                        </td>
                        <td class="text-end"><?= number_format((float)$a['ca'], 2, ',', ' ') ?> DA</td>
                        <td class="text-end text-success fw-semibold"><?= number_format((float)$a['marge_realisee'], 2, ',', ' ') ?> DA</td>
                        <td class="text-center"><?= (int)$a['qte_vendue'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
