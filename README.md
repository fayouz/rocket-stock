# Rocket Stock

**Stock** des lieux (logements, locaux…) : catalogue (consommables, linge, équipements), niveaux par lieu et emplacement (quantité **et** OK / Bas / Vide), mouvements idempotents (usage location / perso), magasins et offres, liste de courses et paniers cochés en magasin, équipements (garantie, notice Rocket Cloud), export du bilan de consommation, alertes de stock bas (Rocket Mailer, désactivées par défaut). Extrait de [rocket-place](https://github.com/fayouz/rocket-place) ; brique du Middleware Rocket, sur le socle [rocket-core](https://github.com/fayouz/rocket-core).

| Dossier | Stack |
|---|---|
| `backend/` | Symfony 8.1, API Platform, Doctrine (PostgreSQL), rocket-core (`rocket/core-bundle`) |
| `frontend/` | Nuxt 4, Nuxt UI 4, layer `@rocket/core` |
| `docs/` | Documentation (Nuxt UI + Nuxt Content), changelog sur `/changelog` |

Le socle commun (comptes, LDAP, SSO / Rocket Auth, applications externes, tableau de bord, mises à jour, modes autonome et suite) vient de rocket-core : ce dépôt ne contient que le métier.

## Lieux : Rocket Place ou autonome

Le stock référence un lieu par son **identifiant** (`placeId`, UUID), jamais par clé étrangère : Rocket Stock ne possède aucun lieu (même schéma que Rocket Clean).

- **Avec Rocket Place** (`ROCKET_PLACE_URL` + `ROCKET_PLACE_TOKEN` `rpl_…`, ou jeton Rocket Auth en mode suite) : lieux de Place (`/api/places`), lus par `App\Place\PlaceClient`, nom en cache dans `Site`.
- **Autonome** (sans `ROCKET_PLACE_URL`) : lieux locaux (entité `Site`) créés dans Rocket Stock.

## Démarrage rapide

```bash
docker compose up -d --build
```

- Application : http://localhost:4100 (configuration initiale : création de l'administrateur)
- API + OpenAPI : http://localhost:9100/api/docs
- Démo complète : `docker compose -f compose.yaml -f compose.demo.yaml up -d --build` (voir [demo/README.md](demo/README.md))

### Développement sans Docker

```bash
# base locale
docker run -d --name rocket-stock-db -e POSTGRES_USER=app -e POSTGRES_PASSWORD=app -e POSTGRES_DB=app -p 127.0.0.1:55439:5432 postgres:16-alpine
# backend (PHP 8.4) ; .env.local et .env.test.local : DATABASE_URL=...:55439/app, MESSENGER_TRANSPORT_DSN=sync://
cd backend && composer install --ignore-platform-req=ext-ldap
php bin/console lexik:jwt:generate-keypair
php bin/console doctrine:migrations:migrate
DEMO_MODE=1 php bin/console app:demo:seed   # facultatif
php -S 127.0.0.1:9100 -t public
php bin/phpunit

# frontend
cd frontend && npm install && NUXT_PUBLIC_API_BASE=http://localhost:9100 npm run dev -- --port 4100
```

## Installer sur téléphone

Application web installable (PWA), sans store :

- **Android (Chrome)** : bouton **Installer l’application** (page *Courses*) ou menu ⋮ › *Installer l’application*.
- **iPhone (Safari)** : **Partager** › **Sur l’écran d’accueil**.

Ouverture sur `/courses`, raccourcis *Liste de courses* et *Panier*. Hors ligne, le panier garde ses dernières données (badge **Hors ligne**) et met en file les lignes cochées jusqu’au retour du réseau. Service worker actif en production seulement (`NUXT_PUBLIC_PWA=false` pour le couper). Icônes : `node frontend/scripts/pwa-icons.mjs`. Détails : `docs/content/2.usage/7.telephone.md`.

## Configuration

| Variable | Rôle |
|---|---|
| `ROCKET_PLACE_URL` / `ROCKET_PLACE_TOKEN` | Rocket Place (lieux), jeton d'application `rpl_…`. Vide : lieux locaux. |
| `ROCKET_MAILER_URL`, `ROCKET_MAILER_TOKEN`, `ROCKET_MAILER_MAILBOX`, `ROCKET_MAILER_SENDER` | Rocket Mailer (alertes). Vide : démo (`var/demo-mailer-<env>.json`). |
| `AMAZON_ASSOCIATE_TAG` | Identifiant Partenaires Amazon des paniers pré-remplis. Vide par défaut. |
| `STOCK_ALERT_EMAILS` | Destinataires des alertes de stock bas, séparés par des virgules. Vide (défaut) : aucune alerte. |
| `ROCKET_AUTH_URL`, `ROCKET_AUTH_INTERNAL_URL`, `ROCKET_AUTH_CLIENT_ID` (`rocket-stock`), `ROCKET_AUTH_CLIENT_SECRET`, `ROCKET_AUTH_ADMIN_GROUP`, `ROCKET_PUBLIC_URL`, `ROCKET_INTERNAL_URL` | Mode suite. En suite, Place et Mailer sont appelés avec un jeton Rocket Auth (audiences `rocket-place`, `rocket-mailer`), les jetons statiques restent le repli. |

## API (Host, Place, Clean, PMS)

`/api/stock-items` et `/api/stock-levels` gardent les chemins et les champs de Rocket Place (IRI `place`/`item`, `PATCH {"level"}`) : un client de Place bascule en changeant l'URL et le jeton (`rst_…`). En plus : `/api/places/{placeId}/stock`, `/api/movements` (POST idempotent par `externalRef`), `/api/shopping-list`, `/api/shopping-carts`, `/api/suppliers`, `/api/stores`, `/api/equipment`, `/api/export/{movements,consumption}`. Détail : [docs/content/3.api/2.domain.md](docs/content/3.api/2.domain.md).

Une application agissant pour elle-même (jeton `rst_…` sans `X-Impersonate-User`) a accès à ces routes (`StockAccessVoter`, `StockScopeGuardListener`).

## Vérifier

```bash
cd backend && php bin/console lint:container && php bin/console doctrine:schema:validate && php bin/phpunit
cd frontend && npm run lint && npm run typecheck
```
