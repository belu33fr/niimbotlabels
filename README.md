# niimbotlabels — Export Excel pour étiquettes Niimbot (plugin GLPI 10/11)

Ce plugin génère à la demande des fichiers Excel (`.xlsx`) destinés à être
importés dans le logiciel propriétaire Niimbot, à partir de requêtes GLPI
paramétrables (entité + sous-entités, localisation, type de matériel, etc.).
Le fichier est construit et téléchargé directement au clic sur "Télécharger" :
aucun stockage intermédiaire dans le module Documents de GLPI.

## 1. Installation

1. Copiez (ou décompressez le zip fourni) le contenu du plugin dans
   `plugins/niimbotlabels/` de votre installation GLPI (10.0 ou 11.x), de
   sorte à obtenir `plugins/niimbotlabels/setup.php`.
2. En étant dans le répertoire `plugins/`, exécutez ce qui suit (pensez à
   remplacer `www-data` par l'utilisateur/groupe sous lequel tourne votre
   serveur web, si différent) :

   ```bash
   sudo chown -R www-data:www-data niimbotlabels/
   sudo find niimbotlabels/ -type f -exec chmod 644 {} \;
   sudo find niimbotlabels/ -type d -exec chmod 755 {} \;
   sudo -u www-data composer install --no-dev -d niimbotlabels/
   sudo systemctl restart apache2
   ```

   L'étape `composer install` (dépendance Excel PhpSpreadsheet) est
   indispensable : sans elle, le plugin refuse de s'activer (message
   explicite affiché dans la liste des plugins). Si vous mettez à jour une
   installation existante, videz aussi le cache GLPI avant ces commandes :
   `sudo rm -rf /var/glpi/files/_cache/<version-de-votre-cache>/*` (le nom
   exact du sous-dossier dépend de votre instance, visible dans
   `files/_cache/`).
3. Dans GLPI : **Configuration > Plugins**, cliquez sur *Installer* puis
   *Activer* pour "Niimbot Labels Export" (ou, en ligne de commande :
   `php bin/console glpi:plugin:install --force niimbotlabels` puis
   `glpi:plugin:activate niimbotlabels`).
4. Un droit `plugin_niimbotlabels_config` est créé automatiquement. Le profil
   Super-Admin le reçoit par défaut ; attribuez-le aux autres profils
   concernés directement depuis l'onglet "Étiquettes Niimbot" de la fiche
   Profil.

## 2. Structure fonctionnelle

- **Menu Outils > Exports étiquettes Niimbot** : liste des "lignes" de
  configuration. Chaque ligne définit un export Excel complet.
- Chaque ligne possède : un nom, une entité (+ case "sous-entités" pour
  qu'elle s'applique à toute l'arborescence — cocher avec l'entité racine
  pour une ligne valable sur **toutes** les entités), une imprimante Niimbot,
  un type d'étiquette, un type d'objet GLPI à exporter (tous les assets
  standards ainsi que les assets personnalisés déclarés dans GLPI), un nom
  de fichier, des critères de recherche, et une liste de colonnes.
- Le bouton "Télécharger" génère le fichier à la volée et le transmet
  directement au navigateur (aucune trace conservée côté serveur).
- **Configuration > Listes déroulantes** : les imprimantes Niimbot et les
  types d'étiquette sont deux listes administrables indépendantes (ajout,
  modification, désactivation), au même titre que les listes déroulantes
  standards de GLPI.

## 3. Configuration d'une ligne d'export

### Critères de recherche

