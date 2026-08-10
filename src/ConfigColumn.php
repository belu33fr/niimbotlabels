<?php

namespace GlpiPlugin\Niimbotlabels;

use CommonDBTM;
use CommonGLPI;
use Dropdown;
use Html;
use Search;
use Session;
use Toolbox;

/**
 * Une colonne du fichier Excel généré pour une ligne de configuration
 * (Config) : associe un champ source GLPI (identifiant d'option de
 * recherche, comme dans le moteur Search natif) à un nom de colonne
 * paramétrable, utilisé comme en-tête dans le fichier Excel afin de
 * correspondre à ce qu'attend le logiciel Niimbot.
 */
class ConfigColumn extends CommonDBTM
{
    public static $rightname = 'plugin_niimbotlabels_config';

    public static function getTypeName($nb = 0)
    {
        return _n('Colonne du fichier Excel', 'Colonnes du fichier Excel', $nb, 'niimbotlabels');
    }

    public static function getIcon()
    {
        return 'ti ti-table';
    }

    public static function canView(): bool
    {
        return Session::haveRight(Config::$rightname, READ);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight(Config::$rightname, UPDATE);
    }

    public function canViewItem(): bool
    {
        $config = new Config();
        return $config->getFromDB($this->fields['plugin_niimbotlabels_configs_id']) && $config->canViewItem();
    }

    public function canCreateItem(): bool
    {
        $config = new Config();
        return $config->getFromDB($this->fields['plugin_niimbotlabels_configs_id']) && $config->canUpdateItem();
    }

    public function canUpdateItem(): bool
    {
        return $this->canCreateItem();
    }

    public function canDeleteItem(): bool
    {
        return $this->canCreateItem();
    }

