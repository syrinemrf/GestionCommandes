-- Executer sur la base MariaDB Symfony.

-- KPI quotidiens. Les unites sont controlees dans la requete suivante pour
-- eviter de multiplier les totaux de commande par le nombre de lignes.
-- Le TTC est arrondi commande par commande comme Commande::getTotalTtc().
select
    date(c.date) as calendar_date,
    c.fournisseur_id as source_supplier_id,
    count(*) as order_count,
    sum(c.statut = 'ANNULEE') as cancelled_order_count,
    round(sum(c.statut = 'ANNULEE') / count(*), 6) as cancellation_rate,
    round(sum(case when c.statut <> 'ANNULEE' then c.total_ht else 0 end), 3) as revenue_ht,
    round(sum(case
        when c.statut <> 'ANNULEE'
            then round(c.total_ht * (1 + c.taux_tva / 100), 3)
        else 0
    end), 3) as revenue_ttc
from commande c
where c.is_deleted = 0
group by date(c.date), c.fournisseur_id
order by calendar_date, source_supplier_id;

select
    date(c.date) as calendar_date,
    c.fournisseur_id as source_supplier_id,
    sum(l.quantite) as ordered_units
from commande c
inner join ligne_commande l on l.commande_id = c.id
where c.is_deleted = 0
  and c.statut <> 'ANNULEE'
group by date(c.date), c.fournisseur_id
order by calendar_date, source_supplier_id;

-- Repartition actuelle par statut.
select fournisseur_id, statut, count(*) as current_order_count
from commande
where is_deleted = 0
group by fournisseur_id, statut
order by fournisseur_id, statut;

-- Etat de stock selon la formule Symfony.
select
    p.id_fournisseur_id as source_supplier_id,
    pv.product_id as source_product_id,
    pv.id as source_variation_id,
    pv.stock as stock_registered,
    pv.stock_utilise as stock_used,
    pv.stock_reserve as stock_reserved,
    pv.stock - pv.stock_utilise as stock_physical,
    pv.stock - pv.stock_utilise - pv.stock_reserve as stock_available
from product_variation pv
inner join product p on p.id = pv.product_id
order by source_supplier_id, source_product_id, source_variation_id;
