<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../includes/lang.php';

$pageTitle  = __('clients_titre');
$activePage = 'clients';
$assetBase  = '../';

$pdo = getDB();

// Filtre recherche
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// KPI globaux
$stmtKpi = $pdo->query("
    SELECT COUNT(DISTINCT c.numero) AS nb_clients_total,
           COUNT(DISTINCT CAST(v.client AS UNSIGNED)) AS nb_clients_acheteurs,
           COALESCE(SUM(v.totale), 0) AS ca_total,
           COALESCE(AVG(v.totale), 0) AS panier_moyen
    FROM client c
    LEFT JOIN vente v ON CAST(v.client AS UNSIGNED) = c.numero
");
$kpi = $stmtKpi->fetch();

// Liste clients
$sql = "
    SELECT c.numero, c.nom, c.prenom,
           COUNT(v.idVente) AS nb_achats,
           COALESCE(SUM(v.totale), 0) AS total_depense,
           COALESCE(AVG(v.totale), 0) AS panier_moyen,
           MAX(v.dateVente) AS derniere_visite
    FROM client c
    LEFT JOIN vente v ON CAST(v.client AS UNSIGNED) = c.numero
";
$params = array();
if ($search !== '') {
    $sql .= " WHERE (c.nom LIKE ? OR c.prenom LIKE ?) ";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}
$sql .= " GROUP BY c.numero, c.nom, c.prenom ORDER BY total_depense DESC ";

$stmtClients = $pdo->prepare($sql);
$stmtClients->execute($params);
$clients = $stmtClients->fetchAll();

$dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
$dateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');

require_once dirname(__FILE__) . '/../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
    <h4 class="fw-bold mb-0"><i class="bi bi-person-lines-fill me-2 text-primary"></i>Clients</h4>
    <a href="../ajax/export.php?type=clients" class="btn btn-outline-success btn-sm">
        <i class="bi bi-download me-1"></i>Export CSV
    </a>
</div>

<!-- KPI -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
                <div>
                    <div class="text-muted small">Total clients</div>
                    <div class="fs-5 fw-bold"><?= (int)$kpi['nb_clients_total'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-success bg-opacity-10 text-success"><i class="bi bi-person-check"></i></div>
                <div>
                    <div class="text-muted small">Clients acheteurs</div>
                    <div class="fs-5 fw-bold"><?= (int)$kpi['nb_clients_acheteurs'] ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-info bg-opacity-10 text-info"><i class="bi bi-cash-stack"></i></div>
                <div>
                    <div class="text-muted small">CA total clients</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['ca_total'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="card kpi-card h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="kpi-icon bg-warning bg-opacity-10 text-warning"><i class="bi bi-basket"></i></div>
                <div>
                    <div class="text-muted small">Panier moyen global</div>
                    <div class="fs-5 fw-bold"><?= number_format((float)$kpi['panier_moyen'], 2, ',', ' ') ?> DA</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recherche -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Rechercher un client</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control"
                           placeholder="Nom ou prénom…" value="<?= htmlspecialchars($search) ?>">
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search me-1"></i>Rechercher
                </button>
            </div>
            <?php if ($search !== ''): ?>
            <div class="col-md-2">
                <a href="clients.php" class="btn btn-outline-secondary w-100">
                    <i class="bi bi-x me-1"></i>Effacer
                </a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Table clients -->
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold border-bottom d-flex align-items-center justify-content-between">
        <span><i class="bi bi-table me-1 text-primary"></i>Liste des clients</span>
        <span class="badge bg-secondary"><?= count($clients) ?> client(s)</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($clients)): ?>
            <p class="text-muted text-center py-4">Aucun client trouvé.</p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover table-sm mb-0 align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Nom / Prénom</th>
                        <th class="text-center">Nb achats</th>
                        <th class="text-end">Total dépensé</th>
                        <th class="text-end">Panier moyen</th>
                        <th class="text-center">Dernière visite</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$c['numero'] ?></td>
                        <td>
                            <span class="fw-semibold"><?= htmlspecialchars($c['nom']) ?></span>
                            <span class="text-muted ms-1"><?= htmlspecialchars($c['prenom']) ?></span>
                        </td>
                        <td class="text-center">
                            <span class="badge bg-primary rounded-pill"><?= (int)$c['nb_achats'] ?></span>
                        </td>
                        <td class="text-end fw-semibold">
                            <?= number_format((float)$c['total_depense'], 2, ',', ' ') ?> DA
                        </td>
                        <td class="text-end text-muted small">
                            <?= number_format((float)$c['panier_moyen'], 2, ',', ' ') ?> DA
                        </td>
                        <td class="text-center small text-muted">
                            <?= $c['derniere_visite'] ? htmlspecialchars(date('d/m/Y', strtotime($c['derniere_visite']))) : '—' ?>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm btn-outline-primary btn-voir-achats"
                                    data-id="<?= (int)$c['numero'] ?>"
                                    data-nom="<?= htmlspecialchars($c['prenom'] . ' ' . $c['nom']) ?>">
                                <i class="bi bi-eye me-1"></i>Voir achats
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

<!-- Modal détail client -->
<div class="modal fade" id="modalClientDetail" tabindex="-1" aria-labelledby="modalClientDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalClientDetailLabel">
                    <i class="bi bi-person-circle me-2"></i>Achats du client
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="modalClientBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = new bootstrap.Modal(document.getElementById('modalClientDetail'));
    var modalBody  = document.getElementById('modalClientBody');
    var modalLabel = document.getElementById('modalClientDetailLabel');

    document.querySelectorAll('.btn-voir-achats').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id  = this.getAttribute('data-id');
            var nom = this.getAttribute('data-nom');

            modalLabel.innerHTML = '<i class="bi bi-person-circle me-2"></i>Achats de ' + nom;
            modalBody.innerHTML  = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"></div></div>';
            modal.show();

            fetch('../ajax/client_detail.php?id=' + id)
                .then(function(r) { return r.text(); })
                .then(function(html) { modalBody.innerHTML = html; })
                .catch(function() { modalBody.innerHTML = '<div class="alert alert-danger">Erreur lors du chargement.</div>'; });
        });
    });
});
</script>

<?php require_once dirname(__FILE__) . '/../includes/footer.php'; ?>
