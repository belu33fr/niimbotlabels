<?php

/**
 * Fonctions d'installation / désinstallation du plugin, et intégration
 * dans la matrice des droits de profils GLPI.
 *
 * @package niimbotlabels
 */

use GlpiPlugin\Niimbotlabels\Config;
use GlpiPlugin\Niimbotlabels\ConfigColumn;
use GlpiPlugin\Niimbotlabels\LabelType;
use GlpiPlugin\Niimbotlabels\Printer;
use GlpiPlugin\Niimbotlabels\ProfileRight as PluginProfileRight;

/**
 * Installation du plugin : création des tables SQL et des droits de profil.
 */
function plugin_niimbotlabels_install()
{
    global $DB;

    $default_charset   = \DBConnection::getDefaultCharset();
    $default_collation = \DBConnection::getDefaultCollation();
    $default_key_sign  = \DBConnection::getDefaultPrimaryKeySignOption();

    // --- Table des imprimantes Niimbot -------------------------------
    $table = 'glpi_plugin_niimbotlabels_printers';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` int $default_key_sign NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `comment` text,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die($DB->error());
    }

    // --- Table des types d'étiquette ----------------------------------
    $table = 'glpi_plugin_niimbotlabels_labeltypes';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` int $default_key_sign NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `comment` text,
            `label_width` varchar(50) DEFAULT NULL,
            `label_height` varchar(50) DEFAULT NULL,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `is_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die($DB->error());
    }

    // --- Table principale : les lignes de configuration d'export ------
    $table = 'glpi_plugin_niimbotlabels_configs';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` int $default_key_sign NOT NULL AUTO_INCREMENT,
            `name` varchar(255) DEFAULT NULL,
            `entities_id` int $default_key_sign NOT NULL DEFAULT '0',
            `is_recursive` tinyint NOT NULL DEFAULT '0',
            `itemtype` varchar(100) NOT NULL DEFAULT '',
            `search_criteria` longtext,
            `plugin_niimbotlabels_printers_id` int $default_key_sign NOT NULL DEFAULT '0',
            `plugin_niimbotlabels_labeltypes_id` int $default_key_sign NOT NULL DEFAULT '0',
            `filename` varchar(255) DEFAULT NULL,
            `documents_id` int $default_key_sign NOT NULL DEFAULT '0',
            `comment` text,
            `is_active` tinyint NOT NULL DEFAULT '1',
            `last_generation` timestamp NULL DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `name` (`name`),
            KEY `entities_id` (`entities_id`),
            KEY `is_recursive` (`is_recursive`),
            KEY `itemtype` (`itemtype`),
            KEY `plugin_niimbotlabels_printers_id` (`plugin_niimbotlabels_printers_id`),
            KEY `plugin_niimbotlabels_labeltypes_id` (`plugin_niimbotlabels_labeltypes_id`),
            KEY `documents_id` (`documents_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die($DB->error());
    }

    // --- Table des colonnes du fichier Excel de chaque ligne -----------
    $table = 'glpi_plugin_niimbotlabels_configcolumns';
    if (!$DB->tableExists($table)) {
        $query = "CREATE TABLE `$table` (
            `id` int $default_key_sign NOT NULL AUTO_INCREMENT,
            `plugin_niimbotlabels_configs_id` int $default_key_sign NOT NULL DEFAULT '0',
            `rank` int NOT NULL DEFAULT '0',
            `search_option_id` int NOT NULL DEFAULT '0',
            `column_name` varchar(255) NOT NULL DEFAULT '',
            `template` varchar(255) DEFAULT NULL,
            `meta_itemtype` varchar(100) DEFAULT NULL,
            `date_creation` timestamp NULL DEFAULT NULL,
            `date_mod` timestamp NULL DEFAULT NULL,
            PRIMARY KEY (`id`),
            KEY `plugin_niimbotlabels_configs_id` (`plugin_niimbotlabels_configs_id`),
            KEY `rank` (`rank`)
        ) ENGINE=InnoDB DEFAULT CHARSET=$default_charset COLLATE=$default_collation ROW_FORMAT=DYNAMIC;";
        $DB->doQuery($query) or die($DB->error());
    }

    // --- Migration : ajout de la colonne "template" (formule libre par
    // colonne, ex: URL ou formule Excel construite à partir de la valeur
    // brute via le repère "#valeur#") pour les installations déjà en place
    // (table déjà existante, créée avant l'ajout de cette fonctionnalité).
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'template', false)) {
        $DB->doQuery("ALTER TABLE `$table` ADD COLUMN `template` varchar(255) DEFAULT NULL AFTER `column_name`") or die($DB->error());
    }

    // --- Migration : ajout de la colonne "meta_itemtype" (colonnes issues
    // d'un champ lié - ex: IP/MAC/alias réseau via NetworkPort - plutôt que
    // d'un champ direct du type d'objet de la ligne) pour les installations
    // déjà en place.
    if ($DB->tableExists($table) && !$DB->fieldExists($table, 'meta_itemtype', false)) {
        $DB->doQuery("ALTER TABLE `$table` ADD COLUMN `meta_itemtype` varchar(100) DEFAULT NULL AFTER `template`") or die($DB->error());
    }

    // --- Droits de profil ----------------------------------------------
    // Tous les droits pour le profil Super-Admin, aucun pour les autres
    // par défaut (ajustable ensuite via l'onglet "Étiquettes Niimbot" de
    // chaque profil).
    PluginProfileRight::addDefaultProfileRights();

    return true;
}

/**
 * Désinstallation du plugin : suppression des tables et des droits.
 */
function plugin_niimbotlabels_uninstall()
{
    global $DB;

    $tables = [
        'glpi_plugin_niimbotlabels_configcolumns',
        'glpi_plugin_niimbotlabels_configs',
        'glpi_plugin_niimbotlabels_labeltypes',
        'glpi_plugin_niimbotlabels_printers',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE `$table`") or die($DB->error());
        }
    }

    ProfileRight::deleteProfileRights([
        Config::$rightname,
    ]);

    return true;
}
