document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('supplier-dashboard');
    if (!root || typeof echarts === 'undefined' || !window.ComdelyDashboard) return;
    const ui = window.ComdelyDashboard;
    const elements = {
        from: document.getElementById('dashboard-date-from'), to: document.getElementById('dashboard-date-to'),
        apply: document.getElementById('dashboard-apply-filters'), refresh: document.getElementById('dashboard-refresh'),
        refreshInterval: document.getElementById('dashboard-refresh-interval'), feedback: document.getElementById('dashboard-feedback'),
        lastUpdated: document.getElementById('dashboard-last-updated'),
        statuses: document.getElementById('dashboard-status-list'),
        actionTotal: document.getElementById('dashboard-action-total'),
        transitTotal: document.getElementById('dashboard-transit-total'),
        products: document.getElementById('dashboard-top-products'),
        alerts: document.getElementById('dashboard-alerts'),
    };
    const endpoints = {
        overview: root.dataset.overviewUrl,
        evolution: root.dataset.evolutionUrl,
        products: root.dataset.productsUrl,
        statuses: root.dataset.statusesUrl,
        stock: root.dataset.stockUrl,
        risks: root.dataset.risksUrl,
        orders: root.dataset.ordersUrl,
    };
    const controllers = new Map();
    let refreshTimer = null;
    let productMode = 'revenue';
    const snapshot = {};
    let alertPending = new Set();
    const alertErrors = new Map();
    const chart = echarts.init(document.getElementById('dashboard-evolution-chart'));
    const actionableStatuses = {
        EN_ATTENTE_CONFIRMATION: 'À confirmer',
        EN_PREPARATION: 'En préparation',
        PRETE: 'Prêtes à expédier',
    };

    function comparison(elementId, value, { inverse = false, suffix = '%' } = {}) {
        const element = document.getElementById(elementId);
        element.className = 'dashboard-kpi-comparison is-neutral';
        if (value === null || value === undefined) {
            element.textContent = 'Nouvelle activité, sans référence';
            return;
        }
        const amount = Number(value) || 0;
        const good = inverse ? amount < 0 : amount > 0;
        const bad = inverse ? amount > 0 : amount < 0;
        if (good) element.classList.add('is-positive');
        if (bad) element.classList.add('is-negative');
        const sign = amount > 0 ? '+' : amount < 0 ? '−' : '';
        element.textContent = amount === 0 ? 'Stable vs période précédente' : `${sign}${ui.number.format(Math.abs(amount))} ${suffix} vs période précédente`;
    }

    function renderKpis(data) {
        const current = data.current;
        const orders = document.getElementById('kpi-orders');
        orders.textContent = ui.integer.format(current.orderCount);
        const revenue = document.getElementById('kpi-revenue');
        revenue.textContent = ui.money(current.revenueHt, revenue);
        const basket = document.getElementById('kpi-average-basket');
        basket.textContent = ui.money(current.averageOrderValueHt, basket);
        document.getElementById('kpi-cancellation').textContent = `${ui.number.format(current.cancellationRate * 100)} %`;
        const processingValue = document.getElementById('kpi-processing');
        processingValue.textContent = ui.duration(current.averageProcessingSeconds);
        processingValue.title = [
            `Médiane : ${ui.duration(current.medianProcessingSeconds)}`,
            `Préparation → prête : ${ui.duration(current.preparationToReadySeconds)}`,
            `Prête → expédiée : ${ui.duration(current.readyToShippedSeconds)}`,
        ].join('\n');
        comparison('kpi-orders-comparison', data.comparison.orderCountPercent);
        comparison('kpi-revenue-comparison', data.comparison.revenueHtPercent);
        comparison('kpi-average-basket-comparison', data.comparison.averageOrderValueHtPercent);
        comparison('kpi-cancellation-comparison', data.comparison.cancellationRatePoints, { inverse: true, suffix: 'pt' });
        const processing = document.getElementById('kpi-processing-comparison');
        const delta = data.comparison.processingTimeSecondsDelta;
        processing.className = 'dashboard-kpi-comparison is-neutral';
        if (delta === null) processing.textContent = 'Comparaison indisponible';
        else if (Math.abs(delta) < 1800) processing.textContent = 'Stable vs période précédente';
        else {
            processing.textContent = `${delta > 0 ? 'Plus long' : 'Plus court'} de ${ui.duration(Math.abs(delta))}`;
            processing.classList.add(delta > 0 ? 'is-negative' : 'is-positive');
        }
    }

    function periodKey(dateValue, mode) {
        const date = new Date(`${dateValue}T00:00:00Z`);
        if (mode === 'monthly') return `${date.getUTCFullYear()}-${String(date.getUTCMonth() + 1).padStart(2, '0')}-01`;
        if (mode === 'weekly') {
            const monday = new Date(date);
            const day = monday.getUTCDay() || 7;
            monday.setUTCDate(monday.getUTCDate() - day + 1);
            return monday.toISOString().slice(0, 10);
        }
        return dateValue;
    }

    function aggregateEvolution(items) {
        const days = Math.round((new Date(elements.to.value) - new Date(elements.from.value)) / 86400000) + 1;
        const mode = days <= 45 ? 'daily' : days <= 180 ? 'weekly' : 'monthly';
        const groups = new Map();
        items.forEach((item) => {
            const key = periodKey(item.date, mode);
            const value = groups.get(key) || { date: key, orders: 0, revenue: 0 };
            value.orders += Number(item.orderCount) || 0;
            value.revenue += Number(item.revenueHt) || 0;
            groups.set(key, value);
        });
        return { mode, values: Array.from(groups.values()).sort((a, b) => a.date.localeCompare(b.date)) };
    }

    function renderEvolution(items) {
        const aggregate = aggregateEvolution(items);
        const empty = aggregate.values.length === 0 || aggregate.values.every((item) => item.orders === 0 && item.revenue === 0);
        document.querySelector('[data-empty-for="evolution"]').hidden = !empty;
        chart.getDom().hidden = empty;
        document.getElementById('dashboard-evolution-subtitle').textContent = `Commandes et CA HT - vue ${aggregate.mode === 'daily' ? 'quotidienne' : aggregate.mode === 'weekly' ? 'hebdomadaire' : 'mensuelle'}`;
        if (empty) return chart.clear();
        chart.setOption({ color: ['#047857', '#2563eb'], tooltip: { trigger: 'axis', backgroundColor: '#0f172a', borderWidth: 0, textStyle: { color: '#fff' } }, legend: { top: 0, data: ['Commandes', 'CA HT'] }, grid: { left: 52, right: 70, top: 46, bottom: 36 }, xAxis: { type: 'category', boundaryGap: false, data: aggregate.values.map((item) => item.date), axisLabel: { color: '#64748b', hideOverlap: true } }, yAxis: [{ type: 'value', minInterval: 1, axisLabel: { color: '#64748b' } }, { type: 'value', axisLabel: { color: '#64748b', formatter: (value) => ui.money(value).replace(' TND', '') } }], series: [{ name: 'Commandes', type: 'line', smooth: true, showSymbol: false, areaStyle: { opacity: .08 }, data: aggregate.values.map((item) => item.orders) }, { name: 'CA HT', type: 'line', yAxisIndex: 1, smooth: true, showSymbol: false, data: aggregate.values.map((item) => item.revenue) }] }, { notMerge: true });
    }

    function renderStatuses(items) {
        elements.statuses.replaceChildren();
        const counts = new Map(
            items.map((item) => [item.status, Number(item.currentOrderCount) || 0])
        );
        const actionable = Object.entries(actionableStatuses)
            .map(([status, label]) => ({ status, label, count: counts.get(status) || 0 }))
            .filter((item) => item.count > 0);
        const total = actionable.reduce((sum, item) => sum + item.count, 0);
        const transit = (counts.get('EXPEDIEE') || 0) + (counts.get('EN_LIVRAISON') || 0);

        elements.actionTotal.textContent = total === 0
            ? 'Aucune commande ne nécessite votre attention'
            : total === 1
                ? '1 commande nécessite votre attention'
                : `${ui.integer.format(total)} commandes nécessitent votre attention`;
        elements.actionTotal.hidden = false;
        elements.statuses.hidden = total === 0;
        document.querySelector('[data-empty-for="statuses"]').hidden = true;

        actionable.forEach((item) => {
            const row = document.createElement('a');
            row.href = ui.buildUrl(endpoints.orders, { statut: item.status });
            row.className = 'dashboard-action-row';

            const count = document.createElement('strong');
            count.textContent = ui.integer.format(item.count);
            const label = document.createElement('span');
            label.textContent = item.label;
            const action = document.createElement('small');
            action.textContent = 'Voir →';
            row.append(count, label, action);
            elements.statuses.append(row);
        });

        elements.transitTotal.hidden = transit === 0;
        elements.transitTotal.textContent = transit === 1
            ? '1 commande en cours d’acheminement'
            : `${ui.integer.format(transit)} commandes en cours d’acheminement`;
    }

    function rankedProducts(items) {
        if (productMode === 'declining') {
            return items
                .filter((item) => item.revenueChangePercent !== null && item.revenueChangePercent < 0)
                .sort((a, b) => a.revenueChangePercent - b.revenueChangePercent)
                .slice(0, 5);
        }

        return items
            .filter((item) => productMode === 'units' ? item.currentUnitsSold > 0 : item.currentRevenueHt > 0)
            .sort((a, b) => productMode === 'units'
                ? b.currentUnitsSold - a.currentUnitsSold || b.currentRevenueHt - a.currentRevenueHt
                : b.currentRevenueHt - a.currentRevenueHt || b.currentUnitsSold - a.currentUnitsSold)
            .slice(0, 5);
    }

    function renderProducts(items) {
        elements.products.replaceChildren();
        const products = rankedProducts(items);
        document.querySelector('[data-empty-for="products"]').hidden = products.length > 0;

        products.forEach((item) => {
            const row = document.createElement('li');
            const content = document.createElement('div');
            content.className = 'dashboard-performance-product';
            const name = document.createElement('strong');
            name.textContent = item.productName;
            content.append(name);
            const revenue = document.createElement('span');
            revenue.textContent = ui.money(item.currentRevenueHt, revenue);
            const units = document.createElement('span');
            units.textContent = ui.integer.format(item.currentUnitsSold);
            const change = productMode === 'units' ? item.unitsChangePercent : item.revenueChangePercent;
            const evolution = document.createElement('span');
            evolution.className = 'dashboard-performance-change';
            if (change === null) {
                evolution.textContent = 'Nouveau';
            } else {
                const amount = Number(change) || 0;
                evolution.textContent = amount === 0 ? 'Stable' : `${amount > 0 ? '+' : ''}${ui.number.format(amount)} %`;
                if (amount > 0) evolution.classList.add('is-positive');
                if (amount < 0) evolution.classList.add('is-negative');
            }
            row.append(content, revenue, units, evolution);
            elements.products.append(row);
        });
    }

    function alertItem(message, detail, level, href) {
        return { message, detail, level, href };
    }

    function renderAlerts(data) {
        const alerts = [];
        const activeStock = data.stock.filter((item) => !item.productDeleted && !item.variationDeleted);
        const out = activeStock.filter((item) => item.currentlyOutOfStock || item.stockAvailable <= 0).length;
        const high = data.risks.filter(
            (item) => item.risk === 'HIGH' && item.stockAvailable > 0
        ).length;
        if (out) alerts.push(alertItem(`${out} rupture${out > 1 ? 's' : ''} actuelle${out > 1 ? 's' : ''}`, 'Stock disponible nul', 'danger', '/dashboard/products'));
        if (high) alerts.push(alertItem(
            `${high} variation${high > 1 ? 's' : ''} à risque élevé de rupture`,
            'Stock insuffisant face à la demande prévue sur 7 jours',
            'danger',
            '/dashboard/products'
        ));
        if ((data.overview.comparison.revenueHtPercent ?? 0) < 0) alerts.push(alertItem('Chiffre d’affaires en baisse', `${ui.number.format(Math.abs(data.overview.comparison.revenueHtPercent))} % vs période précédente`, 'warning', null));
        if ((data.overview.comparison.cancellationRatePoints ?? 0) > 0) alerts.push(alertItem('Taux d’annulation en hausse', `+${ui.number.format(data.overview.comparison.cancellationRatePoints)} point`, 'warning', null));
        if ((data.overview.comparison.processingTimeSecondsDelta ?? 0) > 1800) alerts.push(alertItem('Traitement plus lent', `+${ui.duration(data.overview.comparison.processingTimeSecondsDelta)}`, 'warning', null));
        elements.alerts.replaceChildren(); document.querySelector('[data-empty-for="alerts"]').hidden = alerts.length > 0;
        alerts.slice(0, 5).forEach((item) => { const row = document.createElement('li'); row.className = `is-${item.level}`; const content = document.createElement('div'); const strong = document.createElement('strong'); strong.textContent = item.message; const small = document.createElement('small'); small.textContent = item.detail; content.append(strong, small); row.append(content); if (item.href) { const link = document.createElement('a'); link.href = item.href; link.textContent = 'Voir'; row.append(link); } elements.alerts.append(row); });
    }

    function updateLastUpdated() {
        const updated = ui.latestTimestamp([
            snapshot.overview?.lastUpdatedAt,
            snapshot.products?.map((item) => item.lastUpdatedAt) || [],
            snapshot.statuses?.map((item) => item.lastUpdatedAt) || [],
            snapshot.stock?.map((item) => item.lastUpdatedAt) || [],
            snapshot.risks?.map((item) => item.lastUpdatedAt) || [],
        ]);
        elements.lastUpdated.textContent = updated ? updated.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) : 'Aucune donnée actualisée';
    }

    function setSectionLoading(section, loading, hasContent = false) {
        const container = document.querySelector(`[data-dashboard-section="${section}"]`);
        const indicator = document.querySelector(`[data-loading-for="${section}"]`);
        container?.classList.toggle('is-loading', loading && !hasContent);
        if (indicator) indicator.hidden = !loading || hasContent;
    }

    function setSectionError(section, message = '', retry = null) {
        const target = document.querySelector(`[data-error-for="${section}"]`);
        if (!target) return;
        target.replaceChildren(); target.hidden = !message;
        if (!message) return;
        target.append(document.createTextNode(message));
        if (retry) { const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Réessayer'; button.addEventListener('click', retry); target.append(button); }
    }

    function updateRequestState() {
        elements.refresh.classList.toggle('is-loading', controllers.size > 0);
    }

    function finishAlertPart(key) {
        alertPending.delete(key);
        if (alertPending.size > 0) return;
        setSectionLoading('alerts', false);
        if (alertErrors.size === 0 && snapshot.overview && snapshot.stock && snapshot.risks) {
            renderAlerts(snapshot);
            setSectionError('alerts');
            return;
        }
        setSectionError('alerts', 'Certaines données nécessaires aux alertes sont indisponibles.', load);
    }

    async function loadPart(key, section, url, renderPart) {
        controllers.get(key)?.abort();
        const partController = new AbortController(); controllers.set(key, partController); updateRequestState();
        if (section) { setSectionLoading(section, true, Object.hasOwn(snapshot, key)); setSectionError(section); }
        try {
            const response = await ui.requestJson(url, partController.signal);
            if (controllers.get(key) !== partController) return;
            snapshot[key] = response.data; renderPart?.(response.data); updateLastUpdated();
            alertErrors.delete(key);
        } catch (error) {
            if (error.name !== 'AbortError' && controllers.get(key) === partController) {
                if (section) setSectionError(section, `Chargement impossible. ${error.message}`, () => loadPart(key, section, url, renderPart));
                if (['overview', 'stock', 'risks'].includes(key)) alertErrors.set(key, error.message);
            }
        } finally {
            if (controllers.get(key) === partController) { controllers.delete(key); if (section) setSectionLoading(section, false); if (['overview', 'stock', 'risks'].includes(key)) finishAlertPart(key); updateRequestState(); }
        }
    }

    function load() {
        if (!elements.from.value || !elements.to.value || elements.from.value > elements.to.value) return ui.setFeedback(elements.feedback, 'La période sélectionnée est invalide.');
        controllers.forEach((item) => item.abort()); controllers.clear(); ui.setFeedback(elements.feedback);
        const period = { from: elements.from.value, to: elements.to.value };
        alertPending = new Set(['overview', 'stock', 'risks']); alertErrors.clear(); setSectionLoading('alerts', true, elements.alerts.children.length > 0); setSectionError('alerts');
        loadPart('overview', 'overview', ui.buildUrl(endpoints.overview, period), renderKpis);
        loadPart('evolution', 'evolution', ui.buildUrl(endpoints.evolution, period), renderEvolution);
        loadPart('products', 'products', ui.buildUrl(endpoints.products, { ...period, limit: 100 }), renderProducts);
        loadPart('statuses', 'statuses', endpoints.statuses, renderStatuses);
        loadPart('stock', null, ui.buildUrl(endpoints.stock, { limit: 200 }));
        loadPart('risks', null, ui.buildUrl(endpoints.risks, { limit: 200 }));
    }

    function configureRefresh() {
        if (refreshTimer) clearInterval(refreshTimer);
        const seconds = Number(elements.refreshInterval.value);
        try {
            localStorage.setItem('comdelyDashboardRefreshSeconds', String(seconds));
        } catch (_) {
            // Le stockage local est facultatif (navigation privee, politique navigateur).
        }
        if (seconds > 0) refreshTimer = setInterval(load, seconds * 1000);
    }
    document.querySelectorAll('.dashboard-period').forEach((button) => button.addEventListener('click', () => { ui.selectPeriod(elements.from, elements.to, Number(button.dataset.periodDays)); load(); }));
    document.querySelectorAll('[data-product-mode]').forEach((button) => button.addEventListener('click', () => {
        productMode = button.dataset.productMode;
        document.querySelectorAll('[data-product-mode]').forEach((item) => {
            const active = item === button;
            item.classList.toggle('is-active', active);
            item.setAttribute('aria-selected', String(active));
        });
        renderProducts(snapshot.products || []);
    }));
    elements.apply.addEventListener('click', load); elements.refresh.addEventListener('click', load); elements.refreshInterval.addEventListener('change', configureRefresh);
    window.addEventListener('resize', () => chart.resize()); window.addEventListener('pagehide', () => { controllers.forEach((item) => item.abort()); if (refreshTimer) clearInterval(refreshTimer); chart.dispose(); });
    ui.selectPeriod(elements.from, elements.to, 90);
    let saved = null;
    try {
        saved = localStorage.getItem('comdelyDashboardRefreshSeconds');
    } catch (_) {
        // Le dashboard reste fonctionnel sans stockage local.
    }
    if (saved && elements.refreshInterval.querySelector(`option[value="${saved}"]`)) {
        elements.refreshInterval.value = saved;
    }
    configureRefresh();
    load();
});
