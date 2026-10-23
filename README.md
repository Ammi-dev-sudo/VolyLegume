# VolyLegume — MaraîchèrePro

Application web de gestion d'exploitations maraîchères, projet fil rouge du BUT RGI / L3 SIO (ESMIA). Une exploitation regroupe des parcelles, des campagnes culture, des activités, des intrants et stocks, des traitements phytosanitaires, des récoltes, des lots de produits frais, des clients, des ventes, des paiements et des finances simplifiées, isolés par exploitation.

## Fonctionnalités

- Authentification par compte, contexte d'exploitation active contrôlé côté serveur
- Rôles d'exploitation : Responsable (chef d'exploitation), Agent, Admin — gestion des rattachements (équipiers)
- Rôle global d'administrateur de plateforme : supervision des comptes, des exploitations et du journal transversal
- Parcelles et cultures (référentiels), campagnes (semis / plantation), calendrier cultural généré et replanifiable
- Activités de suivi (labor, désherbage, irrigation, observation…), traitements phytosanitaires avec **délai avant récolte bloquant** (borne serveur)
- Intrants, mouvements de stock (entrées, consommations, corrections), alertes sous seuil
- Récoltes avec contrôle de la borne minimale (récolte bloquée tant que le délai n'est pas écoulé)
- Chaîne commerciale : lot de stock créé automatiquement à la récolte, traçabilité des mouvements, pertes et ajustements
- Clients (particuliers, revendeurs, restaurants, coopératives), ventes multi-lignes, remises, paiements partiels et annulation avec restitution du stock
- Recette générée automatiquement par vente et reste à encaisser visible sur les ventes et le tableau de bord
- Finances simplifiées : dépenses et recettes par campagne, tableaux de bord et indicateurs (rendement, taux de réalisation, alertes stock, rentabilité)
- Recherche, pagination, journal d'audit (`audit_logs`), notifications, tâche différée (queue), cache
- Autorisations centralisées par Policies Laravel et contexte d'exploitation
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

PostgreSQL 18 tourne **dans Docker** (`docker-compose.yml`), sur le port **5433**,
avec un volume nommé `volylegume_pgdata` : les données survivent à
`docker compose down` et à un redémarrage de la machine.

```bash
sudo usermod -aG docker $USER   # une fois — puis se déconnecter/reconnecter
docker compose up -d postgres   # démarre PostgreSQL 18 sur 127.0.0.1:5433
docker compose ps               # état du conteneur
docker compose logs -f postgres # journaux du serveur
```

Dans une session déjà ouverte avant l'ajout au groupe, `docker` reste refusé :
utilisez `sg docker -c 'docker compose ps'` ou reconnectez la session.

Le `.env.example` est déjà réglé sur `DB_CONNECTION=pgsql`, `DB_PORT=5433`, base
`volylegume`, utilisateur `volylegume` / mot de passe `volylegume_secret` :
aucune modification n'est nécessaire.

Deux détails propres à PostgreSQL 18 : le volume est monté sur
`/var/lib/postgresql` (les données sont rangées dans un sous-répertoire
versionné, sinon le conteneur refuse de démarrer) et l'image `alpine` n'embarque
pas de `tzdata` — le fuseau du serveur est donc réglé sur la notation POSIX
`EAT-3` (UTC+3, Madagascar sans changement d'heure).

**Option B — PostgreSQL local** : ajustez `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD` dans `.env`.

Puis, dans les deux cas :

```bash
php artisan migrate:fresh --seed
```

25 migrations créent le schéma complet (dont la table `personal_access_tokens` de Sanctum) ; le seeder (`DatabaseSeeder`) peuple deux exploitations fictives avec leurs comptes, parcelles, cultures, campagnes, stocks, opérations, lots de récolte, clients, ventes et paiements (voir plus bas).

#### Réglage PostgreSQL « gros volumes »

Le serveur utilise la configuration `docker/postgres/postgresql.conf`, montée en
lecture seule dans le conteneur et chargée via
`postgres -c config_file=/etc/postgresql/postgresql.conf`. Elle est calibrée
pour 8 vCPU / 7 Go de RAM : `shared_buffers = 1GB`, `effective_cache_size = 3GB`,
WAL élargi (`max_wal_size = 4GB`), checkpoints espacés, autovacuum plus
souvent (seuils à 5 % / 2 %), requêtes parallèles, et journalisation des
requêtes lentes au-delà de 500 ms.

Adaptez les valeurs à la RAM de la machine, puis rechargez :

```bash
docker compose restart postgres
```

Pour entrer dans le serveur :

```bash
docker compose exec postgres psql -U volylegume -d volylegume
```

## Lancement

```bash
php artisan serve          # application : http://127.0.0.1:8000
php artisan queue:work     # worker pour les tâches différées (notifications, digest…)
```

En développement, `composer run dev` lance serveur, queue worker et Vite ensemble.

Arrêt de la base :

```bash
docker compose stop postgres    # arrête le conteneur, conserve les données
docker compose down             # supprime le conteneur, conserve le volume
```

## Générer un gros volume de données

Pour éprouver la base, les tableaux de bord et la pagination :

```bash
php artisan data:bulk --campaigns=2000 --activities=100000 --movements=300000
php artisan data:bulk --farm=2 --campaigns=500 --activities=5000 --movements=20000
```

La commande écrit par insertions en masse (aucun événement Eloquent, aucun
journal d'audit : c'est un outil de charge, pas un simulateur de production).
Elle respecte les contraintes de la base — notamment `inputs.quantity` qui reste
toujours la somme des variations de stock et ne descend jamais sous zéro — et
contrôle l'intégrité des clés étrangères après l'import.

Options : `--farm` (exploitation cible), `--campaigns`, `--activities`,
`--movements`, `--chunk` (lignes par lot).

Mesuré sur ce poste (8 vCPU, 7 Go de RAM) : **400 000 lignes en ~40 s**, pour
118 Mo de base. Le chargement désactive temporairement les déclencheurs de clés
étrangères (`session_replication_role`) et la synchronisation WAL, puis contrôle
l'intégrité référentielle à la fin.

Attention : la commande recalcule le solde des intrants de l'exploitation
ciblée, ce qui **modifie les scénarios de démonstration** (alertes de stock sous
seuil). Pour retrouver l'état de démonstration du README :

```bash
php artisan migrate:fresh --seed
```

## Comptes de démonstration

Mot de passe commun : **`password`** (voir `DatabaseSeeder`).

| Compte | Rôle | Exploitation |
| --- | --- | --- |
| `responsable@volylegume.mg` | Responsable (chef) | Ferme Ambohipo |
| `agent+ambohipo@volylegume.mg` | Agent | Ferme Ambohipo |
| `admin@volylegume.mg` | Admin plateforme + Admin (rattaché) | Toutes les exploitations |
| `technicien@volylegume.mg` | Agent (rattaché) | Toutes les exploitations |
| `responsable.itasy@volylegume.mg` | Responsable | Exploitation Itasy |
| `agent+analavory@volylegume.mg` | Agent | Exploitation Itasy |

### Scénarios de démonstration inclus

- **Isolation multi-exploitations** : chaque compte ne voit que son exploitation (contrôles serveur, tests croisés)
- **Récolte autorisée** : « Tomate P01 — campagne 1 » (traitement de plus de 14 jours)
- **Récolte bloquée** : « Carotte P02 — campagne 1 » (traitement il y a 3 jours, délai de 21 jours — la récolte avant la borne est refusée)
- **Stocks sous seuil** : « Fongicide cuivre » (3 L / seuil 5) et « Engrais potasse » (2 kg / seuil 10) apparaissent en alerte tableau de bord
- **Lot de récolte** : la récolte de tomate alimente un lot vendable avec mouvement d’entrée et traçabilité
- **Chaîne commerciale** : clients de démonstration, ventes reliées aux lots, recettes générées et paiement partiel mobile money
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

La suite (100 tests, 318 assertions) tourne sur SQLite en mémoire et couvre l'authentification API, l'isolation entre exploitations, les rôles et Policies, les stocks, le délai avant récolte, la chaîne commerciale (lots, ventes, paiements, annulation), les finances, le journal d'audit, l'administration plateforme et l'API.

## Rapport technique

Le rapport technique complet (architecture, sécurité, modèle de données, audit, tests, déploiement) est disponible dans `RAPPORT_TECHNIQUE.pdf` et sa source Markdown `RAPPORT_TECHNIQUE.md`.

## Journal et audit

Chaque écriture métier crée une ligne dans `audit_logs` : exploitation, utilisateur, action, entité concernée, anciennes/nouvelles valeurs, adresse IP et contexte. Les créations, modifications et suppressions passent par le trait `Auditable` ; les connexions, les refus d'autorisation (403) et les ressources masquées par l'isolation (404) sont également tracés.

Le journal est consultable par les responsables de l'exploitation (`/journal`) et, de façon transversale, par un administrateur de plateforme (`/admin/journal`).

## Administration plateforme

Les comptes marqués `is_platform_admin` accèdent à `/admin` : tableau de bord global, gestion des comptes (activation, rôle plateforme, rattachements) et supervision des exploitations. Les autres utilisateurs reçoivent une réponse 403.

## Commandes utiles

```bash
php artisan migrate:fresh --seed   # réinitialiser la base de démonstration
php artisan produce:backfill-lots  # créer les lots manquants des récoltes existantes
php artisan storage:link           # liens de stockage
php artisan queue:work             # worker de file d'attente
php artisan test                   # suite de tests
vendor/bin/pint                    # formatage PSR-12 / Laravel
```

---

Projet académique — ESMIA, 2026. Les noms, quantités et scénarios sont fictifs et destinés uniquement à la démonstration.
