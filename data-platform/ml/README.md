# Préparation du dataset ML Comdely

Cette première étape ne réalise aucun entraînement complexe. Elle charge
uniquement `analytics.mart_ml_daily_demand`, construit des variables sans
fuite temporelle et évalue une baseline saisonnière hebdomadaire.

Le stock courant est volontairement absent du mart et des features. Il sera
combiné aux prévisions dans une étape métier ultérieure.

## Installation

Depuis la racine du dépôt :

```powershell
python -m venv data-platform/ml/.venv
data-platform/ml/.venv/Scripts/python.exe -m pip install -e 'data-platform/ml[test]'
```

Définir `DW_DATABASE_URL` avec un utilisateur PostgreSQL en lecture seule.
Les dates correspondent par défaut au découpage demandé :

```powershell
$env:ML_DATASET_START='2024-08-04'
$env:ML_DATASET_END='2026-08-04'
data-platform/ml/.venv/Scripts/python.exe -m comdely_ml
```

Pour enregistrer le dataset préparé dans le dossier ignoré par Git :

```powershell
data-platform/ml/.venv/Scripts/python.exe -m comdely_ml --output data-platform/ml/artifacts/daily-demand-features.csv
```

## Variables

- cible : somme de la demande des sept jours suivants ;
- lags : 1, 7, 14 et 28 jours ;
- moyenne, somme et écart-type glissants : 7, 14 et 28 jours ;
- tendance : moyenne récente 7 jours moins moyenne 28 jours ;
- calendrier : jour, semaine, mois, trimestre, week-end et encodages cycliques ;
- intermittence : jour précédent nul, série de zéros, jours non nuls sur 28
  jours, taux de zéros et délai depuis la dernière vente.

Toutes les fenêtres sont calculées après `shift(1)`. Les sept dernières dates
peuvent ne pas avoir de cible mature ; elles restent dans le dataset mais ne
sont pas utilisées dans les métriques.

La baseline prédit la demande des sept prochains jours avec la somme de la
demande des sept derniers jours entièrement observés.

## Séparation chronologique

- entraînement : du 4 août 2024 au 31 décembre 2025 ;
- validation : du 1er janvier au 31 mai 2026 ;
- test final : du 1er juin au 4 août 2026.

Les lignes dont la cible à sept jours n'est pas encore entièrement observable
restent disponibles pour une future prédiction, mais sont exclues des métriques.
Une purge de sept jours est également appliquée en fin de chaque sous-ensemble :
aucune cible d'entraînement ne consulte la validation et aucune cible de
validation ne consulte le test.

## Tests

```powershell
data-platform/ml/.venv/Scripts/python.exe -m pytest data-platform/ml/tests
```

## Benchmark reproductible

Depuis `data-platform/ml` :

```powershell
.\.venv\Scripts\python.exe -m comdely_ml.benchmark --seed 20260804 --artifacts artifacts --results artifacts/model_results.json --report reports/model_comparison.md
```

Les pipelines complets `joblib` et le JSON détaillé sont des artefacts locaux
ignorés par Git. Le rapport synthétique sous `reports/` est versionné. Les
paramètres sont choisis sur la validation uniquement, puis le test final est
évalué une seule fois.

## Utilisation opérationnelle

Le modèle déployé est un bundle versionné : Croston-SBA produit la prévision
centrale et le pipeline HGB quantile produit q90. Il ne possède aucune connexion
vers MariaDB et ne modifie jamais les stocks.

```powershell
python -m comdely_ml.migrations --directory data-platform/postgres/migrations
python -m comdely_ml.operational_training --version croston-hgb-20260810T120000Z --artifact-dir data-platform/ml/artifacts
python -m comdely_ml.inference
```

L'inférence effectue un upsert au grain date × variation dans
`ml.stock_prediction`. Un historique inférieur à 28 jours ou comportant moins
de deux jours de vente est classé `INSUFFICIENT_DATA`. Les recommandations sont
informatives : aucune entrée, réservation ou sortie de stock n'est créée.
