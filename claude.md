# Guide de développement - Plugin WordPress "Tableau Équipes"

## Vue d'ensemble du projet

Extension WordPress pour gérer le déplacement de joueurs dans des équipes sur plusieurs journées de championnat de tennis de table.

**Dépendance requise** : [DataPing](https://github.com/robinos33/DataPing/tree/master)

## Contraintes techniques

### Backend (PHP)
- **Version minimale** : PHP 8.1
- **Framework** : WordPress Plugin API
- **Architecture** : Orientée objet, respect des standards WordPress
- **Tests** : Tests unitaires obligatoires avec PHPUnit

### Frontend
- **Framework** : Vue.js (standalone, sans build)
- **PAS de Webpack ni Node.js** : Le plugin doit tourner sur hébergement mutualisé de base
- **Vue.js** : Utiliser la version CDN ou intégrer le fichier directement
- **Styling** : Tailwind CSS (via CDN ou fichier compilé)
- **Tests** : Tests unitaires JavaScript obligatoires

### Design
- Interface moderne et épurée
- Utilisation de Tailwind CSS pour tous les composants
- Responsive design obligatoire
- UX intuitive pour le drag & drop des joueurs

## Workflow de développement

### Tests obligatoires
**CRITIQUE** : Aucun commit ne peut être validé sans tests passants.

#### Backend (PHP)
- Tests unitaires PHPUnit pour toutes les classes
- Tests d'intégration pour les interactions WordPress
- Couverture minimale : à définir par fonctionnalité
- Commande de test : `composer test` (ou équivalent)

#### Frontend (JavaScript)
- Tests unitaires pour les composants Vue.js
- Tests des interactions utilisateur (drag & drop, validation)
- Framework de test : à définir (Vitest, Jest sans build, ou autre solution compatible)

### Processus de commit
1. Écrire le code
2. Écrire les tests (passants ET cas d'erreur)
3. Exécuter tous les tests
4. Si tous les tests passent → commit autorisé
5. Si des tests échouent → corriger avant commit

## Standards de code

### PHP
- PSR-12 pour le style de code
- Typage strict activé (`declare(strict_types=1)`)
- Documentation PHPDoc pour toutes les méthodes publiques
- Gestion d'erreurs robuste avec exceptions

### JavaScript
- ES6+ moderne
- Code modulaire et réutilisable
- Documentation JSDoc pour les fonctions complexes
- Gestion d'erreurs avec try/catch

## Structure du plugin

```
tableau-equipes/
├── tableau-equipes.php          # Fichier principal du plugin
├── includes/                     # Classes PHP
│   ├── class-plugin.php
│   ├── class-equipe.php
│   ├── class-joueur.php
│   └── class-journee.php
├── admin/                        # Interface admin WordPress
├── public/                       # Fichiers publics
│   ├── js/
│   │   ├── vue.min.js           # Vue.js (CDN ou local)
│   │   └── app.js               # Application Vue.js
│   └── css/
│       └── tailwind.min.css     # Tailwind compilé
├── tests/                        # Tests
│   ├── php/                     # Tests PHPUnit
│   └── js/                      # Tests JavaScript
├── composer.json                 # Dépendances PHP
└── package.json                  # Scripts de test (sans build)
```

## Intégration avec DataPing

- Vérifier la présence de DataPing au démarrage
- Utiliser les API/hooks fournis par DataPing
- Documenter les dépendances et interactions

## Bonnes pratiques

### Sécurité
- Échapper toutes les sorties avec `esc_html()`, `esc_attr()`, etc.
- Valider et assainir toutes les entrées utilisateur
- Utiliser les nonces WordPress pour les formulaires
- Vérifier les capacités utilisateur avec `current_user_can()`

### Performance
- Pas de bundler = attention à la taille des fichiers
- Minifier les assets en production
- Lazy loading des composants Vue.js si possible
- Optimisation des requêtes SQL

### Compatibilité hébergement mutualisé
- Pas de dépendance à Node.js en production
- Pas de compilation à la volée
- Ressources statiques uniquement
- Compatibilité PHP 8.1 sans extensions exotiques

## Notes pour Claude

- Toujours créer les tests AVANT de valider le code
- Penser aux cas limites et erreurs
- Documenter les choix techniques
- Vérifier la compatibilité hébergement mutualisé
- Ne jamais commiter sans tests passants
- Privilégier la simplicité et la maintenabilité
