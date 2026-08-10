<?php

namespace GlpiPlugin\Niimbotlabels;

use Glpi\Toolbox\DataExport;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Search;
use Session;

/**
 * Génère le fichier Excel d'une ligne de configuration en exécutant la
 * recherche GLPI native (même moteur/mêmes structures que le moteur de
 * recherche standard), et l'envoie directement au navigateur (pas de
 * stockage intermédiaire dans le module Document de GLPI : le fichier est
 * généré à la demande, à chaque téléchargement).
 */
class ExcelGenerator
{
    /**
     * Construit le classeur Excel pour la ligne de configuration donnée.
     *
     * @return array{success: bool, message: string, spreadsheet?: Spreadsheet, filename?: string, row_count?: int}
     */
    public static function build(Config $config): array
    {
        $export = self::computeExport($config, null);
        if (!$export['success']) {
            return $export;
        }

        $itemtype = $export['itemtype'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Datas');

        // Note : setCellValueByColumnAndRow() a été supprimée dans
        // PhpSpreadsheet 2.x ; on utilise ici la syntaxe [colonne, ligne]
        // supportée par setCellValue(), compatible 1.x et 2.x.
        $col = 1;
        foreach ($export['header'] as $label) {
            $sheet->setCellValue([$col, 1], $label);
            $col++;
        }

        $row_index = 2;
        foreach ($export['data_rows'] as $values) {
            $col = 1;
            foreach ($values as $value) {
                $sheet->setCellValue([$col, $row_index], $value);
                $col++;
            }
            $row_index++;
        }

        $param_sheet = $spreadsheet->createSheet();
        $param_sheet->setTitle('Paramétrage extraction');
        self::fillParamSheet($param_sheet, $config, $itemtype);

        $filename = trim((string) ($config->fields['filename'] ?? ''));
        if ($filename === '') {
            $filename = 'niimbot_export_' . $config->fields['id'];
        }
        // Normalise l'extension (évite les doublons du type "test.xls.xlsx"
        // si l'utilisateur a déjà saisi une extension).
        $filename = preg_replace('/\.(xlsx|xls)$/i', '', $filename);
        $filename .= '.xlsx';

        return [
            'success'   => true,
            'message'   => sprintf(__('Fichier généré avec succès (%d lignes).', 'niimbotlabels'), $export['row_count']) . $export['debug'],
            'spreadsheet' => $spreadsheet,
            'filename'  => $filename,
            'row_count' => $export['row_count'],
        ];
    }

    /**
     * Calcule un aperçu des données (mêmes colonnes, mêmes valeurs, même
     * ordre que la feuille "Datas" du fichier Excel généré), limité à
     * $limit lignes, pour affichage direct dans la fiche de configuration.
     * Permet de mettre au point la requête (critères) et les colonnes sans
     * avoir à télécharger le fichier à chaque essai.
     *
     * @return array{success: bool, message?: string, header?: string[], rows?: array, row_count?: int, truncated?: bool, debug?: string}
     */
    public static function previewData(Config $config, int $limit = 25): array
    {
        $export = self::computeExport($config, $limit);
        if (!$export['success']) {
            return $export;
        }

        return [
            'success'   => true,
            'header'    => $export['header'],
            'rows'      => $export['data_rows'],
            'row_count' => $export['row_count'],
            'truncated' => $export['truncated'],
            'debug'     => $export['debug'],
        ];
    }

    /**
     * Logique commune à build() (fichier Excel complet) et previewData()
     * (aperçu limité dans la fiche) : exécute la recherche et calcule,
     * pour chaque ligne de résultat, les valeurs de colonnes déjà mises en
     * forme (nettoyage HTML + formule/gabarit "#valeur#" éventuel).
     *
     * @return array{success: bool, message?: string, itemtype?: string, columns?: array, header?: string[], data_rows?: array, row_count?: int, truncated?: bool, debug?: string}
     */
    private static function computeExport(Config $config, ?int $limit): array
    {
        $itemtype = $config->fields['itemtype'] ?? '';

        if (empty($itemtype) || !class_exists($itemtype)) {
            return [
                'success' => false,
                'message' => sprintf(__("Type d'objet invalide : %s", 'niimbotlabels'), $itemtype),
            ];
        }

        $columns = ConfigColumn::getForConfig((int) $config->fields['id']);
        if (empty($columns)) {
            return [
                'success' => false,
                'message' => __("Aucune colonne n'est configurée pour cette ligne.", 'niimbotlabels'),
            ];
        }

        // Les IDs d'option de recherche des colonnes voulues doivent être
        // explicitement forcés ("forcedisplay") : sans ça, Search::getDatas()
        // ne sélectionne/joint en SQL que les colonnes des préférences
        // d'affichage par défaut de l'utilisateur, pas celles configurées
        // ici. C'est ce qui produisait un fichier avec des cellules vides.
        // Seuls les champs "de base" (pas de meta_itemtype) sont concernés
        // ici : les champs "liés" (ex: IP via NetworkPort) passent par le
        // mécanisme de recherche méta natif de GLPI (metacriteria), géré à
        // part ci-dessous.
        $forcedisplay = array_values(array_unique(array_map(
            static fn ($column) => (int) $column['search_option_id'],
            array_filter($columns, static fn ($column) => empty($column['meta_itemtype']))
        )));

        // Champs "liés" (ex: IP/MAC/alias réseau via NetworkPort pour un
        // Ordinateur) : GLPI ne les inclut dans les résultats que si un
        // "metacriteria" pour l'itemtype lié est présent (c'est ce qui
        // déclenche la jointure), même sans filtrer réellement sur sa
        // valeur. On force donc un metacriteria "toujours vrai" (ID > 0)
        // par itemtype lié distinct utilisé par au moins une colonne.
        $meta_itemtypes = array_values(array_unique(array_filter(array_map(
            static fn ($column) => $column['meta_itemtype'] ?? null,
            $columns
        ))));
        $extra_metacriteria = [];
        foreach ($meta_itemtypes as $meta_itemtype) {
            if (!class_exists($meta_itemtype)) {
                continue;
            }
            $meta_id_option = Search::getOptionNumber($meta_itemtype, 'id');
            if ($meta_id_option > 0) {
                $extra_metacriteria[] = [
                    'itemtype'   => $meta_itemtype,
                    'link'       => 'AND',
                    'field'      => $meta_id_option,
                    'searchtype' => 'morethan',
                    'value'      => 0,
                ];
            }
        }

        $rows = self::runSearch($config, $itemtype, $forcedisplay, null, $extra_metacriteria);

        // Diagnostic si aucun résultat : le périmètre d'entité seul (sans
        // les critères spécifiques de la ligne) renvoie-t-il quelque
        // chose ? Permet de distinguer "mauvais périmètre d'entité" de
        // "critère qui ne correspond à rien", sans avoir à deviner.
        $debug = '';
        if (empty($rows)) {
            $baseline = self::runSearch($config, $itemtype, [], []);
            $entity_label = (int) $config->fields['entities_id'] === 0
                ? __('Entité racine', 'niimbotlabels')
                : \Dropdown::getDropdownName('glpi_entities', (int) $config->fields['entities_id']);
            $debug = sprintf(
                ' (diagnostic : périmètre = "%s"%s → %d élément(s) visibles hors critères ; %d critère(s) configuré(s) sur la ligne)',
                $entity_label,
                $config->fields['is_recursive'] ? ' + sous-entités' : '',
                count($baseline),
                count($config->getSearchCriteria())
            );
        }

        $header = array_map(static fn ($column) => $column['column_name'], $columns);

        $total_count  = count($rows);
        $limited_rows = $limit !== null ? array_slice($rows, 0, $limit) : $rows;

        // Important : dans le moteur de recherche de GLPI 11, chaque ligne
        // de résultat n'est PAS indexée directement par l'ID d'option de
        // recherche (contrairement aux anciennes versions). La clé est
        // "{itemtype}_{id}" (ex: "Computer_15"), et la valeur est un
        // tableau contenant, entre autres, "displayname" (texte déjà mis
        // en forme, potentiellement avec du HTML à nettoyer). C'est
        // exactement ce que fait le propre export Excel natif de GLPI
        // (Glpi\Search\Output\Spreadsheet::displayData()), qu'on reproduit
        // ici à l'identique.
        $data_rows = [];
        foreach ($limited_rows as $line) {
            $values = [];
            foreach ($columns as $column) {
                // Un champ "lié" (recherche méta, ex: IP via NetworkPort)
                // apparaît dans la ligne sous la clé de SON itemtype, pas
                // celui de la ligne d'export.
                $colkey_itemtype = !empty($column['meta_itemtype']) ? $column['meta_itemtype'] : $itemtype;
                $colkey = $colkey_itemtype . '_' . (int) $column['search_option_id'];
                $raw = $line[$colkey]['displayname'] ?? '';
                $value = self::normalizeCellValue((string) $raw);

                // Formule libre optionnelle : construit la valeur finale de
                // la cellule à partir de la valeur brute, repérée par
                // "#valeur#" (ex: URL, ou formule Excel si elle commence
                // par "=" - PhpSpreadsheet l'interprète alors nativement
                // comme telle à l'ouverture dans Excel).
                $template = trim((string) ($column['template'] ?? ''));
                if ($template !== '') {
                    $value = str_replace('#valeur#', $value, $template);
                }

                $values[] = $value;
            }
            $data_rows[] = $values;
        }

        return [
            'success'   => true,
            'itemtype'  => $itemtype,
            'columns'   => $columns,
            'header'    => $header,
            'data_rows' => $data_rows,
            'row_count' => $total_count,
            'truncated' => $limit !== null && $total_count > $limit,
            'debug'     => $debug,
        ];
    }

    /**
     * Génère le fichier Excel de la ligne de configuration et l'envoie
     * directement au navigateur en téléchargement (aucun stockage
     * intermédiaire dans GLPI). Termine l'exécution du script (comme les
     * autres points d'envoi de fichier de GLPI, ex: document.send.php).
     */
    public static function download(Config $config): void
    {
        $result = self::build($config);

        if (!$result['success']) {
            \Session::addMessageAfterRedirect($result['message'], true, ERROR);
            \Html::redirect(Config::getFormURLWithID((int) $config->fields['id']));
            exit;
        }

        if (($result['row_count'] ?? 0) === 0) {
            // Un téléchargement ("Content-Disposition: attachment") ne
            // recharge pas la page dans le navigateur : un message mis en
            // attente ici ne s'afficherait donc jamais tant que
            // l'utilisateur ne navigue pas réellement ailleurs. On annule
            // donc le téléchargement et on redirige explicitement vers la
            // fiche (avec le diagnostic détaillé), plutôt que de livrer un
            // fichier vide sans explication visible.
            \Session::addMessageAfterRedirect($result['message'], true, WARNING);
            \Html::redirect(Config::getFormURLWithID((int) $config->fields['id']));
            exit;
        }

        $writer = new Xlsx($result['spreadsheet']);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . $result['filename'] . '"');
        header('Cache-Control: max-age=0');
        header('Pragma: public');

        $writer->save('php://output');
        exit;
    }

