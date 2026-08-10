<?php

namespace GlpiPlugin\Niimbotlabels;

use CommonGLPI;
use DbUtils;
use Html;
use Profile;
use ProfileRight as CoreProfileRight;
use Session;

/**
 * Onglet "Étiquettes Niimbot" sur la fiche Profil, gérant le droit
 * plugin_niimbotlabels_config via la matrice de droits native de GLPI
 * (Profile::displayRightsChoiceMatrix), pour une sauvegarde garantie
 * compatible avec le mécanisme natif de front/profile.form.php.
 *
 * Pattern repris tel quel du plugin "dnsmanager" (même auteur), déjà
 * fonctionnel sur cette instance GLPI 11.
 */
class ProfileRight extends Profile
{
    public static $rightname = 'profile';

    public const RIGHTNAME = 'plugin_niimbotlabels_config';

    public static function getTypeName($nb = 0): string
    {
        return _n('Étiquettes Niimbot', 'Étiquettes Niimbot', $nb, 'niimbotlabels');
    }

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if ($item->getType() === 'Profile' && $item->getField('interface') === 'central') {
            return self::createTabEntry(self::getTypeName());
        }
        return '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item->getType() === 'Profile') {
            $ID   = $item->getID();
            $prof = new self();

            $profileRight = new CoreProfileRight();
            $dbu = new DbUtils();
            if ($dbu->countElementsInTable('glpi_profilerights', ['profiles_id' => $ID, 'name' => self::RIGHTNAME]) === 0) {
                $profileRight->add(['profiles_id' => $ID, 'name' => self::RIGHTNAME, 'rights' => 0]);
            }

            $prof->showForm($ID);
        }
        return true;
    }

    public function showForm($profiles_id = 0, $openform = true, $closeform = true)
    {
        $canedit = Session::haveRightsOr(self::$rightname, [CREATE, UPDATE, PURGE]);
        $profile = new Profile();
        $profile->getFromDB($profiles_id);

        echo "<div class='firstbloc'>";
        echo "<form name='form' method='post' action='" . $profile->getFormURL() . "'>";
        echo Html::hidden('id', ['value' => $profiles_id]);
        echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken()]);

        $profile->displayRightsChoiceMatrix(self::getAllRights(), [
            'canedit'       => $canedit,
            'default_class' => 'tab_bg_2',
            'title'         => self::getTypeName(2),
        ]);

        if ($canedit) {
            echo "<div class='center'>";
            echo Html::submit(_sx('button', 'Save'), ['name' => 'update']);
            echo "</div>";
        }

        Html::closeForm();
        echo "</div>";

        return true;
    }

    public static function getAllRights(): array
    {
        return [
            [
                'itemtype' => Config::class,
                'label'    => Config::getTypeName(2),
                'field'    => self::RIGHTNAME,
            ],
        ];
    }

    /**
     * Attribue des valeurs par défaut raisonnables à l'installation :
     * tous les droits pour le profil Super-Admin, aucun pour les autres
     * (à ajuster ensuite via l'onglet, ou en base pour les profils dont le
     * droit est déjà positionné).
     */
    public static function addDefaultProfileRights(): void
    {
        global $DB;

        $profileRight = new CoreProfileRight();
        $dbu          = new DbUtils();

        foreach ($DB->request(['FROM' => 'glpi_profiles']) as $profile) {
            $profileId = (int) $profile['id'];
            $value     = ($profile['name'] === 'Super-Admin') ? ALLSTANDARDRIGHT : 0;

            if ($dbu->countElementsInTable('glpi_profilerights', ['profiles_id' => $profileId, 'name' => self::RIGHTNAME]) === 0) {
                $profileRight->add(['profiles_id' => $profileId, 'name' => self::RIGHTNAME, 'rights' => $value]);
            } else {
                $DB->update(
                    'glpi_profilerights',
                    ['rights' => $value],
                    ['profiles_id' => $profileId, 'name' => self::RIGHTNAME, 'rights' => 0]
                );
            }
        }
    }

    public static function removeProfileRights(): void
    {
        CoreProfileRight::deleteProfileRights([self::RIGHTNAME]);
    }

    /**
     * Resynchronise le droit du plugin dans la session active à chaque
     * requête, pour qu'un changement de droit soit pris en compte
     * immédiatement (sans nécessiter une déconnexion/reconnexion).
     */
    public static function initProfile(): void
    {
        global $DB;

        $dbu = new DbUtils();
        if ($dbu->countElementsInTable('glpi_profilerights', ['name' => self::RIGHTNAME]) === 0) {
            CoreProfileRight::addProfileRights([self::RIGHTNAME]);
        }

        $profileId = $_SESSION['glpiactiveprofile']['id'] ?? 0;
        if ($profileId) {
            foreach ($DB->request([
                'FROM'  => 'glpi_profilerights',
                'WHERE' => ['profiles_id' => $profileId, 'name' => self::RIGHTNAME],
            ]) as $prof) {
                $_SESSION['glpiactiveprofile'][$prof['name']] = $prof['rights'];
            }
        }
    }
}
