<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/lang.php';

$pageTitle  = __('inventaire_titre');
$activePage = 'inventaire';
$assetBase  = '../';

$pdo = getDB();

$message = '';
$messageType = 'success';

// Traitement POST inventaire
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stocks']) && is_array($_POST['stocks'])) {
    $nb = 0;
    $stmtUpdate = $pdo->prepare("UPDATE article SET stock = ?, dateMAJ = NOW() WHERE code = ?");
    foreach ($_POST['stocks'] as $code => $quantite) {
        if (is_numeric($quantite) && $quantite >= 0) {
            $stmtUpdate->execute(array((int)$quantite, $code));
            $nb++;
        }
    }
    $message     = $nb . ' article(s) mis à jour avec succès.';
    $messageType = 'success';
}

// Filtre catégorie
$categorie = isset($_GET['categorie']) ? $_GET['categorie'] : '';

// Liste des catégories
$categories = $pdo->query("SELECT DISTINCT categorie FROM article WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie")->fetchAll();

// KPI stock
$stmtKpi = $pdo->query("
    SELECT
        COALESCE(SUM(stock * prixAchat), 0) AS valeur_stock,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) AS nb_rupture,
        SUM(CASE WHEN stock > 0 AND stock <= seuilAlerte THEN 1 ELSE 0 END) AS nb_sous_seuil
    FROM article
");
$kpi = $stmtKpi->fetch();

// Articles
$sqlArticles = "SELECT code, nom, categorie, stock, seuilAlerte, prixAchat, prixVente FROM article";
$paramsArticles = array();
if ($categorie !== '') {
    $sqlArticles .= " WHERE categorie = ?";
    $paramsArticles[] = $categorie;
}
$sqlArticles .= " ORDER BY nom";

$stmtArticles = $pdo->prepare($sqlArticles);
$stmtArticles->execute($paramsArticles);
$articles = $stmtArticles->fetchAll();

