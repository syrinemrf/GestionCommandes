document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('supplier-dashboard');
    if (!root || typeof echarts === 'undefined') {
        return;
    }

    const elements = {
        from: document.getElementById('dashboard-date-from'),
        to: document.getElementById('dashboard-date-to'),
        apply: document.getElementById('dashboard-apply-filters'),
        refresh: document.getElementById('dashboard-refresh'),
        refreshInterval: document.getElementById('dashboard-refresh-interval'),
        feedback: document.getElementById('dashboard-feedback'),
        loader: document.getElementById('dashboard-loader'),
        lastUpdated: document.getElementById('dashboard-last-updated'),
        stockBody: document.getElementById('dashboard-stock-body'),
    };

    const endpoints = {
        summary: root.dataset.summaryUrl,
        evolution: root.dataset.evolutionUrl,
        products: root.dataset.productsUrl,
        statuses: root.dataset.statusesUrl,
        stock: root.dataset.stockUrl,
    };

    const charts = {};
    let activeController = null;
    let refreshTimer = null;
    let lastSnapshot = null;

    const numberFormatter = new Intl.NumberFormat('fr-FR');
    const moneyFormatter = new Intl.NumberFormat('fr-TN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });
    const percentFormatter = new Intl.NumberFormat('fr-FR', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });

    const statusLabels = {
        EN_ATTENTE_CONFIRMATION: 'En attente de confirmation',
        EN_PREPARATION: 'En préparation',
        PRETE: 'Prête',
        EXPEDIEE: 'Expédiée',
        EN_LIVRAISON: 'En livraison',
        LIVREE: 'Livrée',
        ANNULEE: 'Annulée',
    };

    const statusColors = {
        EN_ATTENTE_CONFIRMATION: '#b45309',
        EN_PREPARATION: '#1d4ed8',
        PRETE: '#7c3aed',
        EXPEDIEE: '#0e7490',
        EN_LIVRAISON: '#0369a1',
        LIVREE: '#047857',
        ANNULEE: '#b91c1c',
    };

    function isoDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }

    function selectPeriod(days) {
        const end = new Date();
        const start = new Date(end);
        start.setDate(start.getDate() - (days - 1));
        elements.from.value = isoDate(start);
        elements.to.value = isoDate(end);

        document.querySelectorAll('.dashboard-period').forEach((button) => {
            button.classList.toggle('is-active', Number(button.dataset.periodDays) === days);
        });
    }

    function buildUrl(url, params = {}) {
        const target = new URL(url, window.location.origin);
        Object.entries(params).forEach(([key, value]) => target.searchParams.set(key, value));
        return target.toString();
    }

    async function requestJson(url, signal) {
        const response = await fetch(url, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            signal,
        });

        let payload = null;
        try {
            payload = await response.json();
        } catch (error) {
            throw new Error('Le serveur a renvoyé une réponse illisible.');
        }

        if (!response.ok) {
            throw new Error(payload?.error?.message || 'Impossible de charger les statistiques.');
        }

        return payload;
    }

    function setFeedback(message = '', retry = false) {
        elements.feedback.replaceChildren();
        elements.feedback.hidden = !message;
        if (!message) return;

        elements.feedback.append(document.createTextNode(message));
        if (retry) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = 'Réessayer';
            button.addEventListener('click', () => loadDashboard());
            elements.feedback.append(button);
        }
    }

    function setLoading(loading) {
        elements.loader.hidden = !loading || lastSnapshot !== null;
        elements.refresh.classList.toggle('is-loading', loading);
        elements.refresh.disabled = loading;
        elements.apply.disabled = loading;

        if (loading && lastSnapshot) {
            setFeedback('Actualisation en cours. Le dernier snapshot valide reste affiché.');
        }
    }

    function ensureChart(key, elementId) {
        const element = document.getElementById(elementId);
        if (!charts[key] || charts[key].isDisposed()) {
            charts[key] = echarts.init(element, null, { renderer: 'canvas' });
        }
        return charts[key];
    }

    function toggleEmpty(key, isEmpty, chartKey = key) {
        const empty = document.querySelector(`[data-empty-for="${key}"]`);
        const chart = charts[chartKey];
        if (empty) empty.hidden = !isEmpty;
        if (chart) chart.getDom().hidden = isEmpty;
    }

    function renderSummary(data) {
        document.getElementById('kpi-orders').textContent = numberFormatter.format(data.orderCount || 0);
        document.getElementById('kpi-revenue-ht').textContent = `${moneyFormatter.format(data.revenueHt || 0)} TND`;
        document.getElementById('kpi-revenue-ttc').textContent = `${moneyFormatter.format(data.revenueTtc || 0)} TND`;
        document.getElementById('kpi-average-basket').textContent = `${moneyFormatter.format(data.averageOrderValueHt || 0)} TND`;
        document.getElementById('kpi-cancellation-rate').textContent = `${percentFormatter.format((data.cancellationRate || 0) * 100)} %`;
    }

    function renderEvolution(items) {
        const chart = ensureChart('evolution', 'dashboard-evolution-chart');
        toggleEmpty('evolution', items.length === 0);
        if (!items.length) {
            chart.clear();
            return;
        }

        chart.setOption({
            animationDuration: 350,
            color: ['#047857', '#2563eb'],
            tooltip: {
                trigger: 'axis',
                backgroundColor: '#0f172a',
                borderWidth: 0,
                textStyle: { color: '#fff' },
                valueFormatter: (value) => numberFormatter.format(value),
            },
            legend: { top: 2, data: ['Commandes', 'CA HT'] },
            grid: { left: 48, right: 66, top: 48, bottom: 42 },
            xAxis: {
                type: 'category',
                boundaryGap: false,
                data: items.map((item) => item.date),
                axisLabel: { color: '#64748b', hideOverlap: true },
            },
            yAxis: [
                { type: 'value', name: 'Commandes', minInterval: 1, axisLabel: { color: '#64748b' } },
                {
                    type: 'value',
                    name: 'TND',
                    axisLabel: { color: '#64748b', formatter: (value) => numberFormatter.format(value) },
                },
            ],
            series: [
                {
                    name: 'Commandes',
                    type: 'line',
                    smooth: true,
                    showSymbol: false,
                    areaStyle: { opacity: .08 },
                    data: items.map((item) => item.orderCount),
                },
                {
                    name: 'CA HT',
                    type: 'line',
                    yAxisIndex: 1,
                    smooth: true,
                    showSymbol: false,
                    data: items.map((item) => item.revenueHt),
                },
            ],
        }, { notMerge: true });
    }

    function renderStatuses(items) {
        const chart = ensureChart('statuses', 'dashboard-status-chart');
        const values = items.filter((item) => item.currentOrderCount > 0);
        toggleEmpty('statuses', values.length === 0);
        if (!values.length) {
            chart.clear();
            return;
        }

        chart.setOption({
            color: values.map((item) => statusColors[item.status] || '#64748b'),
            tooltip: {
                trigger: 'item',
                backgroundColor: '#0f172a',
                borderWidth: 0,
                textStyle: { color: '#fff' },
                formatter: (item) => `${item.name}<br><strong>${numberFormatter.format(item.value)}</strong> (${item.percent} %)`
            },
            legend: {
                type: 'scroll',
                bottom: 0,
                textStyle: { color: '#475569' },
            },
            series: [{
                type: 'pie',
                radius: ['44%', '68%'],
                center: ['50%', '44%'],
                label: { show: false },
                emphasis: { label: { show: true, fontWeight: 700 } },
                data: values.map((item) => ({
                    name: statusLabels[item.status] || item.status,
                    value: item.currentOrderCount,
                })),
            }],
        }, { notMerge: true });
    }

    function aggregateProducts(items) {
        const products = new Map();
        items.forEach((item) => {
            const key = `${item.productId}:${item.variationId}`;
            const variation = item.variationName && item.variationName.toLowerCase() !== 'standard'
                ? ` - ${item.variationName}`
                : '';
            const current = products.get(key) || {
                label: `${item.productName}${variation}`,
                units: 0,
                revenue: 0,
            };
            current.units += item.unitsSold;
            current.revenue += item.revenueHt;
            products.set(key, current);
        });

        return Array.from(products.values())
            .sort((left, right) => right.units - left.units || right.revenue - left.revenue)
            .slice(0, 10)
            .reverse();
    }

    function renderProducts(items) {
        const values = aggregateProducts(items);
        const chart = ensureChart('products', 'dashboard-products-chart');
        toggleEmpty('products', values.length === 0);
        if (!values.length) {
            chart.clear();
            return;
        }

        chart.setOption({
            color: ['#047857'],
            tooltip: {
                trigger: 'axis',
                axisPointer: { type: 'shadow' },
                backgroundColor: '#0f172a',
                borderWidth: 0,
                textStyle: { color: '#fff' },
                formatter: (params) => {
                    const index = params[0].dataIndex;
                    return `${values[index].label}<br><strong>${numberFormatter.format(values[index].units)} unités</strong><br>${moneyFormatter.format(values[index].revenue)} TND HT`;
                },
            },
            grid: { left: 12, right: 28, top: 12, bottom: 16, containLabel: true },
            xAxis: { type: 'value', minInterval: 1, axisLabel: { color: '#64748b' } },
            yAxis: {
                type: 'category',
                data: values.map((item) => item.label),
                axisLabel: {
                    color: '#475569',
                    width: 150,
                    overflow: 'truncate',
                },
            },
            series: [{
                name: 'Unités vendues',
                type: 'bar',
                barMaxWidth: 22,
                data: values.map((item) => item.units),
                itemStyle: { borderRadius: [0, 5, 5, 0] },
            }],
        }, { notMerge: true });
    }

    function stockState(item) {
        if (item.currentlyOutOfStock || item.stockAvailable <= 0) {
            return { label: 'Rupture', className: 'is-out' };
        }
        if (item.stockAvailable <= 5) {
            return { label: 'Stock faible', className: 'is-low' };
        }
        return { label: 'Disponible', className: '' };
    }

    function renderStock(items) {
        elements.stockBody.replaceChildren();
        const activeItems = items
            .filter((item) => !item.productDeleted && !item.variationDeleted)
            .sort((left, right) => left.stockAvailable - right.stockAvailable)
            .slice(0, 12);
        const empty = activeItems.length === 0;
        document.querySelector('[data-empty-for="stock"]').hidden = !empty;
        document.querySelector('.dashboard-stock-wrapper').hidden = empty;

        activeItems.forEach((item) => {
            const state = stockState(item);
            const row = document.createElement('tr');
            const product = document.createElement('td');
            product.className = 'dashboard-stock-name';
            const name = document.createElement('strong');
            name.textContent = item.productName;
            product.append(name);
            if (item.variationName && item.variationName.toLowerCase() !== 'standard') {
                const variation = document.createElement('span');
                variation.textContent = item.variationName;
                product.append(variation);
            }

            const available = document.createElement('td');
            available.className = `dashboard-stock-value ${state.className}`.trim();
            available.textContent = numberFormatter.format(item.stockAvailable);

            const reserved = document.createElement('td');
            reserved.textContent = numberFormatter.format(item.stockReserved);

            const status = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = `dashboard-stock-badge ${state.className}`.trim();
            badge.textContent = state.label;
            status.append(badge);

            row.append(product, available, reserved, status);
            elements.stockBody.append(row);
        });
    }

    function snapshotTimestamps(snapshot) {
        const timestamps = [snapshot.summary?.lastUpdatedAt];
        ['products', 'statuses', 'stock'].forEach((key) => {
            snapshot[key].forEach((item) => timestamps.push(item.lastUpdatedAt));
        });

        return timestamps
            .filter(Boolean)
            .map((value) => new Date(value))
            .filter((value) => !Number.isNaN(value.getTime()));
    }

    function isCoherentSnapshot(snapshot) {
        const timestamps = snapshotTimestamps(snapshot);
        if (timestamps.length < 2) return true;

        const values = timestamps.map((value) => value.getTime());
        return Math.max(...values) - Math.min(...values) <= 30 * 60 * 1000;
    }

    function extractLastUpdated(snapshot) {
        const valid = snapshotTimestamps(snapshot);

        return valid.length
            ? new Date(Math.max(...valid.map((value) => value.getTime())))
            : new Date();
    }

    function renderSnapshot(snapshot) {
        renderSummary(snapshot.summary);
        renderEvolution(snapshot.evolution);
        renderProducts(snapshot.products);
        renderStatuses(snapshot.statuses);
        renderStock(snapshot.stock);

        elements.lastUpdated.textContent = extractLastUpdated(snapshot).toLocaleString('fr-FR', {
            dateStyle: 'medium',
            timeStyle: 'short',
        });
    }

    async function loadDashboard() {
        if (!elements.from.value || !elements.to.value) return;
        if (elements.from.value > elements.to.value) {
            setFeedback('La date de début doit précéder la date de fin.', false);
            return;
        }

        if (activeController) activeController.abort();
        activeController = new AbortController();
        const controller = activeController;
        setLoading(true);

        const period = { from: elements.from.value, to: elements.to.value };
        try {
            const [summary, evolution, products, statuses, stock] = await Promise.all([
                requestJson(buildUrl(endpoints.summary, period), controller.signal),
                requestJson(buildUrl(endpoints.evolution, period), controller.signal),
                requestJson(buildUrl(endpoints.products, { ...period, limit: 100 }), controller.signal),
                requestJson(endpoints.statuses, controller.signal),
                requestJson(buildUrl(endpoints.stock, { limit: 100 }), controller.signal),
            ]);

            if (controller !== activeController) return;
            const snapshot = {
                summary: summary.data,
                evolution: evolution.data,
                products: products.data,
                statuses: statuses.data,
                stock: stock.data,
            };

            if (!isCoherentSnapshot(snapshot)) {
                throw new Error('Les marts sont en cours de reconstruction.');
            }

            renderSnapshot(snapshot);
            lastSnapshot = snapshot;
            setFeedback('');
        } catch (error) {
            if (error.name === 'AbortError') return;
            const prefix = lastSnapshot
                ? 'Actualisation impossible. Le dernier snapshot valide reste affiché.'
                : 'Impossible de charger le tableau de bord.';
            setFeedback(`${prefix} ${error.message}`, true);
        } finally {
            if (controller === activeController) {
                setLoading(false);
                activeController = null;
            }
        }
    }

    function configureAutoRefresh() {
        if (refreshTimer) window.clearInterval(refreshTimer);
        const seconds = Number(elements.refreshInterval.value);
        try {
            window.localStorage.setItem('comdelyDashboardRefreshSeconds', String(seconds));
        } catch (error) {
            // Storage can be disabled without affecting the dashboard.
        }
        if (seconds > 0) {
            refreshTimer = window.setInterval(loadDashboard, seconds * 1000);
        }
    }

    document.querySelectorAll('.dashboard-period').forEach((button) => {
        button.addEventListener('click', () => {
            selectPeriod(Number(button.dataset.periodDays));
            loadDashboard();
        });
    });
    elements.apply.addEventListener('click', loadDashboard);
    elements.refresh.addEventListener('click', loadDashboard);
    elements.refreshInterval.addEventListener('change', configureAutoRefresh);

    let resizeFrame = null;
    window.addEventListener('resize', () => {
        if (resizeFrame) window.cancelAnimationFrame(resizeFrame);
        resizeFrame = window.requestAnimationFrame(() => {
            Object.values(charts).forEach((chart) => {
                if (!chart.isDisposed()) chart.resize();
            });
        });
    });

    window.addEventListener('pagehide', () => {
        if (activeController) activeController.abort();
        if (refreshTimer) window.clearInterval(refreshTimer);
        Object.values(charts).forEach((chart) => {
            if (!chart.isDisposed()) chart.dispose();
        });
    });

    try {
        const savedInterval = window.localStorage.getItem('comdelyDashboardRefreshSeconds');
        if (savedInterval && elements.refreshInterval.querySelector(`option[value="${savedInterval}"]`)) {
            elements.refreshInterval.value = savedInterval;
        }
    } catch (error) {
        // Use the default interval when storage is unavailable.
    }

    selectPeriod(90);
    configureAutoRefresh();
    loadDashboard();
});
