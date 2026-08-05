# Comdely ELT MariaDB vers PostgreSQL

Ce package charge la couche `raw` du Data Warehouse sans jamais écrire dans la
base MariaDB Symfony. Les tables PostgreSQL conservent les identifiants sources
et ajoutent `extracted_at` et `etl_run_id`.

Le hash de mot de passe de `user` est volontairement exclu. Les coordonnées
clients restent des données sensibles et la couche `raw` ne doit pas être
exposée à l'API ou au dashboard.

## Installation

Depuis la racine du dépôt, sous PowerShell :

```powershell
python -m venv data-platform/elt/.venv
data-platform/elt/.venv/Scripts/python.exe -m pip install --upgrade pip
data-platform/elt/.venv/Scripts/python.exe -m pip install -e "data-platform/elt[test]"
```

Copier ensuite l'exemple local, qui est ignoré par Git :

```powershell
Copy-Item data-platform/elt/.env.example data-platform/elt/.env
```

Le package lit directement les variables d'environnement ; il ne charge pas le
fichier automatiquement. Exemple PowerShell :

```powershell
$env:SOURCE_DATABASE_URL="mysql+pymysql://readonly_user:mot-de-passe@127.0.0.1:3306/app?charset=utf8mb4"
$env:DW_DATABASE_URL="postgresql+psycopg://comdely_dw:mot-de-passe@127.0.0.1:5433/comdely_dw"
$env:ELT_BATCH_SIZE="500"
$env:ELT_SOURCE_SYSTEM="comdely_mariadb"
$env:ELT_SOURCE_TIMEZONE="Africa/Tunis"
```

En production, `SOURCE_DATABASE_URL` doit utiliser un compte MariaDB possédant
uniquement le droit `SELECT`. Le code force également chaque session source en
lecture seule.

## Commandes

Chargement initial atomique :

```powershell
data-platform/elt/.venv/Scripts/comdely-elt-full.exe
```

Chargement incrémental :

```powershell
data-platform/elt/.venv/Scripts/comdely-elt-incremental.exe
```

Équivalents avec le module Python :

```powershell
data-platform/elt/.venv/Scripts/python.exe -m comdely_elt full
data-platform/elt/.venv/Scripts/python.exe -m comdely_elt incremental
```

## Stratégies

- `mouvement_stock`, `historique_statut_commande` et
  `reclamation_message` : ajout par `id` supérieur au watermark, avec
  `ON CONFLICT DO NOTHING` ;
- `user`, `client`, `product`, `product_variation`, `commande`,
  `ligne_commande`, `parametre` et `reclamation` : scan par lots et upsert ;
- les tables sans `updated_at` sont rescannées afin de détecter leurs
  modifications ;
- une table temporaire d'identifiants permet de réconcilier les suppressions
  physiques ;
- `is_deleted` est conservé tel quel pour les suppressions logiques.

Le chargement métier et les watermarks sont validés dans une même transaction
PostgreSQL. En cas d'erreur, cette transaction est annulée et l'exécution est
marquée `FAILED` dans `meta.etl_run`.

## Tests

```powershell
$env:DW_DATABASE_URL="postgresql+psycopg://comdely_dw:mot-de-passe@127.0.0.1:5433/comdely_dw"
data-platform/elt/.venv/Scripts/python.exe -m pytest data-platform/elt/tests
```

Les tests PostgreSQL utilisent une transaction annulée en fin de test. Ils ne
laissent aucune ligne synthétique dans `raw` ou `meta`.
