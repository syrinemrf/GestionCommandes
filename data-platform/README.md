# Comdely Data Platform

Ce dossier contient l'infrastructure PostgreSQL du Data Warehouse. L'extraction
Python, dbt, Airflow, le dashboard et le ML ne font pas partie de cette étape.

## Configuration locale

Valeurs par défaut : base et utilisateur `comdely_dw`, port hôte `5433` et port
conteneur `5432`.

Pour personnaliser la configuration sous PowerShell :

```powershell
Copy-Item data-platform/.env.example data-platform/.env
docker compose --env-file data-platform/.env up -d warehouse
```

Le fichier `data-platform/.env` est ignoré par Git. Sans personnalisation :

```powershell
docker compose up -d warehouse
```

## Exploitation

```powershell
docker compose config --quiet
docker compose ps warehouse
docker compose exec warehouse pg_isready -U comdely_dw -d comdely_dw
```

Afficher les cinq schémas :

```powershell
docker compose exec warehouse psql -U comdely_dw -d comdely_dw -c "SELECT schema_name FROM information_schema.schemata WHERE schema_name IN ('raw', 'staging', 'analytics', 'marts', 'meta') ORDER BY schema_name;"
```

Arrêter ou redémarrer uniquement PostgreSQL DW :

```powershell
docker compose stop warehouse
docker compose start warehouse
docker compose restart warehouse
```

Ne pas utiliser `docker compose down -v` : cette commande supprimerait les
volumes persistants, notamment MariaDB et le Data Warehouse.

## Schémas

- `raw` : copie fidèle et traçable des sources MariaDB ;
- `staging` : typage, normalisation et contrôles techniques ;
- `analytics` : dimensions et faits historisés ;
- `marts` : KPI destinés à l'API et au dashboard ;
- `meta` : watermarks, lots d'extraction et suivi de qualité.

Le modèle cible est détaillé dans [docs/dw-model.md](docs/dw-model.md).
