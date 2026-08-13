window.ComdelyDashboard = (() => {
    const number = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 });
    const integer = new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 0 });
    const exactMoney = new Intl.NumberFormat('fr-TN', { minimumFractionDigits: 3, maximumFractionDigits: 3 });
    const compactMoney = new Intl.NumberFormat('fr-FR', { notation: 'compact', maximumFractionDigits: 1 });

    function buildUrl(url, params = {}) {
        const target = new URL(url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => {
            if (value !== '' && value !== null && value !== undefined) target.searchParams.set(key, value);
        });
        return target.toString();
    }

    async function requestJson(url, signal) {
        const response = await fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
        let payload;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Le serveur a renvoyé une réponse illisible.');
        }
        if (!response.ok) throw new Error(payload?.error?.message || 'Impossible de charger les statistiques.');
        return payload;
    }

    function isoDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function selectPeriod(from, to, days) {
        const end = new Date();
        const start = new Date(end);
        start.setDate(start.getDate() - (days - 1));
        from.value = isoDate(start);
        to.value = isoDate(end);
        document.querySelectorAll('.dashboard-period').forEach((button) => button.classList.toggle('is-active', Number(button.dataset.periodDays) === days));
    }

    function money(value, element = null) {
        const amount = Number(value) || 0;
        if (element) element.title = `${exactMoney.format(amount)} TND`;
        return `${compactMoney.format(amount)} TND`;
    }

    function duration(seconds) {
        if (seconds === null || seconds === undefined || Number.isNaN(Number(seconds))) return 'Non disponible';
        const totalHours = Math.max(0, Math.round(Number(seconds) / 3600));
        const days = Math.floor(totalHours / 24);
        const hours = totalHours % 24;
        return days > 0 ? `${days} j ${hours} h` : `${hours} h`;
    }

    function productLabel(item) {
        return item.variationName && item.variationName.toLowerCase() !== 'standard'
            ? `${item.productName} - ${item.variationName}` : item.productName;
    }

    function setFeedback(element, message = '', retry = null) {
        element.replaceChildren();
        element.hidden = !message;
        if (!message) return;
        element.append(document.createTextNode(message));
        if (retry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Réessayer';
            button.addEventListener('click', retry);
            element.append(button);
        }
    }

    function latestTimestamp(values) {
        const dates = values.flat(Infinity).filter(Boolean).map((value) => new Date(value)).filter((value) => !Number.isNaN(value.getTime()));
        return dates.length ? new Date(Math.max(...dates.map((date) => date.getTime()))) : null;
    }

    return { buildUrl, requestJson, isoDate, selectPeriod, money, duration, productLabel, setFeedback, latestTimestamp, number, integer, exactMoney };
})();
