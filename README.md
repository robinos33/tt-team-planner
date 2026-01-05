# Tableau Équipes - Plugin WordPress

Extension WordPress pour gérer le déplacement de joueurs dans des équipes sur plusieurs journées de championnat de tennis de table.

## Architecture

Ce plugin suit une architecture **Domain-Driven Design (DDD)** avec trois couches principales :

### Couche Domaine (`src/Domain/`)
- **Entités** : Joueur, Équipe, Journée
- **Interfaces Repository** : Contrats pour la persistance
- Logic métier indépendante de WordPress

### Couche Application (`src/Application/`)
- Use Cases : Orchestration de la logique métier
- DTOs : Transfert de données entre couches

### Couche Infrastructure (`src/Infrastructure/`)
- **Persistence** : Implémentations des repositories (actuellement avec fake data)
- **WordPress** : Adaptateurs pour l'intégration WordPress

## Prérequis

- PHP 8.1 ou supérieur
- WordPress 6.0 ou supérieur
- Plugin [DataPing](https://github.com/robinos33/DataPing) (dépendance requise)
- Composer (pour le développement)

## Installation

### Pour le développement

1. Cloner le dépôt dans le dossier plugins de WordPress :
```bash
cd wp-content/plugins
git clone [url-du-repo] tableau-equipes
cd tableau-equipes
```

2. Installer les dépendances :
```bash
composer install
```

3. Activer le plugin depuis l'interface WordPress

### Pour la production

1. Télécharger la release
2. Décompresser dans `wp-content/plugins/`
3. Activer le plugin depuis l'interface WordPress

## Développement

### Structure du projet

```
tableau-equipes/
├── src/
│   ├── Domain/              # Couche domaine (entités, interfaces)
│   ├── Application/         # Couche application (use cases)
│   └── Infrastructure/      # Couche infrastructure (repositories, WordPress)
├── admin/                   # Interface admin WordPress
├── public/                  # Assets publics (JS, CSS)
├── tests/
│   ├── Unit/               # Tests unitaires
│   ├── Integration/        # Tests d'intégration
│   └── JavaScript/         # Tests JavaScript
├── tableau-equipes.php     # Fichier principal du plugin
├── composer.json           # Dépendances PHP
├── package.json            # Scripts de test JavaScript
└── phpunit.xml            # Configuration PHPUnit
```

### Lancer les tests

#### Tests PHP (PHPUnit)

```bash
# Tous les tests
composer test

# Tests unitaires uniquement
composer test:unit

# Tests d'intégration uniquement
composer test:integration

# Avec couverture de code
composer test:coverage
```

#### Tests JavaScript

```bash
# Installer les dépendances
npm install

# Lancer les tests
npm test

# Mode watch
npm run test:watch

# Avec couverture
npm run test:coverage
```

### Standards de code

- **PHP** : PSR-12, typage strict activé
- **JavaScript** : ES6+, modulaire
- **Tests** : Obligatoires pour tout nouveau code

### Fake Data

Le plugin utilise actuellement des repositories avec fake data pour le développement :

- **~200 joueurs** avec différents niveaux (débutant à expert)
- **14 équipes** réparties sur 5 divisions
- **18 journées** de championnat avec différents statuts

Ces données sont générées automatiquement et permettent de tester le plugin sans base de données.

## Fonctionnalités

### Gestion des entités

#### Joueur
- Identité : ID, nom, prénom, licence FFTT
- Classement : Points FFTT (0-4000)
- Validation stricte des données

#### Équipe
- Composition : 4 joueurs maximum par défaut
- Division : Niveau de championnat
- Calcul automatique du total et moyenne des points

#### Journée
- Numéro et date
- Statut : brouillon, validée, terminée
- Compositions d'équipes
- Protection contre modification des journées terminées

### Permissions

- **Super-admin** : Accès complet au plugin
- **Utilisateurs autorisés** : Liste configurable par le super-admin
- **Autres utilisateurs** : Aucun accès

## Workflow de développement

1. **Écrire les tests** avant ou en même temps que le code
2. **Implémenter** la fonctionnalité
3. **Lancer les tests** et vérifier qu'ils passent tous
4. **Commit** uniquement si tous les tests passent

## État actuel du projet

✅ **Complété** :
- Structure de base du plugin
- Entités DDD avec tests (Joueur, Équipe, Journée)
- Interfaces des repositories
- Repositories avec fake data
- Configuration des tests PHP et JavaScript
- Fichier principal du plugin avec activation

🔄 **En cours** :
- Interface admin WordPress
- Use cases de l'application
- Intégration avec DataPing

⏳ **À venir** :
- Persistance en base de données
- Interface utilisateur Vue.js
- Drag & drop des joueurs
- Gestion des autorisations utilisateur
- API REST

## Contribuer

1. Créer une branche feature
2. Développer avec tests
3. Vérifier que tous les tests passent
4. Créer une pull request

## Licence

GPL v2 or later

## Support

Pour toute question ou problème, créer une issue sur le dépôt GitHub.