    /**
     * Exécute la recherche GLPI native pour le périmètre (entité +
     * sous-entités) et les critères propres à la ligne de configuration,
     * indépendamment de l'entité active de l'utilisateur qui déclenche la
     * génération.
     *
     * @param int[]      $forcedisplay      IDs d'option de recherche à
     *                                      forcer dans la sélection SQL
     *                                      (colonnes configurées pour cette
     *                                      ligne).
     * @param array|null $criteria_override  Si fourni (même vide), utilisé
     *                                       à la place des critères
     *                                       enregistrés sur la ligne (sert
     *                                       au diagnostic "périmètre seul,
     *                                       hors critères").
     * @param array      $extra_metacriteria Metacriteria à forcer (champs
     *                                       "liés", ex: IP via
     *                                       NetworkPort) en plus de celles
     *                                       de la ligne (aucune pour
     *                                       l'instant côté critères de
     *                                       filtre).
     */
    private static function runSearch(
        Config $config,
        string $itemtype,
        array $forcedisplay,
        ?array $criteria_override = null,
        array $extra_metacriteria = []
    ): array {
        $previous_entities       = $_SESSION['glpiactiveentities'] ?? null;
        $previous_active_entity  = $_SESSION['glpiactive_entity'] ?? null;
        $previous_active_entity_recursive = $_SESSION['glpiactive_entity_recursive'] ?? null;

        // entities_id = 0 signifie "Entité racine" : on utilise le mode
        // spécial "all" de changeActiveEntities() plutôt que l'ID 0 brut,
        // qui exige sinon que l'entité 0 soit explicitement listée parmi
        // les entités actives du profil de l'utilisateur (peut échouer
        // silencieusement selon le profil, même pour un compte avec accès
        // complet).
        $entities_id = (int) $config->fields['entities_id'];
        Session::changeActiveEntities(
            $entities_id === 0 ? 'all' : $entities_id,
            (bool) $config->fields['is_recursive']
        );

        $criteria = $criteria_override ?? $config->getSearchCriteria();
        if (empty($criteria)) {
            // Une ligne sans le moindre critère doit renvoyer tout le
            // périmètre ("aucun filtre" = "tout"). Or le moteur de
            // recherche natif de GLPI traite un jeu de critères
            // entièrement vide comme "aucune recherche encore lancée"
            // (l'état d'affichage initial de la page de recherche, avant
            // clic sur "Rechercher") et ne remonte alors aucune ligne. On
            // ajoute donc un critère toujours vrai (ID > 0) pour forcer
            // l'exécution réelle de la recherche.
            $id_option = Search::getOptionNumber($itemtype, 'id');
            if ($id_option > 0) {
                $criteria = [[
                    'field'      => $id_option,
                    'searchtype' => 'morethan',
                    'value'      => 0,
                    'link'       => 'AND',
                ]];
            }
        }

        $search_params = [
            'is_deleted'   => 0,
            'start'        => 0,
            'criteria'     => $criteria,
            'metacriteria' => $extra_metacriteria,
            'export_all'   => 1,
        ];

        $rows = [];

        try {
            $data = Search::getDatas($itemtype, $search_params, $forcedisplay);
            if (!empty($data['data']['rows'])) {
                $rows = $data['data']['rows'];
            }
        } finally {
            // Restauration du périmètre d'entités actif de l'utilisateur.
            if ($previous_entities !== null) {
                $_SESSION['glpiactiveentities'] = $previous_entities;
            }
            if ($previous_active_entity !== null) {
                $_SESSION['glpiactive_entity'] = $previous_active_entity;
            }
            if ($previous_active_entity_recursive !== null) {
                $_SESSION['glpiactive_entity_recursive'] = $previous_active_entity_recursive;
            }
        }

        return $rows;
    }

