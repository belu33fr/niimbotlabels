<?php

use GlpiPlugin\Niimbotlabels\Printer;

include('../../../inc/includes.php');

Session::checkRight('dropdown', READ);

Html::header(Printer::getTypeName(2), $_SERVER['PHP_SELF'], 'admin', 'dropdown', 'niimbotlabels_printer');

Search::show(Printer::class);

Html::footer();
