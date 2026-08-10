<?php

/**
 * Plugin Niimbot Labels pour GLPI
 *
 * Génère des fichiers Excel destinés au logiciel propriétaire Niimbot,
 * à partir de requêtes GLPI paramétrables (entité, localisation, type de
 * matériel, etc.).
 *
 * @package   niimbotlabels
 * @author    L. Berthaud, Claude (Anthropic)
 * @license   GPLv2+
 */

use GlpiPlugin\Niimbotlabels\Config;
use GlpiPlugin\Niimbotlabels\ConfigColumn;
use GlpiPlugin\Niimbotlabels\LabelType;
use GlpiPlugin\Niimbotlabels\Printer;
use GlpiPlugin\Niimbotlabels\ProfileRight;

define('PLUGIN_NIIMBOTLABELS_VERSION', '1.0.1');

// Compatibilité annoncée : GLPI 10.0 -> 11.x
define('PLUGIN_NIIMBOTLABELS_MIN_GLPI_VERSION', '10.0.0');
define('PLUGIN_NIIMBOTLABELS_MAX_GLPI_VERSION', '11.9.99');

// Autoload des dépendances tierces (PhpSpreadsheet), si "composer install"
// a bien été exécuté dans le dossier du plugin.
if (is_readable(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/hook.php';

/**
 * Déclaration du plugin auprès du coeur GLPI : menus, droits, classes.
 */
function plugin_init_niimbotlabels()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['niimbotlabels'] = true;

    // Icône de la tuile du plugin (page de gestion des plugins) : sans
    // cela, GLPI affiche par défaut les initiales du plugin ("NL"). Une
    // imprimante illustre mieux le rôle du plugin (export pour étiqueteuse
    // Niimbot).
    $PLUGIN_HOOKS['icon']['niimbotlabels'] = 'ti ti-printer';

    // Enregistrement des classes du plugin auprès du coeur GLPI.
    Plugin::registerClass(Printer::class);
    Plugin::registerClass(LabelType::class);
    Plugin::registerClass(Config::class, [
        'addtabon' => [],
    ]);
    Plugin::registerClass(ConfigColumn::class);

    // Ajoute un véritable onglet "Étiquettes Niimbot" sur la fiche Profil,
    // pour gérer le droit du plugin directement là où les autres droits
    // sont gérés.
    Plugin::registerClass(ProfileRight::class, [
        'addtabon' => 'Profile',
    ]);

    if (Session::getLoginUserID()) {
        // Resynchronise le droit du plugin dans la session active à chaque
        // requête (sans quoi un changement de droit ne serait pris en
        // compte qu'après déconnexion/reconnexion).
        ProfileRight::initProfile();

        // Entrée de menu (visible uniquement si l'utilisateur a le droit
        // de lecture).
        if (Session::haveRight(Config::$rightname, READ)) {
            $PLUGIN_HOOKS['menu_toadd']['niimbotlabels'] = [
                'tools' => Config::class,
            ];
        }
    }

    // Sous "public/" (et non "js/") : sur cette instance, seul ce dossier
    // est servi directement par le serveur web pour les assets statiques
    // des plugins, le reste passe par le routeur PHP de GLPI.
    $PLUGIN_HOOKS['add_javascript']['niimbotlabels'] = [
        'public/config.js',
    ];
}

/**
 * Informations affichées dans la liste des plugins.
 */
function plugin_version_niimbotlabels()
{
    return [
        'name'           => 'Niimbot Labels Export',
        'version'        => PLUGIN_NIIMBOTLABELS_VERSION,
        'author'         => 'L. Berthaud, Claude (Anthropic)',
        'license'        => 'GPLv2+',
        'homepage'       => '',
        'requirements'   => [
            'glpi' => [
                'min' => PLUGIN_NIIMBOTLABELS_MIN_GLPI_VERSION,
                'max' => PLUGIN_NIIMBOTLABELS_MAX_GLPI_VERSION,
            ],
            'php' => [
                'min' => '8.1',
            ],
        ],
    ];
}

/**
 * Vérifie que les prérequis techniques sont bien remplis (dépendances
 * Composer notamment) avant d'autoriser l'activation du plugin.
 */
function plugin_niimbotlabels_check_prerequisites()
{
    if (!is_readable(__DIR__ . '/vendor/autoload.php')) {
        echo "Le plugin Niimbot Labels nécessite l'exécution de la commande "
            . "<code>composer install --no-dev</code> dans son répertoire "
            . "(plugins/niimbotlabels/) avant activation.";
        return false;
    }

    return true;
}

/**
 * Vérifie la configuration du plugin. Rien de bloquant pour l'instant.
 */
function plugin_niimbotlabels_check_config($verbose = false)
{
    return true;
}
