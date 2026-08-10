# Comdely — Gestion des commandes

Application Symfony de gestion de produits, variations, commandes et stocks.

## Données de démonstration

Le générateur crée des données entièrement synthétiques destinées aux tests, au futur Data Warehouse, aux tableaux de bord et aux travaux de prévision. Il réutilise le Workflow des commandes et les services métier de stock de l’application.

Les données sont identifiées par un lot déterministe. Une seconde exécution avec les mêmes paramètres ne crée aucun doublon. L’option `--reset-demo` ne supprime que les données portant ce marquage et conserve les données saisies manuellement. La commande refuse toujours de fonctionner dans l’environnement `prod`.

### Prévisualiser les volumes

```bash
php bin/console app:generate-demo-data --seed=20260804 --start-date=2024-08-04 --end-date=2026-08-04 --scale=medium --dry-run
```

### Petit dataset

Le petit volume sert aux validations rapides : 2 fournisseurs, 6 produits, 12 variations, 30 clients et 120 commandes.

```bash
php bin/console app:generate-demo-data --seed=20260804 --start-date=2024-08-04 --end-date=2026-08-04 --scale=small --reset-demo
```

### Dataset moyen

Le volume moyen contient 5 fournisseurs, 40 produits, 120 variations, 200 clients et 4 800 commandes. Les dates vont du 4 août 2024 au 4 août 2026 inclus. Août 2026 est volontairement limité à ses quatre premiers jours.

Pour conserver une transaction atomique malgré le volume ORM, cette commande réserve jusqu’à 512 Mo à son seul processus CLI. La limite mémoire de l’application web n’est pas modifiée.

```bash
php bin/console app:generate-demo-data --seed=20260804 --start-date=2024-08-04 --end-date=2026-08-04 --scale=medium --reset-demo
```

Le catalogue stable se trouve dans `config/demo/catalog.yaml`. Il définit les produits fictifs, leurs prix HT, variations, profils de demande et requêtes d’images.

Les comptes fournisseurs générés utilisent le domaine réservé `demo.comdely.test`. Leur mot de passe local de démonstration est `Demo-2026!`. Ces comptes ne doivent pas être utilisés en production.

### Simuler une activité récente

Le simulateur ajoute un lot fini aux données existantes sans modifier le
générateur historique :

```powershell
php bin/console app:simulate-live-activity --orders=10 --status-updates=10 --seed=20260810 --dry-run
php bin/console app:simulate-live-activity --orders=10 --status-updates=10 --seed=20260810
```

Les dates commencent après le dernier événement métier enregistré. La même
graine reproduit les mêmes choix à partir du même état initial de la base.
Chaque exécution crée volontairement de nouvelles activités. Un lot est limité
à 100 commandes et 200 transitions, et la commande est toujours refusée en
environnement `prod`.

La création et les transitions passent par les services de commande, le
Workflow Symfony et le service de stock. Pour actualiser ensuite le dashboard,
déclencher le DAG incrémental `comdely_dw_daily`.

## Images de démonstration Pexels

Définir la clé uniquement dans `.env.local` ou dans une variable d’environnement, jamais dans un fichier versionné :

```dotenv
PEXELS_API_KEY=votre_cle_pexels
```

Puis télécharger les images avant de générer le dataset :

```bash
php bin/console app:download-demo-images
```

Les fichiers sont placés dans `public/uploads/demo/products/` avec des noms déterministes. Un manifeste `manifest.json` conserve l’identifiant Pexels, la requête, l’URL de la photo, le photographe, le chemin local et la date de téléchargement. Une image existante n’est pas téléchargée à nouveau. Sans clé, sans connexion ou sans résultat pertinent, la commande crée des placeholders et continue normalement.

Photos provided by Pexels.

## Tests et contrôles

```bash
composer test
php bin/console lint:container
php bin/console lint:yaml config
php bin/console doctrine:schema:validate
```