    /**
     * Nettoie une valeur brute (potentiellement HTML) pour une cellule
     * Excel, en réutilisant l'utilitaire natif de GLPI. GLPI affiche
     * parfois un séparateur purement visuel (icône sans texte, ex: chevron
     * entre les niveaux d'une arborescence d'entités "Root > Pessac > ...")
     * plutôt qu'un caractère texte ; DataExport::normalizeValueForTextExport()
     * supprime ces icônes vides, ce qui recolle les segments entre eux sans
     * séparateur visible. On les remplace par " / " avant nettoyage.
     */
    private static function normalizeCellValue(string $raw): string
    {
        $raw = (string) preg_replace(
            '/<i\b[^>]*class="[^"]*\b(?:ti-|fa-)[^"]*"[^>]*>\s*<\/i>/i',
            ' / ',
            $raw
        );
        $raw = (string) preg_replace(
            '/<i\b[^>]*class="[^"]*\b(?:ti-|fa-)[^"]*"[^>]*\/>/i',
            ' / ',
            $raw
        );

        return DataExport::normalizeValueForTextExport($raw);
    }

    /**
     * Remplit la feuille "Paramétrage extraction" : périmètre (type
     * d'objet, entité, sous-entités) et critères de recherche utilisés
     * pour produire la feuille "Datas", en clair (pas d'ID technique).
     */
    private static function fillParamSheet(Worksheet $sheet, Config $config, string $itemtype): void
    {
        $row = 1;

        $sheet->setCellValue([1, $row], __("Ligne d'export", 'niimbotlabels'));
        $sheet->setCellValue([2, $row], $config->fields['name'] ?? '');
        $row++;

        $sheet->setCellValue([1, $row], __("Type d'objet", 'niimbotlabels'));
        $sheet->setCellValue([2, $row], class_exists($itemtype) ? $itemtype::getTypeName(1) : $itemtype);
        $row++;

        $entities_id = (int) $config->fields['entities_id'];
        $entity_label = $entities_id === 0
            ? __('Entité racine', 'niimbotlabels')
            : \Dropdown::getDropdownName('glpi_entities', $entities_id);
        $sheet->setCellValue([1, $row], __('Entité', 'niimbotlabels'));
        $sheet->setCellValue([2, $row], $entity_label);
        $row++;

        $sheet->setCellValue([1, $row], __('Sous-entités', 'niimbotlabels'));
        $sheet->setCellValue([2, $row], $config->fields['is_recursive'] ? __('Oui', 'niimbotlabels') : __('Non', 'niimbotlabels'));
        $row += 2;

        $criteria = $config->getSearchCriteria();

        $sheet->setCellValue([1, $row], __('Critères de recherche', 'niimbotlabels'));
        $row++;

        if (empty($criteria)) {
            $sheet->setCellValue([1, $row], __('Aucun critère : toutes les données du périmètre ci-dessus.', 'niimbotlabels'));
            $row++;
        } else {
            $fields_flat = ConfigColumn::flattenGroupedFields(ConfigColumn::getFieldsForDropdown($itemtype));
            $searchtypes = Config::getSearchtypeLabels();
            $links = [
                'AND'     => __('ET', 'niimbotlabels'),
                'OR'      => __('OU', 'niimbotlabels'),
                'AND NOT' => __('ET NON', 'niimbotlabels'),
            ];

            $sheet->setCellValue([1, $row], __('Lien', 'niimbotlabels'));
            $sheet->setCellValue([2, $row], __('Champ', 'niimbotlabels'));
            $sheet->setCellValue([3, $row], __('Opérateur', 'niimbotlabels'));
            $sheet->setCellValue([4, $row], __('Valeur', 'niimbotlabels'));
            $row++;

            foreach ($criteria as $index => $criterion) {
                $field_id = (int) ($criterion['field'] ?? 0);
                $field_label = $fields_flat[$field_id] ?? ('#' . $field_id);
                $searchtype = $criterion['searchtype'] ?? 'contains';
                $searchtype_label = $searchtypes[$searchtype] ?? $searchtype;
                $link = (string) ($criterion['link'] ?? 'AND');

                $sheet->setCellValue([1, $row], $index === 0 ? '' : ($links[$link] ?? $link));
                $sheet->setCellValue([2, $row], $field_label);
                $sheet->setCellValue([3, $row], $searchtype_label);
                $sheet->setCellValue([4, $row], (string) ($criterion['value'] ?? ''));
                $row++;
            }
        }

        foreach (['A', 'B', 'C', 'D'] as $col_letter) {
            $sheet->getColumnDimension($col_letter)->setAutoSize(true);
        }
    }
}