// Valeur totale du stock filtré
$valeurFiltree = 0;
foreach ($articles as $a) {
    $valeurFiltree += (float)$a['stock'] * (float)$a['prixAchat'];
}

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-clipboard-check me-2 text-primary"></i>Inventaire</h4>
    <a href="../ajax/export.php?type=stocks" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible fade show d-flex align-items-center gap-2 mb-4" role="alert">
    <i class="bi bi-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>-fill"></i>
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
</div>
<?php endif; ?>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-currency-euro"></i></div>
                <div>
                    <div class="text-muted small">Valeur totale du stock</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['valeur_stock'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-danger bg-opacity-10 text-danger"><i class="bi bi-x-circle"></i></div>
                <div>
                    <div class="text-muted small">Articles en rupture</div>
                    <div class="fs-5 fw-bold text-danger"><?= (int)$kpi['nb_rupture'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-4">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-exclamation-triangle"></i></div>
                <div>
                    <div class="text-muted small">Articles sous seuil</div>
                    <div class="fs-5 fw-bold text-warning"><?= (int)$kpi['nb_sous_seuil'] ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filtre catégorie -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Filtrer par catégorie</label>
                <select name="categorie" class="form-select">
                    <option value="">— <?= __('toutes_categories') ?> —</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['categorie']) ?>"
                            <?= $categorie === $cat['categorie'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['categorie']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
            </div>
            <?php if ($categorie !== ''): ?>
            <div class="col-md-2">
                <a href="inventaire.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x me-1"></i>Effacer
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Formulaire inventaire -->
<form method="POST" action="inventaire.php<?= $categorie ? '?categorie=' . urlencode($categorie) : '' ?>">
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold border-bottom d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-1 text-primary"></i>Saisie des stocks réels</span>
        <span class="badge bg-secondary"><?= count($articles) ?> article(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($articles)): ?>
            <p class="text-muted text-center py-4">Aucun article trouvé.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle" id="tableInventaire">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th class="text-end">Prix achat</th>
                        <th class="text-end">Prix vente</th>
                        <th class="text-center">Seuil alerte</th>
                        <th class="text-center">Stock actuel</th>
                        <th class="text-center" style="width:130px;">Stock réel (saisie)</th>
                        <th class="text-center ecart-cell">Écart</th>
                        <th class="text-end">Valeur</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                    <?php
                    $stockClass = '';
                    if ((int)$a['stock'] === 0) {
                        $stockClass = 'badge-stock-empty';
                    } elseif ((float)$a['stock'] <= (float)$a['seuilAlerte']) {
                        $stockClass = 'badge-stock-low';
                    } else {
                        $stockClass = 'badge-stock-ok';
                    }
                    ?>
                    <tr>
                        <td class="text-muted small"><?= htmlspecialchars($a['code']) ?></td>
                        <td><?= htmlspecialchars($a['nom']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['categorie']) ?></span></td>
                        <td class="text-end small"><?= number_format((float)$a['prixAchat'], 2, ',', ' ') ?> DA</td>
                        <td class="text-end small"><?= number_format((float)$a['prixVente'], 2, ',', ' ') ?> DA</td>
                        <td class="text-center small text-muted"><?= (int)$a['seuilAlerte'] ?></td>
                        <td class="text-center">
                            <span class="badge <?= $stockClass ?> px-2 py-1"><?= (int)$a['stock'] ?></span>
                        </td>
                        <td class="text-center">
                            <input type="number" name="stocks[<?= htmlspecialchars($a['code']) ?>]"
                                   class="form-control form-control-sm text-center stock-input"
                                   value="<?= (int)$a['stock'] ?>"
                                   min="0" style="width:90px; margin:auto;">
                        </td>
                        <td class="text-center fw-bold text-muted ecart-cell">0</td>
                        <td class="text-end small valeur-cell">
                            <?= number_format((float)$a['stock'] * (float)$a['prixAchat'], 2, ',', ' ') ?> DA
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="9" class="text-end">Valeur totale du stock (filtré) :</td>
                        <td class="text-end" id="valeurTotale">
                            <?= number_format($valeurFiltree, 2, ',', ' ') ?> DA
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!empty($articles)): ?>
    <div class="card-footer bg-white border-top d-flex justify-content-end gap-2">
        <a href="inventaire.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-counterclockwise me-1"></i>Annuler
        </a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle me-1"></i>Valider l'inventaire
        </button>
    </div>
    <?php endif; ?>
</div>
</form>

<script>
(function() {
    // Données prix d'achat par code
    var prixAchat = {};
    <?php foreach ($articles as $a): ?>
    prixAchat[<?= json_encode($a['code']) ?>] = <?= (float)$a['prixAchat'] ?>;
    <?php endforeach; ?>

    document.querySelectorAll('input[name^="stocks["]').forEach(function(input) {
        var original = parseInt(input.defaultValue);

        input.addEventListener('input', function() {
            var val   = parseInt(this.value);
            if (isNaN(val)) val = 0;
            var ecart = val - original;
            var cell  = this.closest('tr').querySelector('.ecart-cell');
            cell.textContent = (ecart >= 0 ? '+' : '') + ecart;
            cell.className = 'ecart-cell text-center fw-bold ' + (ecart > 0 ? 'text-success' : (ecart < 0 ? 'text-danger' : 'text-muted'));
        });
    });

    // Recalcul valeur totale en temps réel
    function recalcTotal() {
        var total = 0;
        document.querySelectorAll('input[name^="stocks["]').forEach(function(input) {
            var nameAttr = input.getAttribute('name');
            var code = nameAttr.replace('stocks[', '').replace(']', '');
            var qty  = parseInt(input.value) || 0;
            var prix = prixAchat[code] || 0;
            total += qty * prix;
        });
        document.getElementById('valeurTotale').textContent = total.toLocaleString('fr-FR', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' DA';
    }

    document.querySelectorAll('input[name^="stocks["]').forEach(function(input) {
        input.addEventListener('input', recalcTotal);
    });
})();
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
