<?php
// ============================================================
//  CONFIGURATION BASE DE DONNÉES
// ============================================================

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'caisse');
define('DB_USER',    'root');   // <-- Changer si besoin
define('DB_PASS',    '');       // <-- Changer si besoin
define('DB_CHARSET', 'latin1'); // charset de la base Java

// ============================================================
//  SCHÉMA RÉEL (base caisse Java Swing)
// ============================================================
// article      : code (PK), nom, dateMAJ, categorie, seuilAlerte,
//                prixAchat, prixVente, stock, articleAchatMultiple
// articlevente : id, code, nom, prixAchatUnite, prixVenteUnite,
//                quantite, idVente (FK → vente.idVente)
// vente        : idVente (PK), dateVente, loginVendeur, recu, rendu,
//                totale, marge, client, credit
// stock        : id, codeArticle, dateAchat, nomArticle,
//                nombreUnite, prixUnite   (entrées de réappro)
// client       : numero (PK), nom, prenom
// user         : login (PK), isAdmin, password

// ============================================================
//  CONNEXION PDO
// ============================================================
function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo '<!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
                  <title>Erreur DB</title>
                  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
                  </head><body class="bg-danger-subtle d-flex align-items-center justify-content-center vh-100">
                  <div class="text-center p-5 bg-white rounded shadow">
                  <h4 class="text-danger mb-3">Connexion MySQL impossible</h4>
                  <p class="text-muted">' . htmlspecialchars($e->getMessage()) . '</p>
                  <p class="small text-muted">Vérifiez les constantes dans <code>config.php</code></p>
                  </div></body></html>';
            exit;
        }
    }
    return $pdo;
}
