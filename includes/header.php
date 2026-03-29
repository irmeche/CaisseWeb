<?php require_once (isset($assetBase) ? $assetBase : '') . 'includes/lang.php'; ?>
<!DOCTYPE html>
<html lang="fr" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(isset($pageTitle) ? $pageTitle : 'Gestion Magasin') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= isset($assetBase) ? $assetBase : '' ?>assets/css/style.css">
</head>
<body>

<?php
$_ap     = isset($activePage) ? $activePage : '';
$_base   = isset($assetBase)  ? $assetBase  : '';

$_ventesActif   = in_array($_ap, array('commandes', 'vendeurs'));
$_produitsActif = in_array($_ap, array('prix', 'inventaire', 'historique_prix'));
$_financesActif = in_array($_ap, array('marges', 'stats'));
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= $_base ?>index.php">
            <i class="bi bi-shop me-2"></i>Gestion Magasin
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto">

                <li class="nav-item">
                    <a class="nav-link <?= $_ap === 'dashboard' ? 'active' : '' ?>" href="<?= $_base ?>index.php">
                        <i class="bi bi-speedometer2 me-1"></i>Tableau de bord
                    </a>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $_ventesActif ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-receipt me-1"></i>Ventes
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item <?= $_ap === 'commandes' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/commandes.php">
                            <i class="bi bi-list-ul me-1"></i>Commandes
                        </a></li>
                        <li><a class="dropdown-item <?= $_ap === 'vendeurs' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/vendeurs.php">
                            <i class="bi bi-people me-1"></i>Vendeurs
                        </a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $_produitsActif ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-tags me-1"></i>Produits
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item <?= $_ap === 'prix' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/prix.php">
                            <i class="bi bi-currency-exchange me-1"></i>Prix produits
                        </a></li>
                        <li><a class="dropdown-item <?= $_ap === 'inventaire' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/inventaire.php">
                            <i class="bi bi-clipboard-check me-1"></i>Inventaire
                        </a></li>
                        <li><a class="dropdown-item <?= $_ap === 'historique_prix' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/historique_prix.php">
                            <i class="bi bi-clock-history me-1"></i>Historique prix
                        </a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $_financesActif ? 'active' : '' ?>"
                       href="#" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-graph-up me-1"></i>Finances
                    </a>
                    <ul class="dropdown-menu dropdown-menu-dark">
                        <li><a class="dropdown-item <?= $_ap === 'marges' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/marges.php">
                            <i class="bi bi-percent me-1"></i>Marges
                        </a></li>
                        <li><a class="dropdown-item <?= $_ap === 'stats' ? 'active' : '' ?>"
                               href="<?= $_base ?>pages/stats.php">
                            <i class="bi bi-bar-chart-line me-1"></i>Statistiques
                        </a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $_ap === 'clients' ? 'active' : '' ?>"
                       href="<?= $_base ?>pages/clients.php">
                        <i class="bi bi-person-lines-fill me-1"></i>Clients
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link <?= $_ap === 'stocks' ? 'active' : '' ?>"
                       href="<?= $_base ?>pages/stocks.php">
                        <i class="bi bi-box-seam me-1"></i>Stocks
                    </a>
                </li>

            </ul>

            <span class="navbar-text text-secondary small">
                <i class="bi bi-circle-fill text-success me-1" style="font-size:.5rem"></i>
                MySQL connecté
            </span>
        </div>
    </div>
</nav>

<main class="container-fluid py-4 px-4">
