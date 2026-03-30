# CaisseWeb

Interface web de gestion et de reporting pour un point de vente, connectée à la base de données MySQL d'une caisse Java Swing existante.

## Aperçu

CaisseWeb est une application PHP/MySQL qui offre un tableau de bord et des outils d'analyse pour suivre les ventes, les marges, les stocks et les clients d'un magasin. Elle se connecte directement à la base de données de la caisse Java Swing sans modifier son schéma.

## Fonctionnalités

- **Tableau de bord** — KPIs du jour (CA, marge, nb ventes, panier moyen, nb clients), graphiques CA 30 jours, ventes par heure et par jour de la semaine avec bascule Nb ventes / CA / Marge, top 5 articles par marge
- **Reporting des ventes** — liste paginée des ventes avec filtres par période, vendeur et article, marge réelle calculée par vente, détail article par article en modal
- **Vendeurs** — performance par vendeur sur la période (CA, marge, nb ventes, panier moyen)
- **Prix produits** — consultation et historique des prix de vente et d'achat
- **Inventaire** — état du stock avec seuils d'alerte
- **Historique des prix** — évolution des prix dans le temps
- **Marges** — analyse des marges par article et par catégorie
- **Statistiques avancées** — graphiques CA par jour, Top 10 articles par CA et par marge, comparaison mois courant vs mois précédent, récapitulatif par jour (30 derniers jours)
- **Clients** — liste et détail des clients
- **Stocks** — suivi des entrées de réapprovisionnement
- **Export CSV** — export des ventes sur la période filtrée

## Stack technique

| Composant | Version |
|-----------|---------|
| PHP | 5.6+ |
| MySQL | 5.x+ |
| Bootstrap | 5.3 |
| Chart.js | 3.9 |
| Bootstrap Icons | 1.11 |

> Pas de framework PHP, pas de Composer — fichiers PHP natifs uniquement.

## Structure du projet

```
CaisseWeb/
├── config.php              # Connexion PDO (DB_HOST, DB_NAME, DB_USER, DB_PASS)
├── index.php               # Tableau de bord
├── pages/
│   ├── commandes.php       # Reporting des ventes
│   ├── vendeurs.php        # Performance vendeurs
│   ├── prix.php            # Prix produits
│   ├── inventaire.php      # Inventaire
│   ├── historique_prix.php # Historique des prix
│   ├── marges.php          # Marges
│   ├── stats.php           # Statistiques avancées
│   ├── clients.php         # Clients
│   └── stocks.php          # Stocks
├── ajax/
│   ├── vente_detail.php    # Détail d'une vente (modal)
│   ├── client_detail.php   # Détail d'un client (modal)
│   └── export.php          # Export CSV
├── includes/
│   ├── header.php          # Navbar Bootstrap
│   ├── footer.php          # Scripts JS + fermeture body
│   └── lang.php            # Fonction __() pour les libellés
├── lang/
│   └── fr.php              # Chaînes de traduction françaises
└── assets/
    ├── css/style.css
    └── js/
```

## Schéma de la base de données

La base `caisse` est créée et gérée par l'application Java Swing. CaisseWeb la lit en lecture seule.

| Table | Colonnes principales |
|-------|---------------------|
| `vente` | `idVente`, `dateVente`, `loginVendeur`, `totale`, `marge`, `recu`, `rendu`, `client`, `credit` |
| `articlevente` | `id`, `idVente`, `code`, `nom`, `quantite`, `prixAchatUnite`, `prixVenteUnite` |
| `article` | `code`, `nom`, `categorie`, `prixAchat`, `prixVente`, `stock`, `seuilAlerte` |
| `stock` | `id`, `codeArticle`, `nomArticle`, `dateAchat`, `nombreUnite`, `prixUnite` |
| `client` | `numero`, `nom`, `prenom` |
| `user` | `login`, `isAdmin`, `password` |

## Installation

1. Cloner le dépôt dans le répertoire web de WAMP/XAMPP :
   ```bash
   git clone https://github.com/<utilisateur>/CaisseWeb.git
   ```

2. Configurer la connexion à la base de données dans `config.php` :
   ```php
   define('DB_HOST', 'localhost');
   define('DB_PORT', '3306');
   define('DB_NAME', 'caisse');
   define('DB_USER', 'root');
   define('DB_PASS', '');
   ```

3. Ouvrir `http://localhost/CaisseWeb/` dans le navigateur.

> La base de données doit être celle de l'application Java Swing. CaisseWeb ne crée aucune table.

## Captures d'écran

> *(à compléter)*

## Licence

Usage interne — tous droits réservés.
