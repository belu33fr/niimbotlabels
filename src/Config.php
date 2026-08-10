<?php

namespace GlpiPlugin\Niimbotlabels;

use CommonDBTM;
use Dropdown;
use Entity;
use Html;
use Plugin;
use Search;
use Session;
use Toolbox;

/**
 * Ligne principale de configuration d'export : associe une recherche GLPI
 * paramétrable (entité, sous-entités, critères) à une imprimante, un type
 * d'étiquette, et une liste de colonnes destinées à un fichier Excel
 * consommé par le logiciel Niimbot.
 */
class Config extends CommonDBTM
{
    public static $rightname = 'plugin_niimbotlabels_config';

    // Historique des modifications activé.
    public $dohistory = true;

    public static function getTypeName($nb = 0)
    {
        return _n('Export étiquettes Niimbot', 'Exports étiquettes Niimbot', $nb, 'niimbotlabels');
    }

    public static function getIcon()
    {
        return 'ti ti-tags';
    }

    public static function getMenuName()
    {
        return __('Étiquettes Niimbot', 'niimbotlabels');
    }

    /**
     * Types d'objets proposés à l'export : tous les assets standards ainsi
     * que les assets personnalisés déclarés dans GLPI (Assets > Définitions
     * d'assets), puisqu'ils sont automatiquement ajoutés par le coeur à
     * $CFG_GLPI['asset_types'].
     */
    public static function getAllowedItemtypes(): array
    {
        global $CFG_GLPI;

        $types = $CFG_GLPI['asset_types'] ?? [];
        $list  = [];

        foreach ($types as $type) {
            if (is_string($type) && class_exists($type) && is_a($type, CommonDBTM::class, true)) {
                $list[$type] = $type::getTypeName(2);
            }
        }

        asort($list);

        return $list;
    }

    public function post_getFromDB()
    {
        parent::post_getFromDB();

        $decoded = [];
        if (!empty($this->fields['search_criteria']) && is_string($this->fields['search_criteria'])) {
            $tmp = json_decode($this->fields['search_criteria'], true);
            if (is_array($tmp)) {
                $decoded = $tmp;
            }
        }
        // Conservé décodé en mémoire pour un usage pratique côté formulaire
        // et générateur Excel (n'affecte pas ce qui est stocké en base :
        // prepareInputForAdd/Update ré-encode systématiquement en JSON).
        $this->fields['search_criteria'] = $decoded;
    }

    /**
     * Critères de recherche décodés (tableau), au format consommé
     * directement par la classe Search de GLPI.
     */
    public function getSearchCriteria(): array
    {
        if (!isset($this->fields['search_criteria'])) {
            return [];
        }
        if (is_array($this->fields['search_criteria'])) {
            return $this->fields['search_criteria'];
        }
        $tmp = json_decode((string) $this->fields['search_criteria'], true);
        return is_array($tmp) ? $tmp : [];
    }

    /**
     * Libellés (français) des opérateurs de recherche proposés par
     * l'éditeur de critères du plugin. Partagé entre l'affichage du
     * formulaire et la feuille "Paramétrage extraction" du fichier Excel
     * généré.
     */
    public static function getSearchtypeLabels(): array
    {
        return [
            'contains'  => __('contient', 'niimbotlabels'),
            'equals'    => __('est', 'niimbotlabels'),
            'notequals' => __("n'est pas", 'niimbotlabels'),
            'under'     => __('sous', 'niimbotlabels'),
            'notunder'  => __('pas sous', 'niimbotlabels'),
            'morethan'  => __('supérieur à', 'niimbotlabels'),
            'lessthan'  => __('inférieur à', 'niimbotlabels'),
        ];
    }

    public function prepareInputForAdd($input)
    {
        return $this->prepareCommonInput($input);
    }

    public function prepareInputForUpdate($input)
    {
        return $this->prepareCommonInput($input);
    }

