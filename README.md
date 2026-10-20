# VolyLegume — MaraîchèrePro

Application web de gestion d'exploitations maraîchères, projet fil rouge du BUT RGI / L3 SIO (ESMIA). Une exploitation regroupe des parcelles, des campagnes culture, des activités, des intrants et stocks, des traitements phytosanitaires, des récoltes et des finances simplifiées, isolées par exploitation.

## Fonctionnalités

- Authentification par compte, contexte d'exploitation active contrôlé côté serveur
- Rôles d'exploitation : Responsable (chef d'exploitation), Agent, Admin — gestion des rattachements (équipiers)
- Parcelles et cultures (référentiels), campagnes (semis / plantation), calendrier cultural généré et replanifiable
- Activités de suivi (labor, désherbage, irrigation, observation…), traitements phytosanitaires avec **délai avant récolte bloquant** (borne serveur)
- Intrants, mouvements de stock (entrées, consommations, corrections), alertes sous seuil
- Récoltes avec contrôle de la borne minimale (récolte bloquée tant que le délai n'est pas écoulé)
- Finances simplifiées : dépenses et recettes par campagne, tableaux de bord et indicateurs (rendement, taux de réalisation, alertes stock, rentabilité)
- Recherche, pagination, journal d'audit, notifications, tâche différée (queue), cache
- API REST (Sanctum) : consultation des campagnes et saisie des activités

## Versions et environnement

| Élément | Version retenue |
| --- | --- |
| PHP | 8.3+ (développé sous 8.5) |
| Laravel | 13.x (`laravel/framework ^13.17`) |
| Sanctum | `^4.3` |
| Base de données | PostgreSQL 18 (via Docker sur le port **5433**) |
| File d'attente / cache | `database` (tables `jobs`, `cache`) |
| Front-end | Blade + Tailwind (Vite) |
| Tests | PHPUnit, base `sqlite :memory:` |
| Locale / fuseau | `fr_FR` / `Africa/Antananarivo` |

## Prérequis

- PHP 8.3+ avec les extensions `pdo_pgsql` et (pour les tests) `pdo_sqlite`
- Composer, Node.js 20+, Docker (ou un PostgreSQL local)

```bash
# Debian/Ubuntu — extensions PHP manquantes
sudo apt-get install -y php8.5-pgsql php8.5-sqlite3
```

## Installation

```bash
cd VolyLegume

composer install
npm install
npm run build            # ou : npm run dev (serveur Vite)

cp .env.example .env
php artisan key:generate
```

### Base de données

Deux options :

**A. PostgreSQL via Docker (recommandé, port 5433)**

```bash
docker compose up -d db
```

Le `.env.example` est déjà réglé sur `DB_CONNECTION=pgsql`, `DB_PORT=5433`, base `voly`, utilisateur `voly` / mot de passe `voly`.

**B. PostgreSQL local**

Ajustez `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` dans `.env`.

Puis :

```bash
php artisan migrate:fresh --seed
```

18 migrations créent le schéma complet (dont la table `personal_access_tokens` de Sanctum) ; le seeder (`DatabaseSeeder`) peuple deux exploitations fictives avec leurs comptes, parcelles, cultures, campagnes, stocks et opérations (voir plus bas).

## Lancement

```bash
php artisan serve          # application : http://127.0.0.1:8000
php artisan queue:work     # worker pour les tâches différées (notifications, digest…)
```

En développement, `composer run dev` lance serveur, queue worker et Vite ensemble.

## Comptes de démonstration

Mot de passe commun : **`password`** (voir `DatabaseSeeder`).

| Compte | Rôle | Exploitation |
| --- | --- | --- |
| `responsable@volylegume.mg` | Responsable (chef) | Ferme Ambohipo |
| `agent+ambohipo@volylegume.mg` | Agent | Ferme Ambohipo |
| `admin@volylegume.mg` | Admin (rattaché) | Ferme Ambohipo |
| `responsable.itasy@volylegume.mg` | Responsable | Exploitation Itasy |
| `agent+analavory@volylegume.mg` | Agent | Exploitation Itasy |

### Scénarios de démonstration inclus

- **Isolation multi-exploitations** : chaque compte ne voit que son exploitation (contrôles serveur, tests croisés)
- **Récolte autorisée** : « Tomate P01 — campagne 1 » (traitement de plus de 14 jours)
- **Récolte bloquée** : « Carotte P02 — campagne 1 » (traitement il y a 3 jours, délai de 21 jours — la récolte avant la borne est refusée)
- **Stocks sous seuil** : « Fongicide cuivre » (3 L / seuil 5) et « Engrais potasse » (2 kg / seuil 10) apparaissent en alerte tableau de bord
- **Campagne sans opération** : Exploitation Itasy, prête à être explorée sans données préalables

Les données sont fictives ; aucune dose, délai ou produit du seeder ne constitue une prescription agronomique.

## API REST

Routes sous `/api`, authentification par token Sanctum (`POST /api/login`), contexte d'exploitation via l'en-tête `X-Farm-Id` (membre actif requis) :

| Méthode | Route | Description |
| --- | --- | --- |
| POST | `/api/login` | Obtention d'un token (`email` + `password`) |
| POST | `/api/logout` | Révocation du token courant |
| GET | `/api/campaigns` | Liste des campagnes (filtres, pagination) |
| GET | `/api/campaigns/{campaign}` | Détail d'une campagne |
| POST | `/api/activities` | Saisie d'une activité (201) |

Chaque réponse inclut `X-Farm-Id` et des en-têtes de pagination (`X-Per-Page`, `X-Total`…). Limite : 60 requêtes/minute.

## Tests

```bash
php artisan test           # ou : vendor/bin/phpunit
```

La suite (55 tests, 143 assertions) tourne sur SQLite en mémoire et couvre l'authentification API, l'isolation entre exploitations, les rôles, les stocks, le délai avant récolte, les finances, l'API et l'édition des rattachements.

## Journal et audit

Chaque écriture métier crée une ligne de journal (`JournalEntry`) horodatée avec l'utilisateur, le type d'action et l'entité concernée — journal accessible à l'équipe de démonstration pour justifier chaque opération.

## Commandes utiles

```bash
php artisan migrate:fresh --seed   # réinitialiser la base de démonstration
php artisan storage:link           # liens de stockage
php artisan queue:work             # worker de file d'attente
php artisan test                   # suite de tests
vendor/bin/pint                    # formatage PSR-12 / Laravel
```

---

Projet académique — ESMIA, 2026. Les noms, quantités et scénarios sont fictifs et destinés uniquement à la démonstration.
