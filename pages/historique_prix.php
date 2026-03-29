<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/lang.php';

$pageTitle  = __('hist_prix_titre');
$activePage = 'historique_prix';
$assetBase  = '../';

$pdo = getDB();

// Filtres
$categorie = isset($_GET['categorie']) ? trim($_GET['categorie']) : '';
$search    = isset($_GET['search'])    ? trim($_GET['search'])    : '';

// Catégories disponibles
$categories = $pdo->query("SELECT DISTINCT categorie FROM article WHERE categorie IS NOT NULL AND categorie != '' ORDER BY categorie")->fetchAll();

// Requête principale
$sql = "
    SELECT a.code, a.nom, a.categorie,
           a.prixAchat AS prix_achat_catalogue,
           a.prixVente AS prix_vente_catalogue,
           MIN(av.prixVenteUnite) AS prix_min_vente,
           MAX(av.prixVenteUnite) AS prix_max_vente,
           AVG(av.prixVenteUnite) AS prix_moy_vente,
           COUNT(av.id) AS nb_lignes_vente,
           dernier.dernier_prix
    FROM article a
    LEFT JOIN articlevente av ON av.code = a.code
    LEFT JOIN (
        SELECT av2.code,
               av2.prixVenteUnite AS dernier_prix
        FROM articlevente av2
        JOIN vente v2 ON v2.idVente = av2.idVente
        WHERE v2.dateVente = (
            SELECT MAX(v3.dateVente)
            FROM vente v3
            JOIN articlevente av3 ON av3.idVente = v3.idVente
            WHERE av3.code = av2.code
        )
        GROUP BY av2.code, av2.prixVenteUnite
    ) dernier ON dernier.code = a.code
";

$params  = array();
$wheres  = array();

if ($categorie !== '') {
    $wheres[] = "a.categorie = ?";
    $params[]  = $categorie;
}
if ($search !== '') {
    $wheres[] = "(a.nom LIKE ? OR a.code LIKE ?)";
    $params[]  = '%' . $search . '%';
    $params[]  = '%' . $search . '%';
}

if (!empty($wheres)) {
    $sql .= " WHERE " . implode(' AND ', $wheres);
}

$sql .= " GROUP BY a.code, a.nom, a.categorie, a.prixAchat, a.prixVente, dernier.dernier_prix ORDER BY a.nom";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0">
        <i class="bi bi-clock-history me-2 text-primary"></i>Historique &amp; cohérence des prix
    </h4>
    <a href="../ajax/export.php?type=marges" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- Filtres -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Catégorie</label>
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
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Recherche (nom / code)</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Nom ou code…" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-funnel me-1"></i>Filtrer
                </button>
            </div>
            <?php if ($categorie !== '' || $search !== ''): ?>
            <div class="col-md-2">
                <a href="historique_prix.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x me-1"></i>Effacer
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Légende cohérence -->
<div class="d-flex gap-3 mb-3 small flex-wrap">
    <span><span class="badge bg-success">Cohérent</span> Prix catalogue = dernier prix vendu</span>
    <span><span class="badge bg-warning text-dark">Écart faible</span> Écart &lt; 5 %</span>
    <span><span class="badge bg-danger">Incohérent</span> Écart ≥ 5 % ou aucune vente</span>
</div>

<!-- Table principale -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold border-bottom d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-1 text-primary"></i>Cohérence des prix</span>
        <span class="badge bg-secondary"><?= count($articles) ?> article(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($articles)): ?>
            <p class="text-muted text-center py-4">Aucun article trouvé.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Code</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th class="text-end">Prix achat catalogue</th>
                        <th class="text-end">Prix vente catalogue</th>
                        <th class="text-end">Dernier prix vendu</th>
                        <th class="text-end">Prix min vente</th>
                        <th class="text-end">Prix max vente</th>
                        <th class="text-center">Variation</th>
                        <th class="text-center">Nb ventes</th>
                        <th class="text-center">Cohérence</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $a): ?>
                    <?php
                    $prixCatalogue = (float)$a['prix_vente_catalogue'];
                    $dernierPrix   = isset($a['dernier_prix']) && $a['dernier_prix'] !== null ? (float)$a['dernier_prix'] : null;
                    $prixMin       = $a['prix_min_vente'] !== null ? (float)$a['prix_min_vente'] : null;
                    $prixMax       = $a['prix_max_vente'] !== null ? (float)$a['prix_max_vente'] : null;

                    // Variation (prix_max - prix_min)
                    $variation = null;
                    $variationBadge = '<span class="text-muted">—</span>';
                    if ($prixMin !== null && $prixMax !== null) {
                        $variation = $prixMax - $prixMin;
                        if ($variation == 0) {
                            $variationBadge = '<span class="badge bg-light text-dark border">Stable</span>';
                        } else {
                            $variationBadge = '<span class="badge bg-warning text-dark">± ' . number_format($variation, 2, ',', ' ') . ' DA</span>';
                        }
                    }

                    // Cohérence
                    $coherenceBadge = '<span class="badge bg-danger">Aucune vente</span>';
                    if ($dernierPrix !== null && $prixCatalogue > 0) {
                        $ecartPct = abs($dernierPrix - $prixCatalogue) / $prixCatalogue * 100;
                        if ($ecartPct < 0.01) {
                            $coherenceBadge = '<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Cohérent</span>';
                        } elseif ($ecartPct < 5) {
                            $coherenceBadge = '<span class="badge bg-warning text-dark"><i class="bi bi-dash-circle me-1"></i>Écart faible (' . number_format($ecartPct, 1, ',', '') . ' %)</span>';
                        } else {
                            $coherenceBadge = '<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Incohérent (' . number_format($ecartPct, 1, ',', '') . ' %)</span>';
                        }
                    } elseif ($dernierPrix !== null) {
                        $coherenceBadge = '<span class="badge bg-secondary">Catalogue à 0</span>';
                    }
                    ?>
                    <tr>
                        <td class="text-muted small"><?= htmlspecialchars($a['code']) ?></td>
                        <td><?= htmlspecialchars($a['nom']) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($a['categorie']) ?></span></td>
                        <td class="text-end small"><?= number_format((float)$a['prix_achat_catalogue'], 2, ',', ' ') ?> DA</td>
                        <td class="text-end small fw-semibold"><?= number_format($prixCatalogue, 2, ',', ' ') ?> DA</td>
                        <td class="text-end small <?= $dernierPrix !== null && abs($dernierPrix - $prixCatalogue) > 0.01 ? 'text-warning fw-semibold' : '' ?>">
                            <?= $dernierPrix !== null ? number_format($dernierPrix, 2, ',', ' ') . ' DA' : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td class="text-end small text-muted">
                            <?= $prixMin !== null ? number_format($prixMin, 2, ',', ' ') . ' DA' : '—' ?>
                        </td>
                        <td class="text-end small text-muted">
                            <?= $prixMax !== null ? number_format($prixMax, 2, ',', ' ') . ' DA' : '—' ?>
                        </td>
                        <td class="text-center"><?= $variationBadge ?></td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= (int)$a['nb_lignes_vente'] ?></span>
                        </td>
                        <td class="text-center"><?= $coherenceBadge ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
