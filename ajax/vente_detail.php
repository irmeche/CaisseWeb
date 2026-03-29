<?php
require_once '../config.php';
require_once '../includes/lang.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) { echo '<p class="text-danger">ID invalide</p>'; exit; }

$pdo = getDB();

$vente = $pdo->prepare("SELECT * FROM vente WHERE idVente = ?");
$vente->execute([$id]);
$vente = $vente->fetch();

if (!$vente) { echo '<p class="text-muted">Vente introuvable</p>'; exit; }

$lignes = $pdo->prepare("
    SELECT code, nom, quantite, prixAchatUnite, prixVenteUnite
    FROM articlevente
    WHERE idVente = ?
    ORDER BY nom
");
$lignes->execute([$id]);
$lignes = $lignes->fetchAll();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <span class="text-muted small">Vente #<?= $id ?></span>
        <span class="ms-2 text-muted small"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($vente['dateVente']))) ?></span>
    </div>
    <?php if ($vente['loginVendeur']): ?>
    <span class="badge bg-secondary"><?= htmlspecialchars($vente['loginVendeur']) ?></span>
    <?php endif; ?>
</div>

<table class="table table-sm table-bordered mb-3">
    <thead class="table-light">
        <tr>
            <th>Article</th>
            <th>Code</th>
            <th class="text-center">Qté</th>
            <th class="text-end">P.V. unitaire</th>
            <th class="text-end">Total</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($lignes as $l): ?>
        <tr>
            <td><?= htmlspecialchars($l['nom']) ?></td>
            <td class="text-muted small font-monospace"><?= htmlspecialchars($l['code']) ?></td>
            <td class="text-center"><?= (int)$l['quantite'] ?></td>
            <td class="text-end"><?= number_format((float)$l['prixVenteUnite'], 2, ',', ' ') ?> DA</td>
            <td class="text-end fw-semibold"><?= number_format($l['quantite'] * $l['prixVenteUnite'], 2, ',', ' ') ?> DA</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
    <tfoot class="table-light">
        <tr>
            <td colspan="4" class="text-end fw-bold">Total</td>
            <td class="text-end fw-bold"><?= number_format((float)$vente['totale'], 2, ',', ' ') ?> DA</td>
        </tr>
        <tr>
            <td colspan="4" class="text-end text-muted small">Reçu</td>
            <td class="text-end text-muted small"><?= number_format((float)$vente['recu'], 2, ',', ' ') ?> DA</td>
        </tr>
        <tr>
            <td colspan="4" class="text-end text-muted small">Rendu</td>
            <td class="text-end text-muted small"><?= number_format((float)$vente['rendu'], 2, ',', ' ') ?> DA</td>
        </tr>
        <tr>
            <td colspan="4" class="text-end text-success small fw-semibold">Marge</td>
            <td class="text-end text-success small fw-semibold"><?= number_format((float)$vente['marge'], 2, ',', ' ') ?> DA</td>
        </tr>
    </tfoot>
</table>
