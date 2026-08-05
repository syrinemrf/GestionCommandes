# Comdely Warehouse dbt

Projet dbt PostgreSQL transformant les tables `raw` produites par l'ELT en vues
`staging`, puis en dimensions et faits dans `analytics`.

## Configuration

Aucun secret n'est versionné. Copiez le profil d'exemple puis fournissez le mot
de passe avec une variable d'environnement :

```powershell
Copy-Item data-platform/dbt/comdely_warehouse/profiles.yml.example data-platform/dbt/comdely_warehouse/profiles.yml
$env:DBT_POSTGRES_HOST="127.0.0.1"
$env:DBT_POSTGRES_PORT="5433"
$env:DBT_POSTGRES_USER="comdely_dw"
$env:DBT_POSTGRES_PASSWORD="mot-de-passe-local"
$env:DBT_POSTGRES_DB="comdely_dw"
$env:DBT_POSTGRES_SCHEMA="analytics"
```

Installation isolée :

```powershell
python -m venv data-platform/dbt/comdely_warehouse/.venv
data-platform/dbt/comdely_warehouse/.venv/Scripts/python.exe -m pip install -r data-platform/dbt/comdely_warehouse/requirements.txt
```

## Exécution

Depuis `data-platform/dbt/comdely_warehouse` :

```powershell
.venv/Scripts/dbt.exe debug --profiles-dir .
.venv/Scripts/dbt.exe build --profiles-dir .
.venv/Scripts/dbt.exe docs generate --profiles-dir .
```

Les fichiers générés dans `target`, `logs` et le profil local sont ignorés par
Git.

## Décisions de modélisation

- Une vue `stg_*` existe pour chaque table `raw`. Les vues renomment les champs,
  uniformisent les booléens, `timestamptz` et `numeric`, mais conservent les clés
  MariaDB.
- Les clés substituts sont des MD5 déterministes de `source_system` et de la clé
  naturelle source. Elles restent stables entre deux builds et permettent
  d'ajouter une nouvelle source sans collision.
- Les dimensions sont des tables courantes. Aucun SCD2 n'est ajouté : les
  transitions de commande et mouvements de stock portent déjà leur historique
  dans les faits dédiés.
- Les lignes supprimées logiquement restent dans les dimensions et les faits.
  Les indicateurs `is_deleted` permettent de les inclure ou exclure explicitement.
- `fact_order_line` est reconstruite comme table, car une commande en attente et
  ses lignes peuvent encore être modifiées ou supprimées physiquement.
- `fact_stock_movement` et `fact_order_status_history` sont incrémentaux avec
  `merge`, car leurs sources sont des journaux immuables chargés par identifiant.
- Le prix de vente provient exclusivement de
  `ligne_commande.prix_unitaire`. Le prix courant du produit n'est jamais utilisé
  pour recalculer une vente historique.
- `ANNULEE` est conservé dans les faits et fait partie des valeurs de statut
  acceptées.
- Les coordonnées directes du client restent dans `staging`; `dim_client`
  n'expose ni téléphone, ni email, ni rue.

## Grains

- `fact_order_line` : une ligne par `ligne_commande.id` ;
- `fact_stock_movement` : une ligne par `mouvement_stock.id` ;
- `fact_order_status_history` : une ligne par
  `historique_statut_commande.id`.

Les tests dbt contrôlent l'unicité de ces grains, les clés obligatoires, les
relations dimensionnelles, les statuts, les types de mouvement, les quantités et
les stocks non négatifs.
