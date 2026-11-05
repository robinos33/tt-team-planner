# Contribuer à TT Team Planner

Merci de l'intérêt pour ce plugin ! Les pull requests sont les bienvenues.

---

## Contexte du projet

Ce plugin a été développé par et pour l'**US Talence Tennis de Table**, club évoluant en championnat par équipes de la **ligue Nouvelle-Aquitaine** (FFTT). Certains choix de conception sont donc directement calqués sur le règlement et les usages locaux — ils sont documentés ci-dessous pour que vous puissiez juger si votre contexte est compatible, ou proposer une adaptation.

---

## Spécificités Nouvelle-Aquitaine

### Règles de brûlage

Le plugin implémente les règles **FFTT II.112.1** format 4 joueurs :

- **Règle 2** — après 2 rencontres dans une équipe de rang N, le joueur ne peut plus jouer dans une équipe de rang supérieur à N pour le reste de la phase.
- **Règle 3** — à la J2 d'une phase, une équipe ne peut aligner plus d'un joueur ayant joué la J1 dans une équipe de rang inférieur.

Ces règles sont communes à toutes les ligues FFTT au format 4 joueurs, mais certaines ligues ont des variantes (nombre de rencontres déclencheur, format 3 joueurs, etc.). Si votre ligue a des règles différentes, la logique est encapsulée dans `includes/Domain/BurnageChecker.php` — une PR qui la rend paramétrable sera appréciée.

### Noms des divisions

Les divisions proposées dans l'interface de configuration reflètent la hiérarchie en vigueur en Nouvelle-Aquitaine :

```
Régionale 1 / 2 / 3
Pré-Régionale
Départementale 1 / 2 / 3 / 4 / 5
```

Ces labels sont pré-remplis dans la datalist HTML de la page Réglages (`includes/Admin/SettingsPage.php`), mais le champ est libre — vous pouvez saisir n'importe quelle valeur.

Si votre ligue utilise une nomenclature différente (ex. : Honneur, Promotion, Inter-régional…), une PR qui rend cette liste configurable ou qui ajoute les nomenclatures d'autres ligues est la bienvenue.

### Format de saison et structure des phases

La saison est découpée en **deux phases de 7 journées** (septembre → janvier, février → juin). Ce découpage est codé en dur dans la logique de brûlage et de ventilation des effectifs.

D'autres ligues peuvent avoir des formats différents (une seule phase, plus de journées). Une PR qui paramétrise ce découpage est la bienvenue, à condition de ne pas complexifier l'interface pour les clubs qui n'en ont pas besoin.

---

## Ce qui est ouvert aux PR

Toutes les contributions sont les bienvenues, en particulier :

- **Adaptation à d'autres ligues** — nomenclature des divisions, variantes des règles de brûlage, formats de saison
- **Nouvelles fonctionnalités** — export PDF des compositions, notification par SMS/email, gestion des remplaçants, statistiques
- **Amélioration de l'UI** — accessibilité, performance, mode tablette
- **Traductions** — le plugin est i18n-ready (`text-domain: tt-team-planner`)
- **Tests** — augmenter la couverture PHP et JavaScript
- **Compatibilité** — support d'autres plugins de synchronisation FFTT que DataPing

---

## Guide de contribution

### 1. Prérequis

- PHP 8.2+, WordPress 6.4+
- Composer, Node.js

### 2. Installer l'environnement

Docker et Node.js suffisent : PHP tourne dans des conteneurs, quelle que soit la version installée sur le poste.

```bash
git clone <url-du-repo> tt-team-planner
cd tt-team-planner
docker run --rm -v "$PWD":/app -w /app composer:2.8 install
```

