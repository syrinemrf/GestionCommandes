# Modèle cible du Data Warehouse Comdely

## Architecture

```text
MariaDB Symfony
      |
      v
Extraction et chargement Python
      |
      v
PostgreSQL DW (raw -> staging -> analytics -> marts)
      |
      v
Transformations dbt et orchestration Airflow
      |
      v
API Symfony sécurisée -> dashboard ECharts fournisseur
      |
      v
Jeux de données ML et explications XAI
```

Cette étape initialise seulement PostgreSQL. Aucun composant Python, dbt,
Airflow, dashboard ou ML n'est encore créé.

## Tables sources MariaDB

| Table source | Rôle analytique |
| --- | --- |
| `user` | Fournisseurs et acteurs des opérations |
| `client` | Clients rattachés à un fournisseur |
| `product` | Catalogue produit et prix de base courant |
| `product_variation` | Variations, références et état courant du stock |
| `commande` | En-tête, statut courant, TVA et total HT |
| `ligne_commande` | Quantités et prix unitaires HT figés à la commande |
| `historique_statut_commande` | Transitions et délais du cycle de commande |
| `mouvement_stock` | Journal des entrées, réservations, sorties et retours |
| `parametre` | Paramètres opérationnels courants |
| `reclamation` | Extension future pour les KPI support |
| `reclamation_message` | Extension future pour les échanges support |

`doctrine_migration_versions` est technique et reste exclue des modèles.

## Dimensions et clés

| Dimension | Source | Clé naturelle | Clé substitut |
| --- | --- | --- | --- |
| `dim_date` | Calendrier généré | `calendar_date` | `date_key` (`YYYYMMDD`) |
| `dim_fournisseur` | `user` fournisseur | `user.id` + source | `fournisseur_key` |
| `dim_utilisateur` | `user` | `user.id` + source | `utilisateur_key` |
| `dim_client` | `client` | `client.id` + source | `client_key` |
| `dim_produit` | `product` | `product.id` + source | `produit_key` |
| `dim_variation` | `product_variation` | `product_variation.id` + source | `variation_key` |
| `dim_statut_commande` | Workflow Symfony | code statut | `statut_key` |
| `dim_type_mouvement` | Types de stock | code type | `type_mouvement_key` |

Les identifiants MariaDB restent les clés naturelles de traçabilité. Les clés
substituts PostgreSQL découplent les faits du système source et permettront une
historisation SCD. Chaque clé naturelle est qualifiée par `source_system`.

## Faits et grains

| Fait | Source | Grain | Clé naturelle source | Mesures principales |
| --- | --- | --- | --- | --- |
| `fact_commande` | `commande` | Une ligne par commande | `commande.id` | total HT, TVA, total TTC |
| `fact_ligne_commande` | `ligne_commande` | Une ligne par ligne de commande | `ligne_commande.id` | quantité, prix HT, total ligne HT |
| `fact_transition_statut` | `historique_statut_commande` | Une ligne par transition | historique `id` | durée entre transitions |
| `fact_mouvement_stock` | `mouvement_stock` | Un mouvement par variation | mouvement `id` | quantité, écarts physique et réservé |
| `fact_snapshot_stock` | `product_variation` | Une variation par instant de snapshot | variation + instant | physique, réservé, disponible |
| `fact_reclamation` | `reclamation` | Une ligne par réclamation | `reclamation.id` | délai de résolution, messages |

Chaque fait possède une clé substitut propre. Les identifiants de commande et de
ligne restent disponibles comme dimensions dégénérées pour l'audit.

## Mapping relationnel

- `fact_commande` référence la date, le fournisseur, le client, le créateur et
  le statut courant.
- `fact_ligne_commande` référence la commande, le produit et la variation. Les
  noms et prix figés de la ligne sont conservés.
- `fact_transition_statut` référence la commande, les statuts ancien et nouveau,
  la date et l'utilisateur auteur.
- `fact_mouvement_stock` référence variation, produit, fournisseur, type, date,
  acteur et éventuellement commande.
- `fact_snapshot_stock` référence variation, produit, fournisseur et date.

## Sécurité et qualité

- Ne jamais charger `user.password`.
- Ne jamais exposer directement les coordonnées personnelles des clients.
- Filtrer l'API avec le fournisseur de l'utilisateur authentifié.
- Vérifier la cohérence fournisseur entre client, produit, variation, commande
  et mouvement.
- Conserver les suppressions logiques pour l'historique.
- Utiliser `numeric`, jamais des flottants, pour les montants.
- Définir le fuseau horaire avant le premier ETL.
