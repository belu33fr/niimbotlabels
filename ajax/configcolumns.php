<?php

use GlpiPlugin\Niimbotlabels\Config;
use GlpiPlugin\Niimbotlabels\ConfigColumn;

include('../../../inc/includes.php');

Session::checkLoginUser();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCSRF($_POST);
}

$configs_id = (int) ($_REQUEST['configs_id'] ?? 0);

$config = new Config();
if (!$configs_id || !$config->getFromDB($configs_id) || !$config->canUpdateItem()) {
    Html::displayRightError();
    exit;
}

if (isset($_POST['add'])) {
    global $DB;

    $max_rank = 0;
    $iterator = $DB->request([
        'SELECT' => ['MAX' => 'rank AS max_rank'],
        'FROM'   => ConfigColumn::getTable(),
        'WHERE'  => ['plugin_niimbotlabels_configs_id' => $configs_id],
    ]);
    foreach ($iterator as $row) {
        $max_rank = (int) $row['max_rank'];
    }

    $column_name = trim((string) ($_POST['column_name'] ?? ''));
    $search_option_id = (int) ($_POST['search_option_id'] ?? 0);

    if ($search_option_id > 0 && $column_name !== '') {
        $column = new ConfigColumn();
        $column->add([
            'plugin_niimbotlabels_configs_id' => $configs_id,
            'search_option_id'                => $search_option_id,
            'column_name'                      => $column_name,
            'rank'                             => $max_rank + 1,
        ]);
    }
} elseif (($_GET['action'] ?? '') === 'delete' && isset($_GET['id'])) {
    $column = new ConfigColumn();
    if (
        $column->getFromDB((int) $_GET['id'])
        && (int) $column->fields['plugin_niimbotlabels_configs_id'] === $configs_id
    ) {
        $column->delete(['id' => (int) $_GET['id']], 1);
    }
}

Html::redirect(Toolbox::getItemTypeFormURL(Config::class) . '?id=' . $configs_id);
