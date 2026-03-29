<?php
require_once '../config.php';
require_once '../includes/lang.php';

$pageTitle  = __('prix_titre');
$activePage = 'prix';
$assetBase  = '../';

$pdo = getDB();
$message     = null;
$messageType = 'success';

// ── Mise à jour prix ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code      = trim($_POST['code']);
    $prixVente = (float)str_replace(',', '.', isset($_POST['prixVente']) ? $_POST['prixVente'] : '0');
    $prixAchat = (float)str_replace(',', '.', isset($_POST['prixAchat']) ? $_POST['prixAchat'] : '0');

    if ($prixVente <= 0) {
        $message     = 'Le prix de vente doit être supérieur à 0.';
        $messageType = 'danger';
    } else {
        $pdo->prepare("
            UPDATE article
            SET prixVente = ?, prixAchat = ?, dateMAJ = NOW()
            WHERE code = ?
        ")->execute(array($prixVente, $prixAchat, $code));
        $message = 'Prix mis à jour avec succès.';
    }
}

// ── Filtres ──────────────────────────────────────────────────
$search    = trim(isset($_GET['q'])         ? $_GET['q']         : '');
$categorie = trim(isset($_GET['categorie']) ? $_GET['categorie'] : '');

$where  = array();
$params = array();
if ($search !== '') {
    $where[]  = '(nom LIKE ? OR code LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categorie !== '') {
    $where[]  = 'categorie = ?';
    $params[] = $categorie;
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$articles = $pdo->prepare("
    SELECT code, nom, categorie, prixAchat, prixVente, stock, seuilAlerte
    FROM article
    $whereSQL
    ORDER BY nom ASC
");
$articles->execute($params);
$articles = $articles->fetchAll();

$categories = $pdo->query("
    SELECT DISTINCT categorie FROM article
    WHERE categorie IS NOT NULL AND categorie <> ''
    ORDER BY categorie
")->fetchAll(PDO::FETCH_COLUMN);

require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-tags me-2 text-primary"></i>Prix des articles</h4>
    <span class="text-muted small"><?= count($articles) ?> article(s)</span>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible auto-dismiss fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <div class="col-sm-5">
                <label class="form-label small text-muted mb-1">Recherche</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Nom ou code…"
                           value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-sm-4">
                <label class="form-label small text-muted mb-1">Catégorie</label>
                <select name="categorie" class="form-select">
                    <option value=""><?= __('toutes_categories') ?></option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>" <?= $categorie === $cat ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-fill"><?= __('filtrer') ?></button>
                <a href="prix.php" class="btn btn-outline-secondary"><?= __('reset') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Tableau -->
<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" data-searchable>
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Article</th>
                        <th>Catégorie</th>
                        <th class="text-end">P. achat (DA)</th>
                        <th class="text-end">P. vente (DA)</th>
                        <th class="text-end">Marge</th>
                        <th class="text-center">Stock</th>
                        <th class="text-center">Modifier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($articles)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-4">Aucun article trouvé</td></tr>
                    <?php else: ?>
                    <?php foreach ($articles as $a):
                        $marge = $a['prixVente'] > 0
                            ? round(($a['prixVente'] - $a['prixAchat']) / $a['prixVente'] * 100, 1)
                            : null;
                        $s = (int)$a['stock'];
                    ?>
                    <tr>
                        <td class="text-muted small font-monospace"><?= htmlspecialchars($a['code']) ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($a['nom']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(isset($a['categorie']) ? $a['categorie'] : '—') ?></span></td>
                        <td class="text-end"><?= $a['prixAchat'] > 0 ? number_format((float)$a['prixAchat'], 2, ',', ' ') : '<span class="text-muted">—</span>' ?></td>
                        <td class="text-end fw-semibold"><?= number_format((float)$a['prixVente'], 2, ',', ' ') ?></td>
                        <td class="text-end">
                            <?php if ($marge !== null && $a['prixAchat'] > 0): ?>
                                <span class="badge <?= $marge >= 30 ? 'bg-success' : ($marge >= 10 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= $marge ?>%
                                </span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge <?= $s === 0 ? 'badge-stock-empty' : ($s <= (int)$a['seuilAlerte'] ? 'badge-stock-low' : 'badge-stock-ok') ?> px-2 py-1">
                                <?= $s ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-outline-primary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editModal"
                                    data-code="<?= htmlspecialchars($a['code']) ?>"
                                    data-nom="<?= htmlspecialchars($a['nom']) ?>"
                                    data-prix-vente="<?= $a['prixVente'] ?>"
                                    data-prix-achat="<?= $a['prixAchat'] ?>">
                                <i class="bi bi-pencil"></i>
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

<!-- Modal édition -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold">Modifier le prix</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="code" id="editCode">
                    <p class="fw-semibold text-primary mb-1" id="editNom"></p>
                    <p class="text-muted small mb-3" id="editCodeLabel"></p>
                    <div class="mb-3">
                        <label class="form-label small">Prix d'achat (DA)</label>
                        <input type="number" name="prixAchat" id="editPrixAchat"
                               step="0.01" min="0" class="form-control">
                    </div>
                    <div>
                        <label class="form-label small">Prix de vente (DA) <span class="text-danger">*</span></label>
                        <input type="number" name="prixVente" id="editPrixVente"
                               step="0.01" min="0.01" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= __('annuler') ?></button>
                    <button type="submit" class="btn btn-primary btn-sm"><?= __('enregistrer') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('editModal').addEventListener('show.bs.modal', function (e) {
    var btn = e.relatedTarget;
    document.getElementById('editCode').value        = btn.dataset.code;
    document.getElementById('editNom').textContent   = btn.dataset.nom;
    document.getElementById('editCodeLabel').textContent = 'Code : ' + btn.dataset.code;
    document.getElementById('editPrixVente').value   = btn.dataset.prixVente;
    document.getElementById('editPrixAchat').value   = btn.dataset.prixAchat || '';
});
</script>

<?php require_once '../includes/footer.php'; ?>
