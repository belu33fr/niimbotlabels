<?php

use GlpiPlugin\Niimbotlabels\Printer;

include('../../../inc/includes.php');

Session::checkRight('dropdown', READ);

$item = new Printer();

if (isset($_POST['add'])) {
    $item->check(-1, CREATE, $_POST);
    $newid = $item->add($_POST);
    Html::back();
} elseif (isset($_POST['delete'])) {
    $item->check($_POST['id'], DELETE);
    $item->delete($_POST);
    $item->redirectToList();
} elseif (isset($_POST['restore'])) {
    $item->check($_POST['id'], PURGE);
    $item->restore($_POST);
    $item->redirectToList();
} elseif (isset($_POST['purge'])) {
    $item->check($_POST['id'], PURGE);
    $item->delete($_POST, 1);
    $item->redirectToList();
} elseif (isset($_POST['update'])) {
    $item->check($_POST['id'], UPDATE);
    $item->update($_POST);
    Html::back();
} else {
    Html::header(Printer::getTypeName(1), $_SERVER['PHP_SELF'], 'admin', 'dropdown', 'niimbotlabels_printer');
    $item->display(['id' => $_GET['id'] ?? 0]);
    Html::footer();
}
