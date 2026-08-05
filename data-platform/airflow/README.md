# Airflow local pour Comdely DW

Cette installation orchestre uniquement le chargement incremental MariaDB vers
PostgreSQL et les transformations dbt. Elle utilise Airflow 3.1.8 avec
`LocalExecutor`. Sa base PostgreSQL `airflow_metadata` et son volume
`airflow_metadata_data` sont distincts du DW `comdely_dw`.

Le DAG `comdely_dw_daily` execute, dans l'ordre :

1. disponibilite de MariaDB et du DW PostgreSQL ;
2. `python -m comdely_elt incremental` ;
3. verification du statut ELT et des comptages physiques `raw` ;
4. `dbt build` ;
5. tests dbt de reconciliation `assert_mart_*` ;
6. enregistrement du succes dans `meta.airflow_pipeline_run`.

Un echec final est egalement enregistre par le callback du DAG. Le succes ne
peut donc pas etre ecrit si `dbt build` ou la reconciliation echoue. Aucun
chargement complet n'est appele par Airflow.

## Configuration

Toutes les commandes suivantes sont lancees depuis la racine du depot.

```powershell
Copy-Item data-platform/airflow/.env.example data-platform/airflow/.env
```

Modifier le fichier local `.env`, ignore par Git. La source MariaDB situee sur
Windows doit utiliser `host.docker.internal`, et non `127.0.0.1`. En cas de
MariaDB Docker, utiliser `database:3306`.

Le planning est configure par `COMDELY_DW_SCHEDULE`, avec `0 2 * * *` par
defaut. `catchup` est desactive et une seule execution peut etre active.

Pour alleger les commandes :

```powershell
$compose = @(
  "-f", "compose.yaml",
  "-f", "data-platform/airflow/compose.airflow.yaml",
  "--env-file", "data-platform/airflow/.env"
)
```

## Initialisation et demarrage

Construire l'image versionnee puis initialiser uniquement la base de metadata :

```powershell
docker compose @compose --profile airflow build
docker compose @compose --profile airflow up airflow-init
```

Demarrer ensuite le DW et les services Airflow :

```powershell
docker compose @compose --profile airflow up -d warehouse airflow-postgres airflow-apiserver airflow-scheduler airflow-dag-processor
docker compose @compose --profile airflow ps
```

L'interface est disponible sur `http://127.0.0.1:8081` par defaut. Les
identifiants viennent exclusivement du fichier `.env` local.

## Import et declenchement

Verifier que le DAG est importable :

```powershell
docker compose @compose exec airflow-scheduler airflow dags list-import-errors
docker compose @compose exec airflow-scheduler airflow dags show comdely_dw_daily
```

Declencher une execution manuelle :

```powershell
docker compose @compose exec airflow-scheduler airflow dags trigger comdely_dw_daily
```

Suivre les executions :

```powershell
docker compose @compose exec airflow-scheduler airflow dags list-runs -d comdely_dw_daily
docker compose @compose logs -f airflow-scheduler airflow-dag-processor
```

Les logs de chaque tache sont egalement disponibles dans l'interface Airflow.

## Relancer une execution

Corriger d'abord la cause, puis effacer uniquement les instances de taches de
l'execution concernee depuis l'interface. En ligne de commande :

```powershell
docker compose @compose exec airflow-scheduler airflow tasks clear comdely_dw_daily --start-date 2026-08-05 --end-date 2026-08-05 --yes
```

Pour une nouvelle execution independante :

```powershell
docker compose @compose exec airflow-scheduler airflow dags trigger comdely_dw_daily
```

L'ELT incremental est idempotent ; Airflow ne lance jamais le mode `full`.

## Arret sans suppression de volumes

```powershell
docker compose @compose --profile airflow stop
```

Pour supprimer uniquement les conteneurs et reseaux tout en conservant les
volumes :

```powershell
docker compose @compose --profile airflow down
```

Ne jamais ajouter `-v` ou `--volumes` a la commande `down`.

## Audit

Le resultat fonctionnel est consultable dans le DW :

```sql
select *
from meta.airflow_pipeline_run
order by started_at desc;
```

Airflow conserve en parallele son historique technique dans sa base de metadata
separee.
