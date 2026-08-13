# Comparaison des modèles de demande

Évaluation chronologique. Les paramètres sont choisis sur la validation uniquement; le test final reste hors sélection.

| Approche | MAE | WAPE | Pinball q90 | Ruptures simulées | Unités manquantes | Surstock | Qté moyenne |
|---|---:|---:|---:|---:|---:|---:|---:|
| seasonal_baseline | 2.0516 | 1.211 | 0.985 | 0.3178 | 6785.0 | 7494.0 | 1.796 |
| croston_sba | 1.7755 | 1.0529 | 0.766 | 0.2363 | 4038.0 | 9630.0 | 2.4861 |
| hist_gradient_boosting_point | 1.7821 | 1.0568 | 0.7739 | 0.2344 | 4139.0 | 9500.0 | 2.4531 |
| hist_gradient_boosting_q90 | 3.5405 | 2.0995 | 0.4673 | 0.0489 | 739.0 | 26882.0 | 5.4253 |

## Décision

Croston-SBA obtient le meilleur WAPE. Le modèle global améliore la baseline mais ne justifie pas sa complexité supplémentaire; Croston-SBA est retenu à ce stade.

Les comparaisons détaillées par fournisseur, variation et intermittence sont conservées dans le JSON de résultats.

> Ces données sont synthétiques : leurs performances ne garantissent pas celles de futures données réelles.
