<?php

namespace GlpiPlugin\Niimbotlabels;

use CommonDropdown;

/**
 * Liste administrable des types/gabarits d'étiquette pouvant être
 * sélectionnés sur une ligne d'export (référence du rouleau Niimbot,
 * dimensions, etc.). Purement informatif : la mise en page réelle de
 * l'étiquette est gérée par le logiciel Niimbot lors de l'import du
 * fichier Excel.
 */
class LabelType extends CommonDropdown
{
    public static $rightname = 'dropdown';

    public static function getTypeName($nb = 0)
    {
        return _n("Type d'étiquette Niimbot", "Types d'étiquette Niimbot", $nb, 'niimbotlabels');
    }

    public static function getIcon()
    {
        return 'ti ti-tag';
    }

    public function getAdditionalFields()
    {
        return [
            [
                'name'  => 'label_width',
                'label' => __('Largeur (mm)', 'niimbotlabels'),
                'type'  => 'text',
            ],
            [
                'name'  => 'label_height',
                'label' => __('Hauteur (mm)', 'niimbotlabels'),
                'type'  => 'text',
            ],
            [
                'name'  => 'comment',
                'label' => __('Commentaires'),
                'type'  => 'textarea',
            ],
        ];
    }
}
