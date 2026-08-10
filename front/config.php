<?php

use GlpiPlugin\Niimbotlabels\Config;

include('../../../inc/includes.php');

Session::checkRight(Config::$rightname, READ);

Html::header(Config::getTypeName(2), $_SERVER['PHP_SELF'], 'tools', Config::class);

Search::show(Config::class);

Html::footer();
