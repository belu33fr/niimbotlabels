<?php

use GlpiPlugin\Niimbotlabels\LabelType;

include('../../../inc/includes.php');

Session::checkRight('dropdown', READ);

Html::header(LabelType::getTypeName(2), $_SERVER['PHP_SELF'], 'admin', 'dropdown', 'niimbotlabels_labeltype');

Search::show(LabelType::class);

Html::footer();
