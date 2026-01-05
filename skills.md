# Compétences et bonnes pratiques - Développement Claude

## Philosophie de développement

### Test-Driven Development (TDD)
- **Règle d'or** : Aucun code ne peut être commité sans tests passants
- Écrire les tests AVANT ou EN MÊME TEMPS que le code
- Couvrir les cas nominaux ET les cas d'erreur
- Tests unitaires + tests d'intégration quand pertinent

### Qualité du code
- Code propre, lisible et maintenable
- Pas de sur-ingénierie : garder les choses simples
- Documentation inline pour la logique complexe
- Respect strict des standards du langage

## Compétences techniques requises

### WordPress Development
- [ ] Création de plugins WordPress
- [ ] Hooks et filtres WordPress (actions/filters)
- [ ] Admin UI WordPress
- [ ] WordPress REST API (si nécessaire)
- [ ] Gestion des options et transients
- [ ] Enqueue scripts et styles
- [ ] Sécurité WordPress (nonces, sanitization, escaping)
- [ ] Internationalisation (i18n)

### PHP 8.1+
- [ ] Programmation orientée objet moderne
- [ ] Typage strict et types union/intersection
- [ ] Namespaces et autoloading (PSR-4)
- [ ] PHPUnit pour les tests
- [ ] Gestion des exceptions
- [ ] PHP moderne (propriétés promoted, match, etc.)

### Vue.js (sans build)
- [ ] Vue.js 3 en mode standalone (CDN)
- [ ] Composants Vue.js
- [ ] Réactivité et state management
- [ ] Directives Vue (v-model, v-for, v-if, etc.)
- [ ] Events et props
- [ ] Lifecycle hooks
- [ ] Drag & Drop avec Vue.js
- [ ] Tests JavaScript unitaires

### Tailwind CSS
- [ ] Classes utilitaires Tailwind
- [ ] Responsive design
- [ ] Customisation du thème
- [ ] Dark mode (si applicable)
- [ ] Design moderne et épuré

### Testing
- [ ] PHPUnit pour PHP
- [ ] Framework de test JS (compatible sans build)
- [ ] Mocking et stubs
- [ ] Assertions et expectations
- [ ] Tests de composants Vue.js
- [ ] Couverture de code

## Workflow Claude

### 1. Analyse de la tâche
- Comprendre les spécifications
- Identifier les dépendances
- Planifier l'architecture
- Lister les tests nécessaires

### 2. Développement
- Créer la structure de base
- Implémenter la logique métier
- Ajouter la sécurité et validation
- Documenter le code

### 3. Tests
- Écrire tests unitaires backend (PHP)
- Écrire tests unitaires frontend (JS)
- Tester les cas limites
- Vérifier la couverture de code
- **BLOQUER si tests échouent**

### 4. Validation
- Relecture du code
- Vérification standards
- Test manuel si nécessaire
- Documentation complète

### 5. Commit
- Message de commit descriptif
- Seulement si TOUS les tests passent
- Inclure les fichiers de test dans le commit

## Checklist avant commit

### Code
- [ ] Respect des standards (PSR-12 pour PHP, ES6+ pour JS)
- [ ] Pas de code mort ou commentaires inutiles
- [ ] Variables et fonctions bien nommées
- [ ] Pas de warnings ni d'erreurs
- [ ] Code documenté si nécessaire

### Sécurité
- [ ] Toutes les entrées sont validées et assainies
- [ ] Toutes les sorties sont échappées
- [ ] Nonces WordPress pour les formulaires
- [ ] Vérification des capacités utilisateur
- [ ] Pas de SQL injection possible
- [ ] Pas de XSS possible

### Tests
- [ ] Tests unitaires écrits et passants
- [ ] Cas nominaux testés
- [ ] Cas d'erreur testés
- [ ] Cas limites testés
- [ ] Commande `composer test` (ou équivalent) réussit
- [ ] Commande de test JS réussit

### Performance & Compatibilité
- [ ] Pas de dépendance à Node.js en production
- [ ] Compatible hébergement mutualisé
- [ ] Fichiers minifiés pour la production
- [ ] Requêtes optimisées
- [ ] Compatible PHP 8.1+

### Documentation
- [ ] README à jour si nécessaire
- [ ] PHPDoc/JSDoc pour API publique
- [ ] Commentaires pour logique complexe
- [ ] Guide d'utilisation si fonctionnalité user-facing

## Anti-patterns à éviter

### ❌ Ne JAMAIS faire
- Commiter sans tests passants
- Utiliser Webpack ou bundler (contrainte projet)
- Oublier l'échappement des sorties
- Ignorer les erreurs silencieusement
- Coder sans comprendre les specs
- Copier-coller sans adapter
- Laisser des `console.log()` en production
- Utiliser des extensions PHP non-standard

### ✅ Toujours faire
- Tester avant de commiter
- Valider les entrées utilisateur
- Échapper les sorties
- Documenter les choix techniques
- Penser à la sécurité
- Optimiser pour hébergement mutualisé
- Écrire du code maintenable
- Communiquer les blocages

## Ressources

### WordPress
- [Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Coding Standards](https://developer.wordpress.org/coding-standards/)
- [Security](https://developer.wordpress.org/apis/security/)

### Vue.js
- [Vue 3 Documentation](https://vuejs.org/)
- [Vue without build step](https://vuejs.org/guide/quick-start.html#using-vue-from-cdn)

### Tailwind CSS
- [Documentation](https://tailwindcss.com/docs)
- [CDN Usage](https://tailwindcss.com/docs/installation/play-cdn)

### Testing
- [PHPUnit](https://phpunit.de/)
- [WordPress Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/phpunit/)

## Notes importantes

Ce projet a des contraintes spécifiques liées à l'hébergement mutualisé. Claude doit systématiquement vérifier que les solutions proposées ne nécessitent pas de build step ou de Node.js en production.

La qualité et la sécurité du code sont prioritaires. En cas de doute, privilégier la solution la plus sûre et la plus simple.
