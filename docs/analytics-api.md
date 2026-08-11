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

## GET `/api/analytics/overview?from=2026-07-01&to=2026-08-04`

Cette route alimente les cinq KPI de la vue d'ensemble et compare la periode
demandee a la periode precedente de meme duree. Les evolutions des commandes,
du CA HT et du panier moyen sont exprimees en pourcentage. Le taux
d'annulation est exprime en points.

Le delai de traitement est la duree entre `EN_PREPARATION` et `EXPEDIEE`, pour
les commandes ayant termine ce cycle. Une annulation avant expedition n'entre
donc pas dans le calcul.

```json
{
  "data": {
    "current": {
      "orderCount": 142,
      "revenueHt": 31842.5,
      "averageOrderValueHt": 237.631,
      "cancellationRate": 0.056338,
      "completedOrderCount": 118,
      "averageProcessingSeconds": 100800,
      "medianProcessingSeconds": 93600,
      "preparationToReadySeconds": 75600,
      "readyToShippedSeconds": 25200
    },
    "previous": {},
    "comparison": {
      "orderCountPercent": 8.4,
      "revenueHtPercent": 5.7,
      "averageOrderValueHtPercent": -2.1,
      "cancellationRatePoints": 0.8,
      "processingTimePercent": 4.2,
      "processingTimeSecondsDelta": 4080
    },
    "lastUpdatedAt": "2026-08-05 10:20:00+00"
  },
  "meta": {
    "period": {"from": "2026-07-01", "to": "2026-08-04"},
    "previousPeriod": {"from": "2026-05-27", "to": "2026-06-30"}
  }
}
```

Une variation relative vaut `null` lorsque la periode precedente vaut zero et
la periode courante est non nulle. Le frontend peut alors afficher « nouvelle
activite » sans division par zero.

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

## GET `/api/analytics/risks?risk=HIGH&limit=100`

`risk` est facultatif et accepte `HIGH`, `MEDIUM`, `LOW` ou
`INSUFFICIENT_DATA`. L'identifiant fournisseur n'est jamais accepté depuis le
navigateur.

```json
{
  "data": [{
    "productId": 42,
    "variationId": 91,
    "productName": "Serum HydraGlow",
    "variationName": "30 ml",
    "predictionDate": "2026-08-10",
    "predictedAt": "2026-08-10 11:50:00+00",
    "forecastCentral7d": 9.2,
    "forecastQ90_7d": 12.8,
    "stockAvailable": 4,
    "risk": "HIGH",
    "recommendedQuantity": 9,
    "modelVersion": "croston-hgb-20260810T030000Z",
    "demandHistory": [{"date": "2026-08-09", "quantity": 2}],
    "shapAvailable": true,
    "lastUpdatedAt": "2026-08-10 11:50:00+00"
  }],
  "meta": {"count": 1, "limit": 100, "risk": "HIGH"}
}
```

## GET `/api/analytics/risks/91/explanation`

Le frontend fournisseur utilise les champs metier `insights`, `recentTrend`,
`variability` et `recommendedAction`. Les contributions SHAP restent
disponibles pour compatibilite API, mais leurs valeurs brutes ne sont jamais
affichees a l'utilisateur metier. Une variable contribue a une prevision ;
elle ne prouve pas une causalite.

Cette route renvoie l'explication déterministe et les contributions SHAP déjà
calculées par l'inférence hors ligne. Une contribution décrit l'influence du
modèle, jamais une causalité.

```json
{
  "data": {
    "variationId": 91,
    "productName": "Serum HydraGlow",
    "variationName": "30 ml",
    "stockAvailable": 4,
    "forecastCentral7d": 9.2,
    "forecastQ90_7d": 12.8,
    "deficit": 6,
    "risk": "HIGH",
    "recommendedQuantity": 9,
    "factors": [{
      "name": "rolling_sum_7d",
      "value": 8,
      "contribution": 1.284,
      "direction": "INCREASES"
    }],
    "shapAvailable": true,
    "modelVersion": "croston-hgb-20260810T030000Z",
    "predictedAt": "2026-08-10 11:50:00+00",
    "wording": "Ces facteurs contribuent à la prévision ; ils ne prouvent pas une causalité."
  }
}
```

Un cold start conserve toute l'explication métier mais renvoie `factors: []`
et `shapAvailable: false`.

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
    -v analytics_reader_password=CHANGE_ME
```

Configurer ensuite localement :

```dotenv
DW_DATABASE_URL="postgresql://comdely_analytics_reader:CHANGE_ME@127.0.0.1:5433/comdely_dw?serverVersion=16&charset=utf8"
```

Le role possede seulement `CONNECT`, `USAGE` sur `analytics` et `SELECT` sur
les six marts exposes, dont `mart_supplier_order_processing_time`. Il n'a
aucun droit sur `raw`, `staging`, `meta`, les faits ou les dimensions.
