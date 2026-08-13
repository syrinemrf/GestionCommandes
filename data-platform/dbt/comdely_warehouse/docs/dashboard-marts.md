# Marts du dashboard fournisseur

Les marts restent specialises afin d'eviter une table large melangeant ventes,
statuts et stock. Chaque mart expose `source_supplier_id`, la cle naturelle
MariaDB utilisee par Symfony pour appliquer le filtre du fournisseur connecte.

## Regles communes

- Les commandes avec `is_deleted = true` sont exclues des KPI operationnels.
- Une commande `ANNULEE` reste comptee dans `order_count` et
  `cancelled_order_count`, mais elle ne genere ni chiffre d'affaires, ni unite
  vendue, ni panier moyen.
- `cancellation_rate = cancelled_order_count / order_count`. La valeur est un
  ratio entre 0 et 1.
- `average_order_value_ht = revenue_ht / nombre de commandes non annulees`.
- Les montants de vente viennent du prix historique de `ligne_commande`, jamais
  du prix courant du catalogue.
- `last_updated_at` represente la date d'extraction la plus recente ayant
  contribue a la ligne, et non l'heure du build dbt.

## Grains

- `mart_supplier_daily_kpi` : date et fournisseur. Le calendrier est complet
  entre la premiere et la derniere commande, y compris les jours sans vente.
- `mart_supplier_product_performance` : mois, fournisseur, produit et variation.
- `mart_supplier_product_daily_performance` : date, fournisseur et produit ;
  utilise pour comparer exactement deux periodes de meme duree.
  `product_rank` classe le chiffre d'affaires HT total du produit dans le mois.
- `mart_supplier_order_status` : fournisseur et statut courant du Workflow.
  `average_transition_duration_seconds` mesure le delai entre la transition
  precedente et l'entree dans le statut.
- `mart_supplier_stock_overview` : fournisseur, produit et variation.

## Stock

Les formules reprennent `ProductVariation` :

```text
stock_physical  = stock_registered - stock_used
stock_available = stock_registered - stock_used - stock_reserved
```

`stock_registered` correspond a `product_variation.stock`. Cette valeur est
augmentee lors d'un reapprovisionnement. `has_observed_stockout` devient vrai
si un mouvement historique a laisse un stock disponible inferieur ou egal a
zero. Les produits et variations supprimes logiquement restent presents avec
leurs indicateurs `*_is_deleted`.

Les requetes de controle croise sont disponibles dans `reconciliation/`.
