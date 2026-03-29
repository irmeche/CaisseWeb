<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/lang.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo '<div class="alert alert-danger">Client introuvable.</div>';
    exit;
}

$pdo = getDB();

// Infos client
$stmtClient = $pdo->prepare("SELECT numero, nom, prenom FROM client WHERE numero = ?");
$stmtClient->execute(array($id));
$client = $stmtClient->fetch();

if (!$client) {
    echo '<div class="alert alert-danger">Client introuvable.</div>';
    exit;
}

// Statistiques globales
$stmtStats = $pdo->prepare("
    SELECT COUNT(*) AS nb_achats,
           COALESCE(SUM(totale), 0) AS total_depense,
           COALESCE(AVG(totale), 0) AS panier_moyen
    FROM vente
    WHERE CAST(client AS UNSIGNED) = ?
");
$stmtStats->execute(array($id));
$stats = $stmtStats->fetch();

// Liste des ventes
$stmtVentes = $pdo->prepare("
    SELECT idVente, dateVente, loginVendeur, totale, marge, credit
    FROM vente
    WHERE CAST(client AS UNSIGNED) = ?
    ORDER BY dateVente DESC
    LIMIT 50
");
$stmtVentes->execute(array($id));
$ventes = $stmtVentes->fetchAll();
?>
<div class="mb-3 pb-2 border-bottom">
    <h6 class="fw-bold mb-1">
        <i class="bi bi-person-circle me-1 text-primary"></i>
        <?= htmlspecialchars($client['prenom'] . ' ' . $client['nom']) ?>
        <span class="text-muted fw-normal small ms-2">#<?= (int)$client['numero'] ?></span>
    </h6>
    <div class="row g-2 mt-1">
        <div class="col-4 text-center">
            <div class="small text-muted">Achats</div>
            <div class="fw-bold"><?= (int)$stats['nb_achats'] ?></div>
        </div>
        <div class="col-4 text-center">
            <div class="small text-muted">Total dépensé</div>
            <div class="fw-bold text-primary"><?= number_format((float)$stats['total_depense'], 2, ',', ' ') ?> DA</div>
        </div>
        <div class="col-4 text-center">
            <div class="small text-muted">Panier moyen</div>
            <div class="fw-bold text-info"><?= number_format((float)$stats['panier_moyen'], 2, ',', ' ') ?> DA</div>
        </div>
    </div>
</div>

<?php if (empty($ventes)): ?>
    <p class="text-muted text-center py-3 small">Aucun achat enregistré pour ce client.</p>
<?php else: ?>
<div class="table-responsive" style="max-height:400px; overflow-y:auto;">
    <table class="table table-sm table-hover mb-0">
        <thead class="table-light sticky-top">
            <tr>
                <th>Date</th>
                <th>Vendeur</th>
                <th class="text-end">Total</th>
                <th class="text-end">Marge</th>
                <th class="text-center">Crédit</th>
                <th class="text-center">Détail</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($ventes as $v): ?>
            <tr>
                <td class="small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($v['dateVente']))) ?></td>
                <td class="small"><?= htmlspecialchars($v['loginVendeur']) ?></td>
                <td class="text-end fw-semibold"><?= number_format((float)$v['totale'], 2, ',', ' ') ?> DA</td>
                <td class="text-end small text-success"><?= number_format((float)$v['marge'], 2, ',', ' ') ?> DA</td>
                <td class="text-center">
                    <?php if ($v['credit'] > 0): ?>
                        <span class="badge bg-warning text-dark"><?= number_format((float)$v['credit'], 2, ',', ' ') ?> DA</span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <a href="ajax/vente_detail.php?id=<?= (int)$v['idVente'] ?>"
                       class="btn btn-xs btn-outline-primary"
                       style="font-size:0.7rem; padding:1px 6px;"
                       target="_blank">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
