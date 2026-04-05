# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

CaisseWeb is a PHP/MySQL read-only reporting interface for a Java Swing POS (point of sale) application. It reads the Java app's `caisse` database without modifying its schema. Deployed on WAMP at `E:\WAMP\www\CaisseWeb\`.

## Running the App

No build step. Start WAMP and open `http://localhost/CaisseWeb/` in a browser.

PHP binary for CLI testing: `E:/WAMP/bin/php/php7.1.9/php.exe`

Example:
```bash
"E:/WAMP/bin/php/php7.1.9/php.exe" -r "require_once 'E:/WAMP/www/CaisseWeb/config.php'; $pdo = getDB(); ..."
```

## PHP Compatibility Rules

**PHP 5.6 compatibility is required throughout.** Violations cause silent failures.

- No `??` (null coalescing) — use `isset($x) ? $x : $default`
- No `[]` for array literals — use `array()`
- No typed return types, no arrow functions, no spread operator
- `json_encode()` requires UTF-8 input — the DB uses `latin1` but PDO is configured with `utf8` charset so MySQL auto-converts. Never change `DB_CHARSET` back to `latin1` or `json_encode()` will silently return `false`, causing JS syntax errors like `var x = ;`

## Architecture

### Page lifecycle

Every page follows this pattern:
```php
require_once '../config.php';       // DB connection
require_once '../includes/lang.php'; // __() translation function
$pageTitle  = __('key');
$activePage = 'slug';               // controls navbar active state
$assetBase  = '../';                // path prefix for assets
// ... queries ...
require_once '../includes/header.php'; // outputs <html> through <main>
// ... HTML output ...
require_once '../includes/footer.php'; // closes </main>, loads JS
```

`index.php` (dashboard) uses `$assetBase = ''` (root level).

### Database

Connection via `getDB()` in `config.php` — singleton PDO. Schema is owned by Java app, never modified by CaisseWeb:

| Table | Key columns |
|-------|-------------|
| `vente` | `idVente`, `dateVente`, `loginVendeur`, `totale`, `marge`, `recu`, `rendu` |
| `articlevente` | `idVente` (FK), `code`, `nom`, `quantite`, `prixAchatUnite`, `prixVenteUnite` |
| `article` | `code` (PK), `nom`, `categorie`, `prixAchat`, `prixVente`, `stock`, `seuilAlerte` |
| `stock` | `codeArticle`, `dateAchat`, `nomArticle`, `nombreUnite`, `prixUnite` |
| `client` | `numero`, `nom`, `prenom` |
| `user` | `login`, `isAdmin`, `password` |

**Critical**: `vente.marge` stores only one article's margin (Java app bug). Always compute real sale margin as `SUM(av.quantite * (av.prixVenteUnite - av.prixAchatUnite))` from `articlevente`. When JOINing `vente` with `articlevente` without GROUP BY, `SUM(v.totale)` and `SUM(v.marge)` are multiplied by the number of articles per sale.

### Navigation / Active page

`includes/header.php` uses `$activePage` to highlight the correct nav item. When adding a new page or moving a page between menus, update the `$_*Actif` arrays at the top of `header.php`:
```php
$_ventesActif       = in_array($_ap, array('commandes'));
$_produitsActif     = in_array($_ap, array('prix', 'historique_prix'));
$_financesActif     = in_array($_ap, array('marges', 'stats'));
$_utilisateursActif = in_array($_ap, array('clients', 'vendeurs'));
$_stocksActif       = in_array($_ap, array('stocks', 'inventaire'));
```

### AJAX endpoints

Files under `ajax/` return HTML fragments (not JSON) loaded via `fetch()` into modals:
- `ajax/vente_detail.php?id=N` — sale line items table
- `ajax/client_detail.php?id=N` — client purchase history
- `ajax/export.php?type=ventes&date_debut=…&date_fin=…` — CSV download

### Frontend

- **Chart.js 3.9** — all charts. Toggle between datasets with `chart.data.datasets[0].data = newData; chart.update()`.
- **Bootstrap 5.3** — layout and components.
- **`assets/js/app.js`** — global JS: `[data-confirm]` delete confirmations, `.alert-dismissible.auto-dismiss` (4s auto-close), `#tableSearch` + `[data-searchable]` client-side table filter.
- **`assets/css/style.css`** — custom styles using CSS variables (defined in `:root`). Primary color: `#4f46e5` (indigo).

### Translation

`__('key')` loads from `lang/fr.php` (returns an array). Add new strings there when adding UI text. The `lang/ar.php` file exists but is unused.

## Default Date Ranges

- **Dashboard**: defaults to today (`Y-m-d`)
- **Ventes (commandes.php)**: defaults to today
- **Stats**: defaults to last 30 days ending at `MAX(dateVente)` from DB — avoids showing empty charts when server date ≠ data date
