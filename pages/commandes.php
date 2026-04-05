<?php
require_once '../config.php';
require_once '../includes/lang.php';

$pageTitle  = __('commandes_titre');
$activePage = 'commandes';
$assetBase  = '../';

$pdo = getDB();

// ── Filtres ──────────────────────────────────────────────────
$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-d');
$dateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');
$vendeur   = trim(isset($_GET['vendeur']) ? $_GET['vendeur'] : '');
$codeArt   = trim(isset($_GET['code'])   ? $_GET['code']    : '');

// Conditions sur vente directement
$whereV  = array('DATE(v.dateVente) BETWEEN ? AND ?');
$paramsV = array($dateDebut, $dateFin);

if ($vendeur !== '') {
    $whereV[]  = 'v.loginVendeur = ?';
    $paramsV[] = $vendeur;
}
// Filtre article : sous-requête pour ne pas polluer les agrégats
if ($codeArt !== '') {
    $whereV[]  = 'v.idVente IN (SELECT idVente FROM articlevente WHERE code = ?)';
    $paramsV[] = $codeArt;
}
$whereSQL = implode(' AND ', $whereV);

// ── Ventes de la période ─────────────────────────────────────
$stmtVentes = $pdo->prepare("
    SELECT v.idVente,
           v.dateVente,
           v.totale,
           v.loginVendeur,
           v.recu,
           v.rendu,
           (SELECT COUNT(*) FROM articlevente WHERE idVente = v.idVente) AS nb_articles,
           (SELECT COALESCE(SUM(quantite * (prixVenteUnite - prixAchatUnite)), 0)
            FROM articlevente WHERE idVente = v.idVente) AS marge
    FROM vente v
    WHERE $whereSQL
    ORDER BY v.dateVente DESC
");
$stmtVentes->execute($paramsV);
$ventes = $stmtVentes->fetchAll();

// ── Totaux ───────────────────────────────────────────────────
$stmtTotaux = $pdo->prepare("
    SELECT COALESCE(SUM(v.totale), 0) AS ca_total,
           COALESCE(SUM(
               (SELECT COALESCE(SUM(quantite * (prixVenteUnite - prixAchatUnite)), 0)
                FROM articlevente WHERE idVente = v.idVente)
           ), 0) AS marge_total,
           COUNT(*) AS nb_ventes
    FROM vente v
    WHERE $whereSQL
");
$stmtTotaux->execute($paramsV);
$totaux = $stmtTotaux->fetch();

// ── Dernières ventes ─────────────────────────────────────────
$dernieresVentes = $pdo->query("
    SELECT idVente, dateVente, totale, loginVendeur
    FROM vente ORDER BY dateVente DESC LIMIT 10
")->fetchAll();

// ── Listes pour filtres ──────────────────────────────────────
$vendeurs = $pdo->query("
    SELECT DISTINCT loginVendeur FROM vente
    WHERE loginVendeur IS NOT NULL ORDER BY loginVendeur
")->fetchAll(PDO::FETCH_COLUMN);

$articles = $pdo->query("SELECT code, nom FROM article ORDER BY nom")->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-receipt me-2 text-primary"></i>Reporting des ventes</h4>
</div>

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
                <label class="form-label small text-muted mb-1">Article</label>
                <select name="code" class="form-select">
                    <option value=""><?= __('tous_articles') ?></option>
                    <?php foreach ($articles as $a): ?>
                    <option value="<?= htmlspecialchars($a['code']) ?>" <?= $codeArt === $a['code'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($a['nom']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-2">
                <label class="form-label small text-muted mb-1">Vendeur</label>
                <select name="vendeur" class="form-select">
                    <option value=""><?= __('tous') ?></option>
                    <?php foreach ($vendeurs as $vd): ?>
                    <option value="<?= htmlspecialchars($vd) ?>" <?= $vendeur === $vd ? 'selected' : '' ?>>
                        <?= htmlspecialchars($vd) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><?= __('filtrer') ?></button>
                <a href="commandes.php" class="btn btn-outline-secondary"><?= __('reset') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- KPI période -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card kpi-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-cash"></i></div>
                <div>
                    <div class="text-muted small">CA période</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$totaux['ca_total'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card kpi-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-receipt"></i></div>
                <div>
                    <div class="text-muted small">Nb ventes</div>
                    <div class="fs-5 fw-bold"><?= (int)$totaux['nb_ventes'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card kpi-card border-0 shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-graph-up-arrow"></i></div>
                <div>
                    <div class="text-muted small">Marge totale</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$totaux['marge_total'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tableau ventes -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date / Heure</th>
                        <th>Vendeur</th>
                        <th class="text-center">Articles</th>
                        <th class="text-end">Reçu (DA)</th>
                        <th class="text-end">Total (DA)</th>
                        <th class="text-end">Marge (DA)</th>
                        <th class="text-center">Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($ventes)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Aucune vente sur cette période</td></tr>
                    <?php else: ?>
                    <?php foreach ($ventes as $v): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$v['idVente'] ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['dateVente']))) ?></td>
                        <td>
                            <?php if ($v['loginVendeur']): ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['loginVendeur']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center"><?= (int)$v['nb_articles'] ?></td>
                        <td class="text-end text-muted"><?= number_format((float)$v['recu'], 2, ',', ' ') ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float)$v['totale'], 2, ',', ' ') ?></td>
                        <td class="text-end text-success"><?= number_format((float)$v['marge'], 2, ',', ' ') ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#detailModal"
                                    data-vente-id="<?= $v['idVente'] ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Dernières ventes -->
<div class="card border-0 shadow-sm mt-4">
    <div class="card-header bg-white fw-semibold border-bottom">
        <i class="bi bi-clock-history me-1 text-success"></i>Dernières ventes
    </div>
    <div class="card-body p-0">
        <?php if (empty($dernieresVentes)): ?>
            <p class="text-muted text-center py-4 small">Aucune vente enregistrée.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Date / Heure</th>
                        <th>Vendeur</th>
                        <th class="text-end">Total (DA)</th>
                        <th class="text-center">Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dernieresVentes as $v): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$v['idVente'] ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['dateVente']))) ?></td>
                        <td>
                            <?php if ($v['loginVendeur']): ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($v['loginVendeur']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end fw-semibold"><?= number_format((float)$v['totale'], 2, ',', ' ') ?></td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#detailModal"
                                    data-vente-id="<?= $v['idVente'] ?>">
                                <i class="bi bi-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal détail -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">Détail de la vente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailContent">
                <div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('detailModal').addEventListener('show.bs.modal', function (e) {
    var venteId = e.relatedTarget.dataset.venteId;
    var content = document.getElementById('detailContent');
    content.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    fetch('../ajax/vente_detail.php?id=' + venteId)
        .then(function(r) { return r.text(); })
        .then(function(html) { content.innerHTML = html; })
        .catch(function() { content.innerHTML = '<p class="text-danger">Erreur de chargement</p>'; });
});
</script>

<?php require_once '../includes/footer.php'; ?>