    private function prepareCommonInput($input)
    {
        // Reconstruction des critères de recherche postés par le petit
        // éditeur dédié (voir showSearchCriteriaEditor()).
        if (isset($input['crit_field']) && is_array($input['crit_field'])) {
            $criteria = [];
            foreach ($input['crit_field'] as $i => $field) {
                if ($field === '' || $field === null) {
                    continue;
                }
                $criteria[] = [
                    'field'      => $field,
                    'searchtype' => $input['crit_searchtype'][$i] ?? 'contains',
                    'value'      => $input['crit_value'][$i] ?? '',
                    'link'       => $input['crit_link'][$i] ?? 'AND',
                ];
            }
            $input['search_criteria'] = json_encode($criteria);
        }

        if (!isset($input['entities_id']) && !isset($input['id'])) {
            $input['entities_id'] = $_SESSION['glpiactive_entity'] ?? 0;
        }

        return $input;
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(ConfigColumn::class, $tabs, $options);
        $this->addStandardTab('Notepad', $tabs, $options);
        $this->addStandardTab('Log', $tabs, $options);
        return $tabs;
    }

    public function showForm($ID, array $options = [])
    {
        $this->initForm($ID, $options);
        $this->showFormHeader($options);

        // Entité + sous-entités sont déjà gérées automatiquement par
        // showFormHeader() ci-dessus (GLPI n'affiche le choix d'entité que
        // s'il y en a plusieurs d'accessibles, et la case sous-entités que
        // si l'entité choisie en a) : inutile de les dupliquer ici.

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Nom', 'niimbotlabels') . "</td><td>";
        echo Html::input('name', ['value' => $this->fields['name'] ?? '']);
        echo "</td>";
        echo "<td>" . __('Actif', 'niimbotlabels') . "</td><td>";
        Html::showCheckbox([
            'name'    => 'is_active',
            'checked' => !isset($this->fields['is_active']) || (bool) $this->fields['is_active'],
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . Printer::getTypeName(1) . "</td><td>";
        Printer::dropdown([
            'name'  => 'plugin_niimbotlabels_printers_id',
            'value' => $this->fields['plugin_niimbotlabels_printers_id'] ?? 0,
        ]);
        echo "</td>";
        echo "<td>" . LabelType::getTypeName(1) . "</td><td>";
        LabelType::dropdown([
            'name'  => 'plugin_niimbotlabels_labeltypes_id',
            'value' => $this->fields['plugin_niimbotlabels_labeltypes_id'] ?? 0,
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __("Type d'objet à exporter", 'niimbotlabels') . "</td><td>";
        Dropdown::showFromArray('itemtype', self::getAllowedItemtypes(), [
            'value'               => $this->fields['itemtype'] ?? '',
            'display_emptychoice' => true,
        ]);
        echo "</td>";
        echo "<td>" . __('Nom du fichier', 'niimbotlabels') . "</td><td>";
        echo Html::input('filename', [
            'value'       => $this->fields['filename'] ?? '',
            'placeholder' => 'export.xlsx',
        ]);
        echo "</td></tr>";

        echo "<tr class='tab_bg_1'>";
        echo "<td>" . __('Commentaires', 'niimbotlabels') . "</td>";
        echo "<td colspan='3'>";
        echo "<textarea name='comment' class='form-control' rows='2'>" . Html::entities_deep($this->fields['comment'] ?? '') . "</textarea>";
        echo "</td></tr>";

        $this->showFormButtons($options);

        if ($ID > 0) {
            // "Sauvegarder" et "Supprimer définitivement" sont déjà fournis
            // ci-dessus par showFormButtons() (natif GLPI) : inutile de les
            // dupliquer. Seul "Télécharger" est spécifique au plugin.
            echo "<div class='center' style='margin:10px 0;'>";
            echo "<a class='vsubmit' href='" . Toolbox::getItemTypeFormURL(self::class) . "?id=$ID&action=download'>"
                . __('Télécharger', 'niimbotlabels') . "</a>";
            echo "</div>";

            if (!empty($this->fields['itemtype']) && class_exists($this->fields['itemtype'])) {
                $this->showSearchCriteriaEditor();
            }
        } else {
            echo "<p class='center'>" . __("Enregistrez la ligne avec un type d'objet choisi afin de configurer les critères de recherche et les colonnes du fichier.", 'niimbotlabels') . "</p>";
        }

        return true;
    }

    /**
     * Petit éditeur de critères de recherche, réutilisant les mêmes champs
     * et sémantique que le moteur de recherche natif de GLPI
     * (Search::getOptions()) pour l'itemtype choisi.
     */
    protected function showSearchCriteriaEditor()
    {
        $itemtype = $this->fields['itemtype'];

        // Champs regroupés par table d'origine (Ordinateur, Utilisateur,
        // Fabricant...), pour éviter d'afficher de nombreux champs "Nom"
        // sans distinction. Les champs "liés" (recherche méta, ex: IP via
        // NetworkPort) ne sont pour l'instant proposables qu'en colonne du
        // fichier Excel, pas encore comme critère de filtre.
        $fields_for_dropdown = ConfigColumn::getFieldsForDropdown($itemtype, false);

        $searchtypes = self::getSearchtypeLabels();

        $criteria = $this->getSearchCriteria();
        if (empty($criteria)) {
            $criteria = [['field' => '', 'searchtype' => 'contains', 'value' => '', 'link' => 'AND']];
        }

        echo "<form name='niimbotlabels_criteria_form' method='post' action='" . Toolbox::getItemTypeFormURL(self::class) . "'>";
        echo Html::hidden('id', ['value' => $this->fields['id']]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='5'>" . __('Critères de recherche', 'niimbotlabels')
            . " <small>(" . $itemtype::getTypeName(2) . ")</small></th></tr>";
        echo "<tr><th>" . __('Lien', 'niimbotlabels') . "</th><th>" . __('Champ', 'niimbotlabels') . "</th><th>" . __('Opérateur', 'niimbotlabels') . "</th><th>" . __('Valeur', 'niimbotlabels') . "</th><th></th></tr>";

        echo "<tbody id='niimbotlabels_criteria_rows'>";
        foreach (array_values($criteria) as $i => $row) {
            $this->showCriteriaRow($i, $row, $fields_for_dropdown, $searchtypes);
        }
        echo "</tbody>";

        echo "<tr><td colspan='5' class='center'>";
        echo "<button type='button' class='btn btn-outline-secondary niimbotlabels-add-criteria'>+ " . __('Ajouter un critère', 'niimbotlabels') . "</button>";
        echo "</td></tr>";

        echo "<tr><td colspan='5' class='center'>";
        echo Html::submit(__('Enregistrer les critères', 'niimbotlabels'), ['name' => 'update_criteria']);
        echo "</td></tr>";
        echo "</table>";
        Html::closeForm();

        // Modèle de ligne caché, dupliqué en JS pour l'ajout dynamique.
        echo "<template id='niimbotlabels-criteria-template'>";
        $this->showCriteriaRow('__INDEX__', ['field' => '', 'searchtype' => 'contains', 'value' => '', 'link' => 'AND'], $fields_for_dropdown, $searchtypes);
        echo "</template>";
    }

    protected function showCriteriaRow($index, array $row, array $fields, array $searchtypes)
    {
        echo "<tr class='niimbotlabels-criteria-row'>";
        echo "<td>";
        Dropdown::showFromArray("crit_link[$index]", [
            'AND'     => __('ET', 'niimbotlabels'),
            'OR'      => __('OU', 'niimbotlabels'),
            'AND NOT' => __('ET NON', 'niimbotlabels'),
        ], ['value' => $row['link'] ?? 'AND']);
        echo "</td>";
        echo "<td>";
        Dropdown::showFromArray("crit_field[$index]", $fields, [
            'value'               => $row['field'] ?? '',
            'display_emptychoice' => true,
            // Un identifiant unique par ligne : plusieurs critères sur la
            // même page sinon empêchent l'activation de la recherche dans
            // la liste déroulante.
            'rand'                 => mt_rand(),
        ]);
        echo "</td>";
        echo "<td>";
        Dropdown::showFromArray("crit_searchtype[$index]", $searchtypes, [
            'value' => $row['searchtype'] ?? 'contains',
            'rand'  => mt_rand(),
        ]);
        echo "</td>";
        echo "<td>";
        echo Html::input("crit_value[$index]", ['value' => $row['value'] ?? '']);
        echo "</td>";
        echo "<td>";
        echo "<button type='button' class='btn btn-sm btn-outline-danger niimbotlabels-remove-criteria'>&times;</button>";
        echo "</td>";
        echo "</tr>";
    }

    public function rawSearchOptions()
    {
        $tab = [];

        $tab[] = [
            'id'   => 'common',
            'name' => self::getTypeName(2),
        ];

        $tab[] = [
            'id'            => 1,
            'table'         => $this->getTable(),
            'field'         => 'name',
            'name'          => __('Nom', 'niimbotlabels'),
            'datatype'      => 'itemlink',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 2,
            'table'    => $this->getTable(),
            'field'    => 'id',
            'name'     => __('ID'),
            'datatype' => 'number',
            'massiveaction' => false,
        ];

        $tab[] = [
            'id'       => 3,
            'table'    => 'glpi_entities',
            'field'    => 'completename',
            'name'     => Entity::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'       => 4,
            'table'    => $this->getTable(),
            'field'    => 'is_recursive',
            'name'     => __('Sous-entités', 'niimbotlabels'),
            'datatype' => 'bool',
        ];

        $tab[] = [
            'id'       => 5,
            'table'    => 'glpi_plugin_niimbotlabels_printers',
            'field'    => 'name',
            'name'     => Printer::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'       => 6,
            'table'    => 'glpi_plugin_niimbotlabels_labeltypes',
            'field'    => 'name',
            'name'     => LabelType::getTypeName(1),
            'datatype' => 'dropdown',
        ];

        $tab[] = [
            'id'       => 7,
            'table'    => $this->getTable(),
            'field'    => 'itemtype',
            'name'     => __("Type d'objet", 'niimbotlabels'),
            'datatype' => 'itemtypename',
        ];

        $tab[] = [
            'id'       => 8,
            'table'    => $this->getTable(),
            'field'    => 'filename',
            'name'     => __('Nom du fichier', 'niimbotlabels'),
        ];

        $tab[] = [
            'id'            => 900,
            'table'         => $this->getTable(),
            'field'         => 'id',
            'name'          => __('Actions', 'niimbotlabels'),
            'datatype'      => 'specific',
            'massiveaction' => false,
            'nosort'        => true,
            'searchtype'    => ['equals'],
        ];

        return $tab;
    }

    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {
        if (!is_array($values)) {
            $values = [$field => $values];
        }

        $searchopt_id = $options['searchopt']['id'] ?? $options['id'] ?? null;

        if ($field === 'id' && (int) $searchopt_id === 900 && isset($values['id'])) {
            $id  = (int) $values['id'];
            $url = Toolbox::getItemTypeFormURL(self::class);

            $out = "<a href='$url?id=$id' title='" . __('Ouvrir', 'niimbotlabels') . "'><i class='ti ti-pencil'></i></a> ";
            $out .= "<a href='$url?id=$id&action=download' title='" . __('Télécharger', 'niimbotlabels') . "'><i class='ti ti-download'></i></a> ";
            $out .= "<a href='$url?id=$id&action=purge' onclick=\"return confirm('" . __('Confirmer la suppression ?', 'niimbotlabels') . "');\" title='" . __('Supprimer', 'niimbotlabels') . "'><i class='ti ti-trash'></i></a>";

            return $out;
        }

        return parent::getSpecificValueToDisplay($field, $values, $options);
    }

}