Les critères sont stockés et exécutés via le moteur de recherche natif de
GLPI (mêmes structures et sémantique qu'une recherche standard : champ,
opérateur, valeur, lien ET/OU/ET NON), avec une interface de sélection
simplifiée intégrée à la fiche. Cela garantit un comportement cohérent avec
le reste de GLPI (gestion native des sous-entités, compatibilité avec les
assets personnalisés, etc.). La liste des champs proposés reprend
exactement le même regroupement que le moteur de recherche natif de GLPI
(par onglet de la fiche de l'objet).

### Colonnes du fichier Excel

Dans l'onglet dédié de la fiche, ajoutez une colonne = un champ GLPI source
(la liste réutilise les mêmes champs que le moteur de recherche, y compris
les champs "liés" comme l'adresse IP ou les alias réseau via NetworkPort)
associé à un **nom de colonne paramétrable**. Ce nom devient l'en-tête de la
colonne dans le fichier Excel généré : il permet de faire correspondre
exactement les noms attendus par le logiciel Niimbot lors de l'import.

Une **formule optionnelle** peut être associée à chaque colonne pour
transformer la valeur brute du champ, repérée par le jeton `#valeur#` (ex :
`https://exemple.fr/?id=#valeur#` pour une URL, ou
`=CONCATENER("REF-";#valeur#)` pour une formule Excel — toute chaîne
commençant par `=` est interprétée comme une formule par Excel à
l'ouverture du fichier).

En bas de ce même onglet, un **aperçu des données** (25 premières lignes)
affiche exactement ce que contiendra la feuille "Datas" du fichier généré,
à partir des critères et colonnes actuellement enregistrés — pratique pour
mettre au point la requête sans avoir à télécharger le fichier à chaque
essai.

### Génération du fichier

Le bouton "Télécharger" exécute la recherche sur le périmètre d'entités
configuré (indépendamment de l'entité active de l'utilisateur qui déclenche
l'action) et produit un classeur à deux feuilles :

- **Datas** : une ligne par résultat, une colonne par champ configuré.
- **Paramétrage extraction** : rappel en clair du périmètre (entité,
  sous-entités) et des critères de recherche utilisés, pour traçabilité.

## 4. Niimbot : portée de cette version

- **V1 (cette version)** : export Excel uniquement. Le fichier généré est à
  importer manuellement dans le logiciel Niimbot.
- **V2 (à venir)** : intégration directe avec le logiciel/API Niimbot si
  celui-ci l'expose, pour déclencher l'impression sans étape d'import
  manuel.

## 5. Traductions

Les chaînes de l'interface sont déclarées via le mécanisme `gettext`
standard de GLPI (domaine `niimbotlabels`). Deux traductions sont fournies :

- `locales/fr_FR.mo` : identique au texte source (français), fournie
  explicitement car GLPI utilise `en_GB.mo` comme langue de repli pour
  **toute** langue sans traduction dédiée, y compris le français lui-même
  en son absence — sans ce fichier, un utilisateur en français se
  retrouvait avec l'anglais.
- `locales/en_GB.mo` : traduction anglaise, utilisée aussi comme repli pour
  toute autre langue sans traduction dédiée.

Pour ajouter/mettre à jour une langue : éditez `locales/<code>.po` (ex :
`de_DE.po`) avec un outil comme [Poedit](https://poedit.net), puis exportez
le `.mo` correspondant dans le même dossier.

## 6. Structure des fichiers

```
niimbotlabels/
├── setup.php                  Déclaration du plugin, menu, hooks, icône
├── hook.php                   Install / désinstall (tables SQL, droits)
├── composer.json              Dépendance PhpSpreadsheet
├── logo.png                   Icône affichée sur la tuile (page Plugins)
├── locales/
│   └── en_GB.mo / en_GB.po     Traduction anglaise
├── src/
│   ├── Printer.php             Liste administrable des imprimantes
│   ├── LabelType.php           Liste administrable des types d'étiquette
│   ├── Config.php              Ligne de configuration (coeur du plugin)
│   ├── ConfigColumn.php        Colonnes du fichier Excel + aperçu
│   ├── ExcelGenerator.php      Génération Excel à la demande
│   └── ProfileRight.php        Droit du plugin par profil
├── front/
│   ├── printer.php / printer.form.php
│   ├── labeltype.php / labeltype.form.php
│   ├── profileright.php
│   └── config.php / config.form.php
├── ajax/
│   └── configcolumns.php       Ajout / suppression de colonnes
└── public/
    └── config.js               Ajout / suppression dynamique de critères
```

## 7. Auteurs

L. Berthaud, Claude (Anthropic)
