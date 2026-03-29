<?php
require_once dirname(__FILE__) . '/../config.php';

$type = isset($_GET['type']) ? $_GET['type'] : '';

if (!in_array($type, array('ventes', 'stocks', 'marges', 'clients'))) {
    http_response_code(400);
    exit('Type invalide');
}

$pdo = getDB();

// BOM UTF-8 pour Excel
$bom = "\xEF\xBB\xBF";

header('Content-Type: text/csv; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');

if ($type === 'ventes') {
    $dateDebut = isset($_GET['date_debut']) ? $_GET['date_debut'] : date('Y-m-01');
    $dateFin   = isset($_GET['date_fin'])   ? $_GET['date_fin']   : date('Y-m-d');

    header('Content-Disposition: attachment; filename="ventes_' . $dateDebut . '_' . $dateFin . '.csv"');

    $stmt = $pdo->prepare("
        SELECT idVente, dateVente, loginVendeur, totale, marge, recu, rendu
        FROM vente
        WHERE DATE(dateVente) BETWEEN ? AND ?
        ORDER BY dateVente DESC
    ");
    $stmt->execute(array($dateDebut, $dateFin));

    $out = fopen('php://output', 'w');
    fwrite($out, $bom);
    fputcsv($out, array('ID Vente', 'Date', 'Vendeur', 'Total', 'Marge', 'Recu', 'Rendu'), ';');
    while ($row = $stmt->fetch()) {
        fputcsv($out, array(
            $row['idVente'],
            $row['dateVente'],
            $row['loginVendeur'],
            number_format((float)$row['totale'], 2, ',', ''),
            number_format((float)$row['marge'],  2, ',', ''),
            number_format((float)$row['recu'],   2, ',', ''),
            number_format((float)$row['rendu'],  2, ',', '')
        ), ';');
    }
    fclose($out);

} elseif ($type === 'stocks') {
    header('Content-Disposition: attachment; filename="stocks_' . date('Y-m-d') . '.csv"');

    $stmt = $pdo->query("
        SELECT code, nom, categorie, stock, seuilAlerte, prixAchat, prixVente,
               (stock * prixAchat) AS valeur
        FROM article
        ORDER BY nom
    ");

    $out = fopen('php://output', 'w');
    fwrite($out, $bom);
    fputcsv($out, array('Code', 'Nom', 'Categorie', 'Stock', 'Seuil Alerte', 'Prix Achat', 'Prix Vente', 'Valeur Stock'), ';');
    while ($row = $stmt->fetch()) {
        fputcsv($out, array(
            $row['code'],
            $row['nom'],
            $row['categorie'],
            $row['stock'],
            $row['seuilAlerte'],
            number_format((float)$row['prixAchat'],  2, ',', ''),
            number_format((float)$row['prixVente'],  2, ',', ''),
            number_format((float)$row['valeur'],     2, ',', '')
        ), ';');
    }
    fclose($out);

} elseif ($type === 'marges') {
    header('Content-Disposition: attachment; filename="marges_' . date('Y-m-d') . '.csv"');

    $stmt = $pdo->query("
        SELECT code, nom, categorie, prixAchat, prixVente,
               (prixVente - prixAchat) AS marge_brute,
               CASE WHEN prixVente > 0 THEN ((prixVente - prixAchat) / prixVente * 100) ELSE 0 END AS marge_pct
        FROM article
        WHERE prixAchat > 0
        ORDER BY marge_pct DESC
    ");

    $out = fopen('php://output', 'w');
    fwrite($out, $bom);
    fputcsv($out, array('Code', 'Nom', 'Categorie', 'Prix Achat', 'Prix Vente', 'Marge Brute', 'Marge %'), ';');
    while ($row = $stmt->fetch()) {
        fputcsv($out, array(
            $row['code'],
            $row['nom'],
            $row['categorie'],
            number_format((float)$row['prixAchat'],  2, ',', ''),
            number_format((float)$row['prixVente'],  2, ',', ''),
            number_format((float)$row['marge_brute'], 2, ',', ''),
            number_format((float)$row['marge_pct'],   2, ',', '')
        ), ';');
    }
    fclose($out);

} elseif ($type === 'clients') {
    header('Content-Disposition: attachment; filename="clients_' . date('Y-m-d') . '.csv"');

    $stmt = $pdo->query("
        SELECT c.numero, c.nom, c.prenom,
               COUNT(v.idVente) AS nb_achats,
               COALESCE(SUM(v.totale), 0) AS total_depense,
               COALESCE(AVG(v.totale), 0) AS panier_moyen
        FROM client c
        LEFT JOIN vente v ON CAST(v.client AS UNSIGNED) = c.numero
        GROUP BY c.numero, c.nom, c.prenom
        ORDER BY total_depense DESC
    ");

    $out = fopen('php://output', 'w');
    fwrite($out, $bom);
    fputcsv($out, array('Numero', 'Nom', 'Prenom', 'Nb Achats', 'Total Depense', 'Panier Moyen'), ';');
    while ($row = $stmt->fetch()) {
        fputcsv($out, array(
            $row['numero'],
            $row['nom'],
            $row['prenom'],
            $row['nb_achats'],
            number_format((float)$row['total_depense'], 2, ',', ''),
            number_format((float)$row['panier_moyen'],  2, ',', '')
        ), ';');
    }
    fclose($out);
}
