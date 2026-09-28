# Changelog

Toutes les évolutions notables de Rocket Stock. Format [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/), versions [SemVer](https://semver.org/lang/fr/).

## [Non publié]

### Ajouté
- **Application installable (PWA)** sur Android et iPhone : manifeste (`/manifest.webmanifest`, ouverture sur `/courses`, raccourcis « Liste de courses » et « Panier » → `/courses/panier`, dernier panier non terminé), icônes 192/512 + maskable + apple-touch-icon (`frontend/scripts/pwa-icons.mjs`, sans dépendance), balises iOS, bouton « Installer l’application » (instructions Safari sur iPhone). Service worker écrit à la main (`/sw.js`) : assets `/_nuxt/` en cache d’abord, pages réseau d’abord avec page hors ligne, jamais `/api/` ; production seulement (`NUXT_PUBLIC_PWA=false` pour couper) ; « Nouvelle version disponible » à chaque déploiement. Panier hors ligne : dernières données gardées, badge « Hors ligne », lignes cochées mises en file puis rejouées.
- Code-barres : `Item.ean` (EAN-13 / EAN-8, chiffre de contrôle vérifié, 422 sinon), affiché dans le panier avec un code-barres SVG dessiné côté client (encodeur EAN intégré, sans dépendance) et dans la liste en texte.
- Liens produit : `Supplier.searchUrlTemplate` (`{ean}` / `{name}`) et `ItemOffer.productUrl` ; « Voir sur le site » par ligne de panier.
- Panier Amazon pré-rempli : magasin `amazon` + `amazonDomain` (défaut `amazon.fr`), `ItemOffer.asin`, `GET /api/shopping-carts/{id}/store-carts` (50 articles par lien, découpé au-delà, `AMAZON_ASSOCIATE_TAG` optionnel) ; l'utilisateur valide et paie sur Amazon, aucun achat automatique.
- `StoreConnectorInterface` (Amazon l'implémente) pour de futurs connecteurs de drive, qui demandent des partenariats avec les enseignes.
- Bons de commande par e-mail : `Supplier.orderEmail`, aperçu `GET …/purchase-orders/{supplierId}/preview`, envoi uniquement sur `POST` avec `{"confirm": true}` (administrateur, via Rocket Mailer, `Idempotency-Key` `po:<panier>:<fournisseur>`), entité `PurchaseOrder` (panier, fournisseur, date, `messageId`, statut). `DemoMailer` enregistre seulement (démo, tests).
- Démo : EAN, magasin Amazon avec ASIN, fournisseur « Hygiène Pro Occitanie » avec e-mail de commande ; recherche Carrefour par EAN.

## [0.1.0] - 2026-09-28

### Ajouté
- Extraction du stock de Rocket Place, sur le modèle de Rocket Clean : lieu par identifiant (`placeId`), `Site` local ou cache de Rocket Place (`PlaceClient`/`PlaceDirectory`, mode suite par jeton Rocket Auth d'audience `rocket-place`).
- Catalogue `Item` (unité, catégorie consommable / linge / équipement, référence, seuil, quantité d'achat, coût unitaire, fournisseur, notes) à `/api/stock-items`, compatible avec Place.
- `Location` (lieu + emplacement « réserve », « cuisine »…) et `Level` (quantité + état OK / Bas / Vide, seuil et cible propres) à `/api/stock-levels` (compatible Place, `PATCH {"level"}`) et `/api/places/{placeId}/stock`.
- `Movement` : entrée, sortie, consommation, transfert, inventaire ; idempotent par `externalRef` ; origine host / pms / place / clean / stock + application ; usage location / perso ; coût au moment du mouvement. `POST /api/movements` (un ou une liste, tout ou rien).
- Magasins et fournisseurs (`Supplier` : type, adresse, coordonnées, horaires), offres par article (`ItemOffer` : magasin préféré, prix et taille de paquet).
- Liste de courses calculée (`/api/shopping-list`) et paniers (`/api/shopping-carts` : groupés par magasin, cochés en magasin, terminés en entrées de stock idempotentes `cart:<id>:<ligne>`, partage en texte).
- `Equipment` : série, achat, garantie, notice (référence Rocket Cloud).
- Export du bilan (`/api/export/movements`, `/api/export/consumption`, JSON ou CSV), filtré par usage.
- Alertes de stock bas par e-mail via Rocket Mailer, désactivées par défaut (`STOCK_ALERT_EMAILS`).
- Accès : `StockAccessVoter` (STOCK_READ / STOCK_MANAGE), `StockScopeGuardListener` (applications pour elles-mêmes).
- Tableau de bord (articles, à réassortir, consommé en location), données de démo (mêmes lieux que la démo de Place), interface (lieux, courses et panier mobile, mouvements, catalogue, magasins, équipements).
- Identité : `app_id` `stock`, jetons `rst_…`, ports front 4100 · api 9100 · docs 4101, base de dev `rocket-stock-db` (127.0.0.1:55439).
