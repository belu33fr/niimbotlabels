/**
 * Petite interactivite pour l'editeur de criteres de recherche de la fiche
 * Niimbot Labels : ajout / suppression dynamique de lignes de criteres,
 * sans rechargement de page. Le formulaire reste fonctionnel sans JS
 * (une ligne vide est toujours affichee par defaut cote serveur).
 */
(function () {
    'use strict';

    function nextIndex(container) {
        var rows = container.querySelectorAll('.niimbotlabels-criteria-row');
        return rows.length;
    }

    document.addEventListener('click', function (e) {
        var addBtn = e.target.closest('.niimbotlabels-add-criteria');
        if (addBtn) {
            var container = document.getElementById('niimbotlabels_criteria_rows');
            var template = document.getElementById('niimbotlabels-criteria-template');
            if (!container || !template) {
                return;
            }
            var index = nextIndex(container);
            var html = template.innerHTML.replace(/__INDEX__/g, index);
            var tmp = document.createElement('tbody');
            tmp.innerHTML = html;
            container.appendChild(tmp.firstElementChild);
            return;
        }

        var removeBtn = e.target.closest('.niimbotlabels-remove-criteria');
        if (removeBtn) {
            var row = removeBtn.closest('.niimbotlabels-criteria-row');
            if (row) {
                row.parentNode.removeChild(row);
            }
        }
    });
})();