    public function canPurgeItem(): bool
    {
        return $this->canCreateItem();
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item instanceof Config && $item->getID()) {
            $nb = 0;
            if (isset($_SESSION['glpishow_count_on_tabs']) && $_SESSION['glpishow_count_on_tabs']) {
                $nb = countElementsInTable(self::getTable(), ['plugin_niimbotlabels_configs_id' => $item->getID()]);
            }
            return self::createTabEntry(self::getTypeName(2), $nb);
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof Config && $item->getID()) {
            self::showForConfig($item);
        }
        return true;
    }

    /**
     * Retourne les colonnes d'une ligne de configuration, triées par rang,
     * prêtes à l'emploi pour le générateur Excel.
     */
    public static function getForConfig(int $configs_id): array
    {
        global $DB;

        $result = [];
        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => ['plugin_niimbotlabels_configs_id' => $configs_id],
            'ORDER' => 'rank ASC',
        ]);

        foreach ($iterator as $row) {
            $result[] = $row;
        }

        return $result;
    }

    /**
     * Affiche la liste des colonnes déjà configurées ainsi qu'un petit
     * formulaire d'ajout, dans l'onglet dédié de la fiche Config.
     */
    public static function showForConfig(Config $config)
    {
        $configs_id = $config->getID();
        $itemtype   = $config->fields['itemtype'] ?? '';

        if (empty($itemtype) || !class_exists($itemtype)) {
            echo "<p class='center'>"
                . __("Choisissez d'abord un type d'objet sur l'onglet principal, puis enregistrez la ligne.", 'niimbotlabels')
                . "</p>";
            return;
        }

        if (!$config->canUpdateItem()) {
            return;
        }

        $fields_for_dropdown = self::getFieldsForDropdown($itemtype);
        $columns             = self::getForConfig($configs_id);
        $form_url             = Toolbox::getItemTypeFormURL(Config::class);

        $template_help = __(
            'Optionnel. Construit la valeur finale à partir de la valeur brute du champ, repérée par #valeur# '
            . '(ex: https://exemple.fr/?id=#valeur# pour une URL, ou =CONCATENER("REF-";#valeur#) pour une formule '
            . 'Excel - toute chaîne commençant par "=" est traitée comme une formule par Excel). Laisser vide pour '
            . 'utiliser la valeur brute telle quelle.',
            'niimbotlabels'
        );

        if (!empty($columns)) {
            echo "<form name='niimbotlabels_editcolumns_form' method='post' action='$form_url'>";
            echo Html::hidden('id', ['value' => $configs_id]);
            echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

            echo "<table class='tab_cadre_fixe'>";
            echo "<tr><th>" . __('Ordre', 'niimbotlabels') . "</th><th>" . __('Champ GLPI source', 'niimbotlabels')
                . "</th><th>" . __('Nom de colonne (en-tête Excel)', 'niimbotlabels')
                . "</th><th>" . __('Formule (optionnel)', 'niimbotlabels') . " <i class='ti ti-info-circle' title='"
                . htmlspecialchars($template_help) . "'></i></th><th></th></tr>";

            foreach ($columns as $col) {
                $cid = (int) $col['id'];
                echo "<tr>";
                echo "<td>" . (int) $col['rank'] . "</td>";
                echo "<td>";
                Dropdown::showFromArray("columns[$cid][search_option_id]", $fields_for_dropdown, [
                    'value' => self::encodeFieldKey($col['meta_itemtype'] ?? null, (int) $col['search_option_id']),
                    'rand'  => mt_rand(),
                ]);
                echo "</td>";
                echo "<td>" . Html::input("columns[$cid][column_name]", ['value' => $col['column_name']]) . "</td>";
                echo "<td>" . Html::input("columns[$cid][template]", [
                    'value'       => $col['template'] ?? '',
                    'placeholder' => '#valeur#',
                ]) . "</td>";
                echo "<td>";
                echo "<a href='$form_url?id=$configs_id&action=delete_column&column_id=$cid' "
                    . "onclick=\"return confirm('" . __('Confirmer la suppression ?') . "');\">"
                    . "<i class='ti ti-trash'></i></a>";
                echo "</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='center' style='margin:8px 0;'>";
            echo Html::submit(__('Enregistrer les modifications', 'niimbotlabels'), ['name' => 'edit_columns']);
            echo "</div>";

            Html::closeForm();
        } else {
            echo "<p class='center'>" . __('Aucune colonne configurée', 'niimbotlabels') . "</p>";
        }

        echo "<form name='niimbotlabels_addcolumn_form' method='post' action='$form_url'>";
        echo Html::hidden('id', ['value' => $configs_id]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);
        echo "<table class='tab_cadre_fixe'>";
        echo "<tr><th colspan='4'>" . __('Ajouter une colonne', 'niimbotlabels') . "</th></tr>";
        echo "<tr>";
        echo "<td>" . __('Champ GLPI source', 'niimbotlabels') . "</td><td>";
        Dropdown::showFromArray('search_option_id', $fields_for_dropdown, [
            'display_emptychoice' => true,
            'rand'                 => mt_rand(),
        ]);
        echo "</td>";
        echo "<td>" . __('Nom de colonne (en-tête Excel)', 'niimbotlabels') . "</td><td>";
        echo Html::input('column_name', ['value' => '']);
        echo "</td>";
        echo "<td>" . __('Formule (optionnel)', 'niimbotlabels') . " <i class='ti ti-info-circle' title='"
            . htmlspecialchars($template_help) . "'></i></td><td>";
        echo Html::input('template', ['value' => '', 'placeholder' => '#valeur#']);
        echo "</td>";
        echo "<td>";
        echo Html::submit(_sx('button', 'Add'), ['name' => 'add_column']);
        echo "</td>";
        echo "</tr>";
        echo "</table>";
        Html::closeForm();

        self::showPreview($config);
    }

    /**
     * Aperçu des données telles qu'elles apparaîtront dans la feuille
     * "Datas" du fichier Excel (mêmes colonnes, mêmes valeurs, même
     * ordre), limité aux premières lignes, à partir des critères et
     * colonnes actuellement enregistrés sur la ligne. Permet de mettre au
     * point la requête et les colonnes sans avoir à télécharger le fichier
     * à chaque essai.
     */
    private static function showPreview(Config $config): void
    {
        $limit   = 25;
        $preview = ExcelGenerator::previewData($config, $limit);

        echo "<h3 style='margin-top:24px;'>" . __('Aperçu des données', 'niimbotlabels') . "</h3>";

        if (!$preview['success']) {
            echo "<p class='center'>" . htmlspecialchars($preview['message']) . "</p>";
            return;
        }

        if (empty($preview['rows'])) {
            echo "<p class='center'>"
                . __('Aucune donnée ne correspond aux critères actuels.', 'niimbotlabels')
                . htmlspecialchars($preview['debug'] ?? '')
                . "</p>";
            return;
        }

        $summary = $preview['truncated']
            ? sprintf(
                __('%d ligne(s) au total, %d affichée(s) ci-dessous.', 'niimbotlabels'),
                $preview['row_count'],
                count($preview['rows'])
            )
            : sprintf(__('%d ligne(s).', 'niimbotlabels'), $preview['row_count']);
        echo "<p>" . htmlspecialchars($summary) . "</p>";

        echo "<div style='overflow-x:auto;'><table class='tab_cadre_fixe'>";
        echo "<tr>";
        foreach ($preview['header'] as $label) {
            echo "<th>" . htmlspecialchars($label) . "</th>";
        }
        echo "</tr>";
        foreach ($preview['rows'] as $row_values) {
            echo "<tr>";
            foreach ($row_values as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table></div>";
    }

    /**
     * Liste des champs disponibles pour un itemtype donné, regroupés par
     * table d'origine (comme dans le moteur de recherche natif de GLPI),
     * pour éviter d'afficher de nombreuses entrées ambiguës comme "Nom"
     * sans indication de leur provenance (Ordinateur, Utilisateur,
     * Fabricant...).
     *
     * @param bool $include_meta Inclut aussi les champs "liés" (ex: IP,
     *                           MAC, alias réseau via NetworkPort pour un
     *                           Ordinateur), disponibles via le mécanisme
     *                           de recherche "méta" natif de GLPI (les
     *                           mêmes types que ceux proposables en
     *                           critère de recherche additionnel dans le
     *                           moteur de recherche standard), même si ces
     *                           champs ne font pas partie de
     *                           Search::getOptions($itemtype) lui-même.
     */
    public static function getFieldsForDropdown(string $itemtype, bool $include_meta = true): array
    {
        // Search::getOptions() est exactement la structure utilisée par le
        // moteur de recherche natif de GLPI (mêmes groupes, dans le même
        // ordre, correspondant en pratique aux onglets de la fiche) pour
        // construire par exemple la liste des colonnes/critères sur la
        // page "Ordinateurs". On la réutilise telle quelle : un groupe est
        // une entrée dont la clé n'est pas numérique (valeur = libellé du
        // groupe, sous forme de chaîne ou de tableau ['name' => ...] selon
        // les cas), un champ est une entrée numérique (tableau avec au
        // moins une clé 'name'). On conserve l'ordre natif (pas de tri
        // alphabétique) et on n'ajoute aucune précision entre parenthèses :
        // le regroupement suffit à lever l'ambiguïté, exactement comme
        // dans GLPI.
        $options = Search::getOptions($itemtype);
        $grouped = [];
        $group   = class_exists($itemtype) ? $itemtype::getTypeName(1) : $itemtype;

        foreach ($options as $key => $opt) {
            if (!is_numeric($key)) {
                // Entrée d'en-tête de groupe.
                $label = is_string($opt) ? $opt : (is_array($opt) ? ($opt['name'] ?? null) : null);
                if (!empty($label)) {
                    $group = $label;
                }
                continue;
            }
            if (!is_array($opt) || !isset($opt['name'])) {
                continue;
            }
            $grouped[$group][(int) $key] = $opt['name'];
        }

        if ($include_meta && class_exists($itemtype)) {
            foreach (self::getMetaFieldsForDropdown($itemtype) as $meta_group_label => $meta_fields) {
                $grouped[$meta_group_label] = $meta_fields;
            }
        }

        return $grouped;
    }

    /**
     * Champs "liés" (recherche méta native de GLPI) : itemtypes joignables
     * en 1 niveau depuis $itemtype (ex: NetworkPort pour un Ordinateur),
     * eux-mêmes non listés par Search::getOptions($itemtype), mais dont les
     * champs (IP, MAC, alias réseau...) sont bien accessibles via ce
     * mécanisme natif. Un groupe par itemtype lié, avec une clé composite
     * ("META|Itemtype|id") pour chaque champ, à décoder via
     * self::decodeFieldKey().
     */
    private static function getMetaFieldsForDropdown(string $itemtype): array
    {
        $result = [];

        foreach (Search::getMetaItemtypeAvailable($itemtype) as $meta_itemtype) {
            if (!class_exists($meta_itemtype)) {
                continue;
            }

            $meta_options = Search::getOptions($meta_itemtype);
            $meta_fields  = [];

            foreach ($meta_options as $mkey => $mopt) {
                if (!is_numeric($mkey) || !is_array($mopt) || !isset($mopt['name'])) {
                    continue;
                }
                $mid = (int) $mkey;
                $meta_fields[self::encodeFieldKey($meta_itemtype, $mid)] = $mopt['name'];
            }

            if (empty($meta_fields)) {
                continue;
            }

            $group_label = sprintf(__('%s (lié)', 'niimbotlabels'), $meta_itemtype::getTypeName(1));
            $result[$group_label] = $meta_fields;
        }

        return $result;
    }

    /**
     * Encode un champ "de base" (meta_itemtype=null) ou "lié" (recherche
     * méta) en une valeur unique utilisable comme clé de tableau / valeur
     * de liste déroulante.
     *
     * @return int|string
     */
    public static function encodeFieldKey(?string $meta_itemtype, int $search_option_id)
    {
        if (!empty($meta_itemtype)) {
            return 'META|' . $meta_itemtype . '|' . $search_option_id;
        }
        return $search_option_id;
    }

    /**
     * Décode une valeur produite par self::encodeFieldKey() (ou un simple
     * ID numérique, pour compatibilité avec les colonnes existantes) en
     * ['meta_itemtype' => ?string, 'search_option_id' => int].
     */
    public static function decodeFieldKey($key): array
    {
        $key = (string) $key;
        if (str_starts_with($key, 'META|')) {
            $parts = explode('|', $key, 3);
            if (count($parts) === 3 && $parts[1] !== '' && is_numeric($parts[2])) {
                return ['meta_itemtype' => $parts[1], 'search_option_id' => (int) $parts[2]];
            }
        }
        return ['meta_itemtype' => null, 'search_option_id' => (int) $key];
    }

    /**
     * Aplati un tableau de champs groupés (id => label) pour un usage
     * simple (ex: affichage d'une colonne déjà enregistrée).
     */
    public static function flattenGroupedFields(array $grouped): array
    {
        $flat = [];
        foreach ($grouped as $fields) {
            foreach ($fields as $id => $label) {
                $flat[$id] = $label;
            }
        }
        return $flat;
    }
}
