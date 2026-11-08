# VolyLegume — MaraîchèrePro

Application web de gestion d'exploitations maraîchères, projet fil rouge du BUT RGI / L3 SIO (ESMIA). Une exploitation regroupe des parcelles, des campagnes culture, des activités, des intrants et stocks, des traitements phytosanitaires, des récoltes, des lots de produits frais, des clients, des ventes, des paiements et des finances simplifiées, isolés par exploitation.

## Fonctionnalités

- Authentification par compte, contexte d'exploitation active contrôlé côté serveur
- Rôles d'exploitation : Responsable (chef d'exploitation), Agent, Admin — gestion des rattachements (équipiers)
- Rôle global d'administrateur de plateforme : supervision des comptes, des exploitations et du journal transversal
- Parcelles et cultures (référentiels), campagnes (semis / plantation), calendrier cultural généré et replanifiable
- Plan interactif des parcelles : contours GeoJSON dessinés et éditables, vues 2D sur imagerie satellite Esri ou plan OpenStreetMap et 3D extrudée à partir des mêmes géométries, avec imagerie plaquée à plat, couches activables cultures, campagnes, activités à venir, activités en retard, récoltes, états de récolte, irrigation, alertes et états avec légendes dédiées, filtres synchronisés et frise des récoltes, sélection synchronisée, campagne en cours, prochaines activités, récoltes, alertes, galerie photos et synthèse imprimable / PDF ; les parcelles sans contour restent signalées et ne sont jamais positionnées arbitrairement
- Navigation principale élargie (tableau de bord, plan, parcelles, campagnes, activités, irrigation, agenda, stock récolté, hub) avec un hub regroupant les fonctions secondaires
- Irrigation et eau : saisie des arrosages par parcelle et campagne, volumes, durées, m³/m², quotas d'eau paramétrables et comparaison entre campagnes
- Photos d'exploitation : photo principale de parcelle et historique de photos rattachables à une campagne ou une activité
- Alertes d'activités en retard : alerte critique au-delà de 3 jours, conséquence métier et action attendue
- Traçabilité par QR code : génération d'un QR scannable par lot, page publique de traçabilité et historique de la parcelle à la récolte
- Activités de suivi (labor, désherbage, irrigation, observation…), traitements phytosanitaires avec **délai avant récolte bloquant** (borne serveur)
- Intrants, mouvements de stock (entrées, consommations, corrections), alertes sous seuil
- Récoltes avec contrôle de la borne minimale (récolte bloquée tant que le délai n'est pas écoulé)
- Chaîne commerciale : lot de stock créé automatiquement à la récolte, traçabilité des mouvements, pertes et ajustements
- Clients (particuliers, revendeurs, restaurants, coopératives), ventes multi-lignes, remises, paiements partiels et annulation avec restitution du stock
- Recette générée automatiquement par vente et reste à encaisser visible sur les ventes et le tableau de bord
- Finances simplifiées : dépenses et recettes par campagne, tableaux de bord et indicateurs (rendement, taux de réalisation, alertes stock, rentabilité)
- Rentabilité par campagne, culture et parcelle : marge, marge/m², rendement, eau consommée et budget, avec filtres de période
- Exports CSV (campagnes, stocks, traitements, ventes, finances) et états imprimables / PDF via le navigateur
- Application installable (PWA) : page hors connexion et file locale pour les activités et récoltes, synchronisée avec idempotence au retour du réseau
- Supervision opérationnelle : endpoint `/health`, commande `system:health` et carte dédiée dans l'administration
- Purge planifiée du journal d'audit, avec conservation prolongée des actions sensibles
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
| Locale / fuseau | `fr_FR` / `Indian/Antananarivo` |

## Prérequis

- PHP 8.3+ avec les extensions `pdo_pgsql`, `gd` (rendu des QR codes), `zip` (sauvegardes) et, pour les tests, `pdo_sqlite`
- Composer, Node.js 20+, Docker (ou un PostgreSQL local)
- Pour les sauvegardes PostgreSQL : `pg_dump` et `pg_restore` en version 18

```bash
# Debian/Ubuntu — extensions PHP manquantes
sudo apt-get install -y php8.5-pgsql php8.5-sqlite3 php8.5-zip

# Client PostgreSQL 18 (après ajout du dépôt PGDG si nécessaire)
sudo apt-get install -y postgresql-client-18
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

32 migrations créent le schéma complet (dont la table `personal_access_tokens` de Sanctum) ; le seeder (`DatabaseSeeder`) peuple deux exploitations fictives avec leurs comptes, parcelles, cultures, campagnes, stocks, opérations, lots de récolte, clients, ventes et paiements (voir plus bas). Les quatre parcelles de chaque exploitation de démonstration reçoivent aussi un contour GeoJSON fictif autour de la localité renseignée, afin d’alimenter directement les vues 2D et 3D.

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

- **Rentabilité** : ouvrir `/rentabilite`, filtrer sur une campagne, comparer les marges par culture et par parcelle, puis utiliser « Imprimer / PDF » ou l'export CSV
- **Saisie hors connexion** : ouvrir « Nouvelle activité » ou « Nouvelle récolte », couper le réseau dans les outils du navigateur, enregistrer (la bannière indique la file locale), rétablir le réseau et cliquer sur « Synchroniser »
- **Supervision** : consulter `/health` ou exécuter `php artisan system:health --json`, puis vérifier la carte « Supervision opérationnelle » du tableau de bord administrateur

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

La suite (170 tests, 658 assertions) tourne sur SQLite en mémoire et couvre l'authentification API, l'isolation entre exploitations, les rôles et Policies, les stocks et leur verrouillage, le délai avant récolte, la chaîne commerciale (lots, ventes, paiements, annulation), les finances, la rentabilité, les exports CSV, le plan interactif et ses contours 2D/3D, l'irrigation, les photos de parcelles, les alertes de retard critique, la traçabilité QR, le hub de modules, le journal d'audit et sa purge, l'administration plateforme, la supervision, la synchronisation hors ligne, l'API et le cycle sauvegarde/restauration.

À chaque `push` et `pull request`, GitHub Actions (`.github/workflows/ci.yml`) rejoue la suite sur SQLite puis sur PostgreSQL 18, vérifie le formatage avec Pint et compile les assets Vite. Le workflow contrôle aussi que les compteurs ci-dessus restent synchronisés avec le rapport JUnit ; mettez-les à jour lorsque la suite évolue.

Pour rejouer la suite sur PostgreSQL en local, sans toucher à la base de démonstration :

```bash
# une seule fois
docker compose exec postgres createdb -U volylegume volylegume_test

# à chaque exécution
DB_CONNECTION=pgsql DB_PORT=5433 DB_DATABASE=volylegume_test \
  DB_USERNAME=volylegume DB_PASSWORD=volylegume_secret php artisan test
```

Ces variables d'environnement prennent le pas sur les valeurs SQLite de `phpunit.xml`.

## Rapport technique

Le rapport technique complet (architecture, sécurité, modèle de données, audit, tests, déploiement) est disponible dans `RAPPORT_TECHNIQUE.pdf` et sa source Markdown `RAPPORT_TECHNIQUE.md`.

## Journal et audit

Chaque écriture métier crée une ligne dans `audit_logs` : exploitation, utilisateur, action, entité concernée, anciennes/nouvelles valeurs, adresse IP et contexte. Les créations, modifications et suppressions passent par le trait `Auditable` ; les connexions, les refus d'autorisation (403) et les ressources masquées par l'isolation (404) sont également tracés.

Le journal est consultable par les responsables de l'exploitation (`/journal`) et, de façon transversale, par un administrateur de plateforme (`/admin/journal`).

## Rentabilité, exports et impression

L'écran `/rentabilite` agrège les dépenses, recettes et consommations d'eau par campagne, culture et parcelle. Les surfaces utilisées sont les surfaces cultivées cumulées, les campagnes annulées sont exclues et les montants sont exprimés en Ariary (Ar). Les filtres `from`, `to` et `campaign_id` sont conservés dans les liens d'export.

- `GET /rentabilite/export.csv` : rentabilité détaillée ;
- `GET /rentabilite/impression` : vue imprimable, à enregistrer en PDF depuis le navigateur ;
- `GET /exports/campagnes.csv`, `/exports/stocks.csv`, `/exports/traitements.csv`, `/exports/ventes.csv`, `/exports/finances.csv` : exports métier.

Les CSV utilisent le point-virgule, un BOM UTF-8 et neutralisent les valeurs commençant par `=`, `+`, `-` ou `@` pour éviter l'exécution de formules dans un tableur.

## Plan cartographique et contours

Les contours de parcelles sont saisis sur `/parcelles/{parcelle}/contour` puis stockés en GeoJSON (longitude / latitude, ordre GeoJSON). La vue `/plan` affiche ces mêmes polygones en 2D avec Leaflet et en 3D avec Three.js.

- Un panneau « Couches d’information » active ou masque les cultures suivies, les campagnes, les activités à venir, les activités en retard, les récoltes, les états de récolte, l’irrigation, les alertes de parcelle et l’état des parcelles. Chaque couche possède sa légende et agit en 2D comme en 3D ; la sélection d’une parcelle continue d’ouvrir sa fiche. Les récoltes conservent leur unité d’origine : les quantités ne sont jamais additionnées entre unités différentes.
- Une section « Suivi des récoltes » filtre les parcelles, les badges, les fiches et la frise par état calculé : bloquée, en cours, à venir, en culture, terminée, terminée sans récolte, suspendue, annulée ou non renseignée. La frise distingue les récoltes enregistrées des récoltes planifiées et n’affiche aucune quantité pour une simple planification.
- Les couches et les états ne s’appuient que sur les enregistrements de l’exploitation active : campagnes et cultures, activités planifiées, récoltes enregistrées ou planifiées, récoltes bloquées, quotas et événements d’irrigation, statuts des parcelles. Une couche sans données est désactivée et explique pourquoi, sans valeur fictive. Les mentions « à venir » et « en retard » sont calculées à partir des dates et statuts saisis ; elles ne constituent pas un historique reconstitué. L’isolation entre exploitations et les autorisations existantes restent appliquées.
- Un bandeau rappelle en permanence le mode actif : contours 2D sur imagerie satellite ou plan OpenStreetMap, extrusion 3D symbolique sur imagerie plaquée à plat sans relief mesuré, ou plan indicatif basé sur les surfaces déclarées. Les contours 2D et la scène 3D sont accessibles au clavier (Tab, flèches, Entrée ou Espace) et les transitions restent brèves ; aucune animation permanente n’est utilisée pour les couches.
- Le fond 2D utilise par défaut l'imagerie satellite Esri, avec un basculement vers le plan OpenStreetMap ; les attributions restent visibles et les tuiles ne sont chargées qu'en ligne. La 3D plaque une image satellite Esri à plat sous les extrusions : c'est un décor, pas un modèle de terrain. Si le réseau est faible ou absent, les contours enregistrés restent affichés, un message le signale et le plan indicatif reste accessible.
- La 3D est une extrusion des polygones, pas un modèle de terrain. Aucune altitude, pente ou distance n'est inventée : la mention « Relief non disponible » est affichée tant qu'aucune source d'altitude fiable n'est configurée.
- Une parcelle sans contour n'est jamais placée au hasard : elle apparaît dans le plan indicatif, et seulement avec un point « position connue, contour à dessiner » sur la carte 2D si des coordonnées existent déjà. Elle n'est jamais extrudée en 3D.
- Le bouton « Imprimer / PDF » ouvre l’impression du navigateur sur une synthèse lisible : carte 2D (ou plan indicatif sans contour), statistiques, tableau des parcelles et légendes des couches actives. La vue 3D n’est pas imprimée, car son rendu n’est pas garanti et le relief n’est pas une donnée mesurée.
- Les tableaux de l’espace de travail partagent une hiérarchie typographique stable (en-têtes 600, texte 500, totaux et valeurs clés accentués), des chiffres alignés et des états de survol/focus discrets ; les lignes ne sont pas animées à chaque chargement.
- Sans JavaScript, une liste de repli affiche le code, la surface et l'état du contour de chaque parcelle.

## Mode hors connexion (PWA)

L'application est installable depuis un navigateur compatible (manifeste `public/manifest.webmanifest`). Le service worker met en cache la page de secours `public/offline.html` et les assets statiques ; les pages authentifiées ne sont volontairement pas mises en cache afin d'éviter toute fuite entre comptes sur un appareil partagé.

Les formulaires « Nouvelle activité » et « Nouvelle récolte » sont marqués `data-offline-form`. Hors connexion, la saisie est conservée dans `localStorage` sous le compte courant, puis renvoyée vers `/hors-ligne/activites` ou `/hors-ligne/recoltes` au retour du réseau. Chaque envoi porte une référence cliente unique : un renvoi du même formulaire ne crée pas de doublon. Une bannière indique les saisies en attente, permet de relancer la synchronisation et signale les rejets de validation.

Limites actuelles : la file locale ne couvre que les activités et les récoltes ; elle dépend du navigateur et du stockage local, et ne remplace pas une application mobile avec synchronisation en arrière-plan.

## Supervision et rétention

`GET /health` renvoie l'état de la base, de la file d'attente, de la sauvegarde la plus récente et du stockage. La réponse est `200` si l'état est `ok` ou `degraded`, `503` si un service est `down`.

```bash
php artisan system:health          # code retour 1 si l'état est down
php artisan system:health --json
```

Pour protéger l'endpoint, définissez `HEALTH_TOKEN` : le header `X-Health-Token` devient alors obligatoire. Les seuils sont configurables dans `config/health.php` (`backup_max_age_hours`, `queue_backlog_warning`, `failed_jobs_warning`).

La commande `audit:prune` supprime les entrées anciennes tout en conservant plus longtemps les actions sensibles (`expense.`, `revenue.`, `sale.`, `payment.`, `treatment.`, `harvest.`, `produce_lot.`, `stock_movement.`) :

```bash
php artisan audit:prune --days=365 --keep-days=1825 --dry-run
php artisan audit:prune --days=365 --keep-days=1825 --farm=1
```

Elle est planifiée chaque jour à 02:30 (`routes/console.php`). Pour l'activer en production, un cron doit exécuter `php artisan schedule:run` chaque minute.

## Administration plateforme

Les comptes marqués `is_platform_admin` accèdent à `/admin` : tableau de bord global, gestion des comptes (activation, rôle plateforme, rattachements) et supervision des exploitations. Les autres utilisateurs reçoivent une réponse 403.

## Sauvegarde et restauration

Deux commandes Artisan produisent et restaurent une sauvegarde vérifiable de la base et des fichiers publics (`storage/app/public` : photos de parcelles, QR codes, pièces des lots).

```bash
# Sauvegarde dans storage/backups/<horodatage> (base par défaut + storage/app/public)
php artisan backup:create

# Répertoire, connexion et stockage explicites
php artisan backup:create --path=/var/backups/volylegume --database=pgsql \
  --storage=/var/www/volylegume/storage/app/public

# Restauration (confirmation demandée ; --force pour automatiser)
php artisan backup:restore storage/backups/20261026-153000 --force
```

Chaque sauvegarde contient :

- `database.dump` (format custom `pg_dump`) ou `database.sqlite` (instantané `VACUUM INTO`) ;
- `storage.zip` (fichiers publics) ;
- `manifest.json` : date, pilote, empreintes SHA-256 et nombre de lignes par table.

La restauration contrôle les empreintes et les compteurs de lignes ; une archive altérée est refusée. Pour une sauvegarde parfaitement cohérente, passez l'application en maintenance pendant l'opération. En production, arrêtez aussi le worker, restaurez, puis videz les caches :

```bash
php artisan down
php artisan backup:restore /var/backups/volylegume/20261026-153000 --force
php artisan optimize:clear
php artisan up
```

Prérequis : extension PHP `zip` et, pour PostgreSQL, `pg_dump`/`pg_restore` de la même version majeure que le serveur (18). Le cycle complet est couvert par `tests/Feature/BackupRestoreTest.php`, exécuté sur SQLite et PostgreSQL en CI.

## Commandes utiles

```bash
php artisan migrate:fresh --seed   # réinitialiser la base de démonstration
php artisan produce:backfill-lots  # créer les lots manquants des récoltes existantes
php artisan backup:create          # sauvegarder la base et les fichiers publics
php artisan backup:restore <path>  # restaurer et vérifier une sauvegarde
php artisan storage:link           # liens de stockage
php artisan queue:work             # worker de file d'attente
php artisan test                   # suite de tests
vendor/bin/pint                    # formatage PSR-12 / Laravel
```

---

Projet académique — ESMIA, 2026. Les noms, quantités et scénarios sont fictifs et destinés uniquement à la démonstration.
