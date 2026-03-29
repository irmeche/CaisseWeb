<?php
require_once '../config.php';
require_once '../includes/lang.php';

$pageTitle  = __('stocks_titre');
$activePage = 'stocks';
$assetBase  = '../';

$pdo = getDB();
$message     = null;
$messageType = 'success';

// ── Enregistrement d'une entrée de stock (réappro) ───────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action      = $_POST['action'];
    $codeArticle = trim(isset($_POST['codeArticle']) ? $_POST['codeArticle'] : '');
    $nombreUnite = (int)(isset($_POST['nombreUnite']) ? $_POST['nombreUnite'] : 0);
    $prixUnite   = (float)str_replace(',', '.', isset($_POST['prixUnite']) ? $_POST['prixUnite'] : '0');

    if ($codeArticle === '' || $nombreUnite <= 0) {
        $message     = 'Article et quantité obligatoires.';
        $messageType = 'danger';
    } else {
        $stmtNom = $pdo->prepare("SELECT nom FROM article WHERE code = ?");
        $stmtNom->execute(array($codeArticle));
        $nomArticle = $stmtNom->fetchColumn();

        if (!$nomArticle) {
            $message     = 'Article introuvable.';
            $messageType = 'danger';
        } else {
            if ($action === 'entree') {
                $pdo->prepare("
                    INSERT INTO stock (codeArticle, dateAchat, nomArticle, nombreUnite, prixUnite)
                    VALUES (?, NOW(), ?, ?, ?)
                ")->execute(array($codeArticle, $nomArticle, $nombreUnite, $prixUnite));

                $pdo->prepare("UPDATE article SET stock = stock + ? WHERE code = ?")
                    ->execute(array($nombreUnite, $codeArticle));

                $message = "Entree de $nombreUnite unite(s) enregistree pour $nomArticle.";

            } elseif ($action === 'ajustement') {
                $nouveauStock = (int)(isset($_POST['nouveauStock']) ? $_POST['nouveauStock'] : 0);
                $pdo->prepare("UPDATE article SET stock = ? WHERE code = ?")
                    ->execute(array($nouveauStock, $codeArticle));
                $message = "Stock de $nomArticle ajuste a $nouveauStock unite(s).";
            }
        }
    }
}

// ── Filtre état stock ────────────────────────────────────────
$filtre = isset($_GET['filtre']) ? $_GET['filtre'] : 'tous';
$whereMap = array(
    'rupture' => 'WHERE stock = 0',
    'faible'  => 'WHERE stock > 0 AND stock <= seuilAlerte',
    'ok'      => 'WHERE stock > seuilAlerte',
    'tous'    => '',
);
$whereSQL = isset($whereMap[$filtre]) ? $whereMap[$filtre] : '';

