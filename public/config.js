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

    // Les balises <script> injectees via innerHTML ne s'executent pas
    // automatiquement (comportement standard des navigateurs) : sans ca,
    // le <script> genere par GLPI juste apres chaque <select> (qui active
    // la liste deroulante avec recherche/filtre - select2) ne se lance
    // jamais sur les lignes de criteres ajoutees dynamiquement. On les
    // recree et reinsere manuellement pour forcer leur execution.
    function executeScripts(container) {
        var scripts = container.querySelectorAll('script');
        scripts.forEach(function (oldScript) {
            var newScript = document.createElement('script');
            for (var i = 0; i < oldScript.attributes.length; i++) {
                var attr = oldScript.attributes[i];
                newScript.setAttribute(attr.name, attr.value);
            }
            newScript.textContent = oldScript.textContent;
            oldScript.parentNode.replaceChild(newScript, oldScript);
        });
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
            var newRow = tmp.firstElementChild;
            container.appendChild(newRow);
            executeScripts(newRow);
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
