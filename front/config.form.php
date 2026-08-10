<?php

use GlpiPlugin\Niimbotlabels\Config;
use GlpiPlugin\Niimbotlabels\ConfigColumn;
use GlpiPlugin\Niimbotlabels\ExcelGenerator;

include('../../../inc/includes.php');

Session::checkLoginUser();

$config = new Config();

if (isset($_POST['add'])) {
    $config->check(-1, CREATE, $_POST);
    $newID = $config->add($_POST);
    Html::redirect(Config::getFormURLWithID($newID));
} elseif (isset($_POST['update']) || isset($_POST['update_criteria'])) {
    $config->check($_POST['id'], UPDATE);
    $config->update($_POST);
    Html::back();
} elseif (isset($_POST['add_column'])) {
    $config->check((int) $_POST['id'], UPDATE);

    $decoded           = ConfigColumn::decodeFieldKey($_POST['search_option_id'] ?? '');
    $search_option_id = $decoded['search_option_id'];
    $meta_itemtype     = $decoded['meta_itemtype'];
    $column_name       = trim((string) ($_POST['column_name'] ?? ''));
    $template           = trim((string) ($_POST['template'] ?? ''));

    if ($search_option_id > 0 && $column_name !== '') {
        global $DB;

        $duplicate = $DB->request([
            'FROM'  => ConfigColumn::getTable(),
            'WHERE' => [
                'plugin_niimbotlabels_configs_id' => (int) $_POST['id'],
                'column_name'                      => $column_name,
            ],
        ])->count() > 0;

        if ($duplicate) {
            Session::addMessageAfterRedirect(
                sprintf(__('Une colonne nommée "%s" existe déjà pour cette ligne.', 'niimbotlabels'), $column_name),
                true,
                ERROR
            );
        } else {
            $max_rank = 0;
            $iterator = $DB->request([
                'SELECT' => ['MAX' => 'rank AS max_rank'],
                'FROM'   => ConfigColumn::getTable(),
                'WHERE'  => ['plugin_niimbotlabels_configs_id' => (int) $_POST['id']],
            ]);
            foreach ($iterator as $row) {
                $max_rank = (int) $row['max_rank'];
            }

            $column = new ConfigColumn();
            $column->add([
                'plugin_niimbotlabels_configs_id' => (int) $_POST['id'],
                'search_option_id'                => $search_option_id,
                'meta_itemtype'                    => $meta_itemtype,
                'column_name'                      => $column_name,
                'template'                         => $template,
                'rank'                             => $max_rank + 1,
            ]);
        }
    }

    Html::back();
} elseif (isset($_POST['edit_columns']) && isset($_POST['columns']) && is_array($_POST['columns'])) {
    $config->check((int) $_POST['id'], UPDATE);

    // Vérifie qu'aucun nom de colonne n'est utilisé deux fois parmi les
    // valeurs soumises avant d'enregistrer quoi que ce soit.
    $names_seen    = [];
    $has_duplicate = false;
    foreach ($_POST['columns'] as $data) {
        $name = trim((string) ($data['column_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        if (isset($names_seen[$name])) {
            $has_duplicate = true;
            break;
        }
        $names_seen[$name] = true;
    }

    if ($has_duplicate) {
        Session::addMessageAfterRedirect(
            __('Deux colonnes ne peuvent pas porter le même nom.', 'niimbotlabels'),
            true,
            ERROR
        );
    } else {
        foreach ($_POST['columns'] as $column_id => $data) {
            $column_id        = (int) $column_id;
            $decoded           = ConfigColumn::decodeFieldKey($data['search_option_id'] ?? '');
            $search_option_id = $decoded['search_option_id'];
            $meta_itemtype     = $decoded['meta_itemtype'];
            $column_name       = trim((string) ($data['column_name'] ?? ''));
            $template           = trim((string) ($data['template'] ?? ''));

            if ($column_id <= 0 || $search_option_id <= 0 || $column_name === '') {
                continue;
            }

            $column = new ConfigColumn();
            if (
                $column->getFromDB($column_id)
                && (int) $column->fields['plugin_niimbotlabels_configs_id'] === (int) $_POST['id']
            ) {
                $column->update([
                    'id'                => $column_id,
                    'search_option_id'  => $search_option_id,
                    'meta_itemtype'      => $meta_itemtype,
                    'column_name'        => $column_name,
                    'template'           => $template,
                ]);
            }
        }
    }

    Html::back();
} elseif (($_GET['action'] ?? '') === 'delete_column' && isset($_GET['id'], $_GET['column_id'])) {
    $config->check((int) $_GET['id'], UPDATE);

    $column = new ConfigColumn();
    if (
        $column->getFromDB((int) $_GET['column_id'])
        && (int) $column->fields['plugin_niimbotlabels_configs_id'] === (int) $_GET['id']
    ) {
        $column->delete(['id' => (int) $_GET['column_id']], 1);
    }

    Html::redirect(Config::getFormURLWithID((int) $_GET['id']));
} elseif (isset($_POST['purge'])) {
    $config->check($_POST['id'], PURGE);
    $config->delete($_POST, 1);
    Html::redirect(Config::getSearchURL());
} elseif (($_GET['action'] ?? '') === 'purge' && isset($_GET['id'])) {
    $config->check((int) $_GET['id'], PURGE);
    $config->delete(['id' => (int) $_GET['id']], 1);
    Html::redirect(Config::getSearchURL());
} elseif (($_GET['action'] ?? '') === 'download' && isset($_GET['id'])) {
    // Génère le fichier Excel à la demande et l'envoie directement au
    // navigateur : pas de stockage intermédiaire dans le module Document
    // de GLPI (le fichier est reconstruit à chaque téléchargement).
    $config->check((int) $_GET['id'], READ);
    ExcelGenerator::download($config);
} else {
    $id = (int) ($_GET['id'] ?? 0);
    Html::header(Config::getTypeName(1), $_SERVER['PHP_SELF'], 'tools', Config::class);
    if ($id > 0) {
        $config->check($id, READ);
    } else {
        $config->check(-1, CREATE);
    }
    $config->display(['id' => $id]);
    Html::footer();
}
