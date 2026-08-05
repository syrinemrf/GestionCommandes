# API Analytics fournisseur

Base URL : `/api/analytics`. Toutes les routes exigent une session Symfony avec
`ROLE_FOURNISSEUR`. L'identifiant du fournisseur vient exclusivement de
`User::getId()` ; un parametre navigateur comme `supplierId` est ignore.

Les dates utilisent `YYYY-MM-DD`, `from` doit preceder `to` et la periode est
limitee a 731 jours. Sans dates, la periode couvre les 30 derniers jours.

## GET `/api/analytics/summary?from=2026-07-01&to=2026-08-04`

```json
{
  "data": {
    "orderCount": 142,
    "cancelledOrderCount": 8,
    "cancellationRate": 0.056338,
    "revenueHt": 31842.5,
    "revenueTtc": 37892.575,
    "orderedUnits": 397,
    "averageOrderValueHt": 237.631,
    "averageOrderValueTtc": 282.78,
    "lastUpdatedAt": "2026-08-05 10:20:00+00"
  },
  "meta": {"period": {"from": "2026-07-01", "to": "2026-08-04"}}
}
```

Une periode vide renvoie les compteurs a zero et `lastUpdatedAt: null`.

## GET `/api/analytics/evolution?from=2026-07-01&to=2026-08-04`

```json
{
  "data": [{
    "date": "2026-07-01",
    "orderCount": 5,
    "cancelledOrderCount": 1,
    "revenueHt": 840.0,
    "revenueTtc": 999.6,
    "orderedUnits": 12
  }],
  "meta": {
    "period": {"from": "2026-07-01", "to": "2026-08-04"},
    "count": 35
  }
}
```

## GET `/api/analytics/products?from=2026-01-01&to=2026-08-04&limit=50`

`limit` est compris entre 1 et 100.

```json
{
  "data": [{
    "periodStart": "2026-08-01",
    "productId": 42,
    "variationId": 91,
    "productName": "Serum HydraGlow",
    "variationName": "30 ml",
    "unitsSold": 24,
    "revenueHt": 1128.0,
    "revenueTtc": 1342.32,
    "orderCount": 19,
    "productRank": 2,
    "productDeleted": false,
    "variationDeleted": false,
    "lastUpdatedAt": "2026-08-05 10:20:00+00"
  }],
  "meta": {
    "period": {"from": "2026-01-01", "to": "2026-08-04"},
    "count": 1,
    "limit": 50
  }
}
```

## GET `/api/analytics/statuses`

```json
{
  "data": [{
    "status": "EN_PREPARATION",
    "currentOrderCount": 12,
    "currentOrderShare": 0.084507,
    "measuredTransitionCount": 830,
    "averageTransitionDurationSeconds": 14520.4,
    "lastUpdatedAt": "2026-08-05 10:20:00+00"
  }],
  "meta": {"count": 7}
}
```

## GET `/api/analytics/stock?limit=100`

`limit` est compris entre 1 et 200.

```json
{
  "data": [{
    "productId": 42,
    "variationId": 91,
    "productName": "Serum HydraGlow",
    "variationName": "30 ml",
    "stockRegistered": 120,
    "stockUsed": 108,
    "stockReserved": 4,
    "stockPhysical": 12,
    "stockAvailable": 8,
    "currentlyOutOfStock": false,
    "observedStockout": true,
    "lastMovementType": "RESERVATION_COMMANDE",
    "lastMovementQuantity": 2,
    "lastMovementAt": "2026-08-04 14:30:00+00",
    "productDeleted": false,
    "variationDeleted": false,
    "lastUpdatedAt": "2026-08-05 10:20:00+00"
  }],
  "meta": {"count": 1, "limit": 100}
}
```

## Erreurs

Anonyme (`401`) :

```json
{"error":{"code":"AUTHENTICATION_REQUIRED","message":"Authentication required."}}
```

Parametres invalides (`400`) :

```json
{"error":{"code":"INVALID_QUERY_PARAMETERS","message":"The \"from\" parameter must use the YYYY-MM-DD format."}}
```

## Connexion PostgreSQL en lecture seule

Creer un mot de passe local fort, puis executer depuis la racine :

```powershell
Get-Content data-platform/postgres/security/create_analytics_reader.sql |
  docker compose exec -T warehouse psql -U comdely_dw -d comdely_dw `
    -v "analytics_reader_password='CHANGE_ME'"
```

Configurer ensuite localement :

```dotenv
DW_DATABASE_URL="postgresql://comdely_analytics_reader:CHANGE_ME@127.0.0.1:5433/comdely_dw?serverVersion=16&charset=utf8"
```

Le role possede seulement `CONNECT`, `USAGE` sur `analytics` et `SELECT` sur
les quatre marts exposes. Il n'a aucun droit sur `raw`, `staging`, `meta`, les
faits ou les dimensions.