**WordPress local** — le plugin et [MonClubTT](https://github.com/robinos33/MonClubTT) dans un WordPress Docker (PHP 8.2, le minimum de MonClubTT) :

```bash
npx @wordpress/env@12.0.0 start    # http://localhost:8888, admin / password
npx @wordpress/env@12.0.0 stop
```

Le dossier du dépôt est monté tel quel : une modification est visible au rechargement de la page. L'import des joueurs passe par MonClubTT, qu'il faut configurer avec ses propres accès FFTT. Pour surcharger la config localement (port, version de MonClubTT), créer un `.wp-env.override.json`, ignoré par git.

### 3. Créer une branche

```bash
git checkout -b feat/ma-fonctionnalite
# ou
git checkout -b fix/mon-correctif
```

### 4. Conventions de code

**PHP**
- PSR-4 (namespace `TT\TeamPlanner\`)
- Typage strict (`declare(strict_types=1)`)
- Mise en forme PSR-12, et règles WordPress de sécurité, de SQL et d'API (phpcs, ruleset `phpcs.xml`)

```bash
docker run --rm -v "$PWD":/app -w /app composer:2.8 lint       # vérifier
docker run --rm -v "$PWD":/app -w /app composer:2.8 lint:fix   # corriger automatiquement
```

Une exception (`// phpcs:ignore <règle> -- <raison>`) nomme la règle et dit pourquoi elle ne s'applique pas. La CI lance le lint sur chaque PR.

**JavaScript**
- Vanilla JS ES5+ (aucun build step, aucun framework)
- L'interface est dans `assets/js/app.js` ; les calculs purs, testables sans navigateur, vont dans un module à part exposé sur `window` (`roster.js`, `share-image.js`), chargé avant `app.js`
- Le cycle `setState → render → attachEvents` doit rester le seul chemin de mise à jour du DOM

**Commits**
Format [Conventional Commits](https://www.conventionalcommits.org/) :

```
feat(burnage): rend le nombre de rencontres déclencheur paramétrable
fix(ui): corrige l'affichage du picker sur iOS 15
docs(readme): ajoute les captures d'écran
```

### 5. Tests

Toute nouvelle fonctionnalité doit être couverte par des tests :

```bash
docker run --rm -v "$PWD":/app -w /app php:8.2-cli vendor/bin/phpunit
```

Les tests PHP se trouvent dans `tests/Unit/` et ne démarrent pas WordPress : les fonctions WordPress sont simulées avec [Brain Monkey](https://giuseppe-mazzapica.gitbook.io/brain-monkey), et les repositories remplacés par des doublures en mémoire (voir `tests/Unit/Domain/BurnageCheckerTest.php`). La CI les lance sur chaque PR, en PHP 8.2 et 8.4.

Les tests JavaScript (Jest + jsdom) se trouvent dans `tests/JavaScript/`, et la CI les lance aussi :

```bash
npm install
npm test
```

### 6. Ouvrir la pull request

- Décrivez le contexte et la motivation dans le corps de la PR
- Si la PR adapte une règle métier spécifique à une ligue, précisez-le clairement
- Si la PR modifie le comportement de brûlage, ajoutez des tests pour les cas limites

---

## Publier une release

Réservé aux mainteneurs. La release est publiée **au merge de la PR**, par `.github/workflows/release.yml`.

1. Dans la PR, passer la version au même numéro aux trois endroits (sinon le workflow échoue) :
   - `tt-team-planner.php` (en-tête `Version:` + `define('TTP_VERSION', ...)`)
   - `readme.txt` (`Stable tag:` + entrée `== Changelog ==` + `== Upgrade Notice ==`)
2. Dans la description de la PR, écrire les notes de release destinées au club entre deux balises :
   ```markdown
   <!-- release: Compositions importées de la FFTT -->
   ## Correction
   …
   <!-- /release -->
   ```
   La release s'intitulera « v1.6.1 — Compositions importées de la FFTT ». Sans ce bloc, les notes reprennent l'entrée du changelog de `readme.txt` et le titre se limite au tag.
3. Merger. Le workflow crée le tag, la release (avec un lien de comparaison vers la précédente) et y attache le ZIP du plugin.

Une PR qui ne change pas la version ne publie rien. Pour rejouer une release qui a échoué : *Actions → Release → Run workflow* sur `main`.

Le ZIP est construit via `git archive`, en excluant les fichiers listés dans `.gitattributes` (tests, composer, phpcs, etc.) — prêt à être téléversé depuis **Extensions → Ajouter → Téléverser une extension** dans wp-admin. Une release créée à la main depuis l'interface GitHub reçoit aussi son ZIP, via `.github/workflows/release-zip.yml`.

Pas de publication sur WordPress.org (pas de dépôt SVN) : la release GitHub est la seule source de distribution.

## Questions

Ouvrez une issue avant de commencer un travail important — pour éviter les doublons et aligner les attentes.
