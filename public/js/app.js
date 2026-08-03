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

document.addEventListener('click', async (event) => {
    const logoutLink = event.target.closest('.logout-confirm');

    if (!logoutLink) {
        return;
    }

    event.preventDefault();

    const confirmed = await window.confirmAction('Voulez-vous vraiment vous déconnecter ?');

    if (confirmed) {
        window.location.href = logoutLink.href;
    }
});

let activeRowActionsMenu = null;

function closeRowActionsMenu() {
    if (!activeRowActionsMenu) {
        return;
    }

    closeDocumentsMenu();
    activeRowActionsMenu.dropdown.hidden = true;
    activeRowActionsMenu.trigger.setAttribute('aria-expanded', 'false');
    activeRowActionsMenu = null;
}

function positionRowActionsMenu(trigger, dropdown) {
    const triggerRect = trigger.getBoundingClientRect();
    const menuWidth = dropdown.offsetWidth;
    const menuHeight = dropdown.offsetHeight;
    const gap = 6;
    const viewportGap = 10;
    let left = triggerRect.right - menuWidth;
    let top = triggerRect.bottom + gap;

    left = Math.max(
        viewportGap,
        Math.min(left, window.innerWidth - menuWidth - viewportGap)
    );

    if (top + menuHeight > window.innerHeight - viewportGap) {
        top = Math.max(
            viewportGap,
            triggerRect.top - menuHeight - gap
        );
    }

    dropdown.style.left = `${left}px`;
    dropdown.style.top = `${top}px`;
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.row-actions-trigger');

    if (trigger) {
        const dropdown = trigger
            .closest('.row-actions-menu')
            ?.querySelector('.row-actions-dropdown');

        if (!dropdown) {
            return;
        }

        const isCurrentMenu = activeRowActionsMenu?.trigger === trigger;
        closeRowActionsMenu();

        if (!isCurrentMenu) {
            dropdown.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            positionRowActionsMenu(trigger, dropdown);
            activeRowActionsMenu = { trigger, dropdown };
        }

        return;
    }

    if (!event.target.closest('.row-actions-dropdown')) {
        closeRowActionsMenu();
        return;
    }

    if (event.target.closest('.documents-menu-trigger')) {
        return;
    }

    if (event.target.closest('.documents-menu-dropdown a')) {
        closeRowActionsMenu();
        return;
    }

    if (event.target.closest('.row-action-item')) {
        closeRowActionsMenu();
    }
});

let activeDocumentsMenu = null;

function closeDocumentsMenu() {
    if (!activeDocumentsMenu) {
        return;
    }

    activeDocumentsMenu.dropdown.hidden = true;
    activeDocumentsMenu.trigger.setAttribute('aria-expanded', 'false');
    activeDocumentsMenu = null;
}

document.addEventListener('click', (event) => {
    const trigger = event.target.closest('.documents-menu-trigger');

    if (trigger) {
        const dropdown = trigger
            .closest('.documents-menu')
            ?.querySelector('.documents-menu-dropdown');

        if (!dropdown) {
            return;
        }

        const isCurrentMenu = activeDocumentsMenu?.trigger === trigger;
        closeDocumentsMenu();

        if (!isCurrentMenu) {
            dropdown.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            activeDocumentsMenu = { trigger, dropdown };
        }

        if (activeRowActionsMenu) {
            positionRowActionsMenu(
                activeRowActionsMenu.trigger,
                activeRowActionsMenu.dropdown
            );
        }

        return;
    }

    if (event.target.closest('.documents-menu-dropdown a')) {
        closeDocumentsMenu();
        return;
    }

    if (!event.target.closest('.documents-menu')) {
        closeDocumentsMenu();
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeDocumentsMenu();
        closeRowActionsMenu();
    }
});

window.addEventListener('resize', closeRowActionsMenu);
window.addEventListener('scroll', closeRowActionsMenu, true);
