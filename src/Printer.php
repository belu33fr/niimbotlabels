<?php

namespace GlpiPlugin\Niimbotlabels;

use CommonDropdown;

/**
 * Liste administrable des imprimantes Niimbot pouvant être sélectionnées
 * sur une ligne d'export.
 */
class Printer extends CommonDropdown
{
    // Droit partagé avec les listes déroulantes standards de GLPI :
    // toute personne autorisée à gérer les "dropdowns" peut gérer la
    // liste des imprimantes.
    public static $rightname = 'dropdown';

    public static function getTypeName($nb = 0)
    {
        return _n('Imprimante Niimbot', 'Imprimantes Niimbot', $nb, 'niimbotlabels');
    }

    public static function getIcon()
    {
        return 'ti ti-printer';
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'comment',
                'label' => __('Commentaires', 'niimbotlabels'),
                'type'  => 'textarea',
            ],
        ];
    }
}