$articles = $pdo->query("
    SELECT code, nom, categorie, stock, seuilAlerte
    FROM article
    $whereSQL
    ORDER BY stock ASC, nom ASC
")->fetchAll();

// ── Historique des entrées de stock ─────────────────────────
$historique = $pdo->query("
    SELECT s.id, s.nomArticle, s.nombreUnite, s.prixUnite, s.dateAchat, s.codeArticle
    FROM stock s
    ORDER BY s.dateAchat DESC
    LIMIT 30
")->fetchAll();

// ── Liste articles pour le formulaire ────────────────────────
$listeArticles = $pdo->query("SELECT code, nom, stock FROM article ORDER BY nom")->fetchAll();

require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam me-2 text-primary"></i>Gestion des stocks</h4>
    <div class="d-flex gap-2">
        <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#entreeModal">
            <i class="bi bi-plus-lg me-1"></i>Entrée stock
        </button>
        <button class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#ajustModal">
            <i class="bi bi-pencil me-1"></i>Ajustement
        </button>
    </div>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?> alert-dismissible auto-dismiss fade show">
    <?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Filtres rapides -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php foreach (array('tous' => 'Tous', 'rupture' => 'Rupture', 'faible' => 'Stock faible', 'ok' => 'OK') as $val => $label): ?>
    <a href="?filtre=<?= $val ?>"
       class="btn btn-sm <?= $filtre === $val ? 'btn-dark' : 'btn-outline-secondary' ?>">
        <?= $label ?>
    </a>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <!-- Tableau stocks -->
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom d-flex justify-content-between align-items-center">
                <span>Articles (<?= count($articles) ?>)</span>
                <input type="text" id="tableSearch" class="form-control form-control-sm"
                       placeholder="Rechercher…" style="max-width:180px">
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" data-searchable>
                        <thead class="table-light">
                            <tr>
                                <th>Article</th>
                                <th>Catégorie</th>
                                <th class="text-center">Stock</th>
                                <th class="text-center">Seuil alerte</th>
                                <th class="text-center">État</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($articles)): ?>
                            <tr><td colspan="5" class="text-center text-muted py-4">Aucun article</td></tr>
                            <?php else: ?>
                            <?php foreach ($articles as $a):
                                $s = (int)$a['stock'];
                                $seuil = (int)$a['seuilAlerte'];
                            ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($a['nom']) ?></div>
                                    <div class="text-muted small font-monospace"><?= htmlspecialchars($a['code']) ?></div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars(isset($a['categorie']) ? $a['categorie'] : '—') ?></span></td>
                                <td class="text-center fs-6 fw-bold"><?= $s ?></td>
                                <td class="text-center text-muted"><?= $seuil ?></td>
                                <td class="text-center">
                                    <?php if ($s === 0): ?>
                                        <span class="badge badge-stock-empty px-2 py-1">Rupture</span>
                                    <?php elseif ($s <= $seuil): ?>
                                        <span class="badge badge-stock-low px-2 py-1">Faible</span>
                                    <?php else: ?>
                                        <span class="badge badge-stock-ok px-2 py-1">OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Historique réappros -->
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold border-bottom">
                <i class="bi bi-clock-history me-1"></i> Historique des entrées
            </div>
            <div class="card-body p-0">
                <?php if (empty($historique)): ?>
                    <p class="text-muted text-center py-4 small">Aucune entrée enregistrée</p>
                <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($historique as $h): ?>
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge bg-success me-1">+<?= (int)$h['nombreUnite'] ?></span>
                                <span class="small fw-semibold"><?= htmlspecialchars($h['nomArticle']) ?></span>
                                <?php if ($h['prixUnite'] > 0): ?>
                                <div class="text-muted small ms-1">
                                    <?= number_format((float)$h['prixUnite'], 2, ',', ' ') ?> DA/u
                                    &mdash; Total : <?= number_format($h['nombreUnite'] * $h['prixUnite'], 2, ',', ' ') ?> DA
                                </div>
                                <?php endif; ?>
                            </div>
                            <span class="text-muted small"><?= date('d/m H:i', strtotime($h['dateAchat'])) ?></span>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal entrée stock -->
<div class="modal fade" id="entreeModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="entree">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-plus-circle text-success me-1"></i>Entrée de stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small">Article <span class="text-danger">*</span></label>
                        <select name="codeArticle" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($listeArticles as $a): ?>
                            <option value="<?= htmlspecialchars($a['code']) ?>">
                                <?= htmlspecialchars($a['nom']) ?> (stock : <?= (int)$a['stock'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small">Quantité reçue <span class="text-danger">*</span></label>
                        <input type="number" name="nombreUnite" class="form-control" min="1" required>
                    </div>
                    <div>
                        <label class="form-label small">Prix unitaire d'achat (DA)</label>
                        <input type="number" name="prixUnite" class="form-control" step="0.01" min="0" placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= __('annuler') ?></button>
                    <button type="submit" class="btn btn-success btn-sm"><?= __('enregistrer') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ajustement stock -->
<div class="modal fade" id="ajustModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <form method="post">
                <input type="hidden" name="action" value="ajustement">
                <div class="modal-header">
                    <h5 class="modal-title fs-6 fw-bold"><i class="bi bi-pencil text-warning me-1"></i>Ajustement manuel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Corriger un stock après inventaire physique.</p>
                    <div class="mb-3">
                        <label class="form-label small">Article <span class="text-danger">*</span></label>
                        <select name="codeArticle" class="form-select" required>
                            <option value="">— Choisir —</option>
                            <?php foreach ($listeArticles as $a): ?>
                            <option value="<?= htmlspecialchars($a['code']) ?>">
                                <?= htmlspecialchars($a['nom']) ?> (actuel : <?= (int)$a['stock'] ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="form-label small">Nouveau stock réel <span class="text-danger">*</span></label>
                        <input type="number" name="nouveauStock" class="form-control" min="0" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= __('annuler') ?></button>
                    <button type="submit" class="btn btn-warning btn-sm">Ajuster</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
