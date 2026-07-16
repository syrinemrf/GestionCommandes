if (window.jQuery && $.fn.dataTable) {
    $.extend(true, $.fn.dataTable.defaults, {
        language: {
            emptyTable: "Aucune donnée disponible",
            info: "Affichage de _START_ à _END_ sur _TOTAL_ éléments",
            infoEmpty: "Affichage de 0 à 0 sur 0 élément",
            infoFiltered: "(filtré depuis _MAX_ éléments)",
            lengthMenu: "Afficher _MENU_ éléments",
            loadingRecords: "Chargement...",
            processing: "Traitement...",
            search: "Rechercher :",
            zeroRecords: "Aucun résultat trouvé",
            paginate: {
                first: "Premier",
                last: "Dernier",
                next: "Suivant",
                previous: "Précédent"
            }
        }
    });
}

window.showToast = function (message, type = 'success') {
    let container = document.querySelector('.toast-container');

    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `app-toast app-toast-${type}`;
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('is-visible'));

    setTimeout(() => {
        toast.classList.remove('is-visible');
        setTimeout(() => toast.remove(), 250);
    }, 3500);
};

window.confirmAction = function (message) {
    return new Promise((resolve) => {
        const overlay = document.createElement('div');
        overlay.className = 'confirm-overlay';
        overlay.innerHTML = `
            <div class="confirm-dialog" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
                <h2 id="confirm-title">Confirmation</h2>
                <p></p>
                <div class="confirm-actions">
                    <button type="button" class="btn-secondary confirm-cancel">Annuler</button>
                    <button type="button" class="btn-primary confirm-accept">Confirmer</button>
                </div>
            </div>`;

        overlay.querySelector('p').textContent = message;
        document.body.appendChild(overlay);

        const close = (answer) => {
            overlay.remove();
            resolve(answer);
        };

        overlay.querySelector('.confirm-cancel').addEventListener('click', () => close(false));
        overlay.querySelector('.confirm-accept').addEventListener('click', () => close(true));
        overlay.addEventListener('click', (event) => {
            if (event.target === overlay) close(false);
        });
        overlay.querySelector('.confirm-accept').focus();
    });
};
