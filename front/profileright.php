<?php

use GlpiPlugin\Niimbotlabels\Config;
use GlpiPlugin\Niimbotlabels\ProfileRight;

include('../../../inc/includes.php');

Session::checkLoginUser();

if (!ProfileRight::canManage()) {
    Html::displayRightError();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Session::checkCSRF($_POST);
    $profiles_id = (int) ($_POST['profiles_id'] ?? 0);
    $value       = (int) ($_POST['value'] ?? 0);
    if ($profiles_id > 0) {
        ProfileRight::setRight($profiles_id, $value);
        Session::addMessageAfterRedirect(__('Droit mis à jour.', 'niimbotlabels'));
    }

    if (!empty($_POST['back_to_profile'])) {
        Html::redirect($CFG_GLPI['root_doc'] . '/front/profile.form.php?id=' . $profiles_id);
    }

    Html::redirect($_SERVER['PHP_SELF']);
}

Html::header(
    __('Droits par profil - Étiquettes Niimbot', 'niimbotlabels'),
    $_SERVER['PHP_SELF'],
    'tools',
    Config::class
);

global $DB;

echo "<div class='center'>";
echo "<table class='tab_cadre_fixehov'>";
echo "<tr><th>" . Profile::getTypeName(1) . "</th><th>" . __('Droit', 'niimbotlabels') . "</th><th></th></tr>";

$iterator = $DB->request(['FROM' => 'glpi_profiles', 'ORDER' => 'name ASC']);
foreach ($iterator as $prof) {
    $current = ProfileRight::getRight((int) $prof['id']);

    echo "<form method='post' action='" . $_SERVER['PHP_SELF'] . "'>";
    echo Html::hidden('profiles_id', ['value' => $prof['id']]);
    echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
    echo "<tr>";
    echo "<td>" . Html::entities_deep($prof['name']) . "</td>";
    echo "<td>";
    Dropdown::showFromArray('value', ProfileRight::getLevels($current), ['value' => $current]);
    echo "</td>";
    echo "<td>" . Html::submit(_sx('button', 'Save')) . "</td>";
    echo "</tr>";
    echo "</form>";
}

echo "</table>";
echo "</div>";

Html::footer();
