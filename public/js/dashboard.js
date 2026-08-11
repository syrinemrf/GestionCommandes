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
        riskBody: document.getElementById('dashboard-risk-body'),
        riskLevel: document.getElementById('dashboard-risk-level'),
        xaiDialog: document.getElementById('dashboard-xai-dialog'),
        xaiLoading: document.getElementById('dashboard-xai-loading'),
        xaiContent: document.getElementById('dashboard-xai-content'),
    };

    const endpoints = {
        summary: root.dataset.summaryUrl,
        evolution: root.dataset.evolutionUrl,
        products: root.dataset.productsUrl,
        statuses: root.dataset.statusesUrl,
        stock: root.dataset.stockUrl,
        risks: root.dataset.risksUrl,
        riskExplanation: root.dataset.riskExplanationUrl,
    };
    const hasRiskSection = Boolean(
        elements.riskBody
        && elements.riskLevel
        && elements.xaiDialog
        && endpoints.risks
        && endpoints.riskExplanation
    );

    const charts = {};
    let activeController = null;
    let explanationController = null;
    let refreshTimer = null;
    let lastSnapshot = null;

    const numberFormatter = new Intl.NumberFormat('fr-FR');

    const moneyFormatter = new Intl.NumberFormat('fr-TN', {
        minimumFractionDigits: 3,
        maximumFractionDigits: 3,
    });

    const compactMoneyFormatter = new Intl.NumberFormat('fr-FR', {
        notation: 'compact',
        compactDisplay: 'short',
        maximumFractionDigits: 1,
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

    const riskLabels = {
        HIGH: 'Élevé',
        MEDIUM: 'Modéré',
        LOW: 'Faible',
        INSUFFICIENT_DATA: 'Données insuffisantes',
    };

    const featureLabels = {
        demand_lag_1: 'Demande la veille',
        demand_lag_7: 'Demande il y a 7 jours',
        demand_lag_14: 'Demande il y a 14 jours',
        demand_lag_28: 'Demande il y a 28 jours',
        rolling_mean_7d: 'Moyenne récente (7 j)',
        rolling_sum_7d: 'Cumul récent (7 j)',
        rolling_std_7d: 'Variabilité récente (7 j)',
        rolling_mean_14d: 'Moyenne récente (14 j)',
        rolling_sum_14d: 'Cumul récent (14 j)',
        rolling_std_14d: 'Variabilité récente (14 j)',
        rolling_mean_28d: 'Moyenne récente (28 j)',
        rolling_sum_28d: 'Cumul récent (28 j)',
        rolling_std_28d: 'Variabilité récente (28 j)',
        recent_trend_7d_vs_28d: 'Tendance récente',
        zero_demand_streak: 'Jours consécutifs sans vente',
        zero_demand_rate_28d: 'Fréquence des jours sans vente',
        nonzero_days_28d: 'Jours avec ventes',
        days_since_last_sale: 'Temps depuis la dernière vente',
        source_supplier_id: 'Profil fournisseur',
        source_product_id: 'Profil produit',
        source_variation_id: 'Profil variation',
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

    function previousPeriod(period) {
        const dayInMilliseconds = 24 * 60 * 60 * 1000;
        const from = new Date(`${period.from}T00:00:00Z`);
        const to = new Date(`${period.to}T00:00:00Z`);
        const durationDays = Math.round((to - from) / dayInMilliseconds) + 1;
        const previousTo = new Date(from);
        previousTo.setUTCDate(previousTo.getUTCDate() - 1);
        const previousFrom = new Date(previousTo);
        previousFrom.setUTCDate(previousFrom.getUTCDate() - (durationDays - 1));

        return {
            from: previousFrom.toISOString().slice(0, 10),
            to: previousTo.toISOString().slice(0, 10),
        };
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

    function setMoneyKpi(id, value, compact = false) {
        const element = document.getElementById(id);
        const amount = Number(value) || 0;

        element.textContent = compact
            ? `${compactMoneyFormatter.format(amount)} TND`
            : `${moneyFormatter.format(amount)} TND`;

        // Affiche la valeur exacte au survol
        element.title = `${moneyFormatter.format(amount)} TND`;
    }

    function setKpiComparison(id, valueId, text, state, period) {
        let element = document.getElementById(id);
        if (!element) {
            element = document.createElement('small');
            element.id = id;
            document.getElementById(valueId).insertAdjacentElement('afterend', element);
        }

        element.textContent = text;
        element.className = `dashboard-kpi-comparison ${state}`;
        element.title = `Période précédente : du ${period.from} au ${period.to}`;
    }

    function renderSummary(data, previousData, comparisonPeriod) {
        document.getElementById('kpi-orders').textContent =
            numberFormatter.format(data.orderCount || 0);

        setMoneyKpi(
            'kpi-revenue-ht',
            data.revenueHt,
            true
        );

        setMoneyKpi(
            'kpi-revenue-ttc',
            data.revenueTtc,
            true
        );

        setMoneyKpi(
            'kpi-average-basket',
            data.averageOrderValueHt
        );

        document.getElementById('kpi-cancellation-rate').textContent =
            `${percentFormatter.format((data.cancellationRate || 0) * 100)} %`;

        const currentBasket = Number(data.averageOrderValueHt) || 0;
        const previousBasket = Number(previousData.averageOrderValueHt) || 0;
        if (previousBasket === 0 && currentBasket !== 0) {
            setKpiComparison(
                'kpi-average-basket-comparison',
                'kpi-average-basket',
                'Aucune référence précédente',
                'is-neutral',
                comparisonPeriod
            );
        } else {
            const basketChange = previousBasket === 0
                ? 0
                : ((currentBasket - previousBasket) / previousBasket) * 100;
            const basketSign = basketChange > 0 ? '+' : basketChange < 0 ? '−' : '';
            const basketState = basketChange > 0
                ? 'is-positive'
                : basketChange < 0 ? 'is-negative' : 'is-neutral';
            setKpiComparison(
                'kpi-average-basket-comparison',
                'kpi-average-basket',
                basketChange === 0
                    ? 'Stable vs période précédente'
                    : `${basketSign}${percentFormatter.format(Math.abs(basketChange))} % `,
                basketState,
                comparisonPeriod
            );
        }

        const cancellationChange = (
            (Number(data.cancellationRate) || 0)
            - (Number(previousData.cancellationRate) || 0)
        ) * 100;
        const cancellationSign = cancellationChange > 0 ? '+' : cancellationChange < 0 ? '−' : '';
        const cancellationState = cancellationChange < 0
            ? 'is-positive'
            : cancellationChange > 0 ? 'is-negative' : 'is-neutral';
        setKpiComparison(
            'kpi-cancellation-rate-comparison',
            'kpi-cancellation-rate',
            cancellationChange === 0
                ? 'Stable vs période précédente'
                : `${cancellationSign}${percentFormatter.format(Math.abs(cancellationChange))} pt`,
            cancellationState,
            comparisonPeriod
        );
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

    function disposeRiskCharts() {
        Object.keys(charts).filter((key) => key.startsWith('risk-history-')).forEach((key) => {
            if (!charts[key].isDisposed()) charts[key].dispose();
            delete charts[key];
        });
    }

    function riskBadge(risk) {
        const badge = document.createElement('span');
        badge.className = `dashboard-risk-badge is-${risk.toLowerCase().replaceAll('_', '-')}`;
        badge.textContent = riskLabels[risk] || risk;
        return badge;
    }

    function renderDemandHistory(item) {
        const id = `dashboard-risk-history-${item.variationId}`;
        const chart = ensureChart(`risk-history-${item.variationId}`, id);
        const history = Array.isArray(item.demandHistory) ? item.demandHistory : [];
        chart.setOption({
            animation: false,
            grid: { left: 2, right: 2, top: 4, bottom: 2 },
            xAxis: { type: 'category', show: false, data: history.map((point) => point.date) },
            yAxis: { type: 'value', show: false, min: 0 },
            tooltip: {
                trigger: 'axis',
                confine: true,
                formatter: (params) => `${params[0].axisValue}<br><strong>${numberFormatter.format(params[0].value)} unité(s)</strong>`,
            },
            series: [{
                type: 'line',
                data: history.map((point) => Number(point.quantity) || 0),
                showSymbol: false,
                smooth: true,
                lineStyle: { color: '#047857', width: 2 },
                areaStyle: { color: 'rgba(4, 120, 87, .12)' },
            }],
        }, { notMerge: true });
    }

    function renderRisks(items) {
        disposeRiskCharts();
        elements.riskBody.replaceChildren();
        const empty = items.length === 0;
        document.querySelector('[data-empty-for="risks"]').hidden = !empty;
        document.querySelector('.dashboard-risk-wrapper').hidden = empty;

        items.forEach((item) => {
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

            const risk = document.createElement('td');
            risk.append(riskBadge(item.risk));
            const central = document.createElement('td');
            central.textContent = numberFormatter.format(item.forecastCentral7d);
            const q90 = document.createElement('td');
            q90.textContent = numberFormatter.format(item.forecastQ90_7d);
            const stock = document.createElement('td');
            stock.textContent = numberFormatter.format(item.stockAvailable);
            const recommendation = document.createElement('td');
            recommendation.className = 'dashboard-risk-recommendation';
            recommendation.textContent = numberFormatter.format(item.recommendedQuantity);
            const history = document.createElement('td');
            const historyChart = document.createElement('div');
            historyChart.id = `dashboard-risk-history-${item.variationId}`;
            historyChart.className = 'dashboard-risk-history';
            historyChart.setAttribute('aria-label', 'Historique récent de la demande');
            history.append(historyChart);
            const action = document.createElement('td');
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'dashboard-risk-explain';
            button.dataset.variationId = item.variationId;
            button.textContent = 'Pourquoi ce risque ?';
            action.append(button);

            row.append(product, risk, central, q90, stock, recommendation, history, action);
            elements.riskBody.append(row);
            renderDemandHistory(item);
        });
    }

    function renderExplanation(data) {
        const variation = data.variationName && data.variationName.toLowerCase() !== 'standard'
            ? ` - ${data.variationName}` : '';
        document.getElementById('dashboard-xai-product').textContent = `${data.productName}${variation}`;
        document.getElementById('dashboard-xai-stock').textContent = numberFormatter.format(data.stockAvailable);
        document.getElementById('dashboard-xai-central').textContent = numberFormatter.format(data.forecastCentral7d);
        document.getElementById('dashboard-xai-q90').textContent = numberFormatter.format(data.forecastQ90_7d);
        document.getElementById('dashboard-xai-deficit').textContent = numberFormatter.format(data.deficit);
        const risk = document.getElementById('dashboard-xai-risk');
        risk.replaceChildren(riskBadge(data.risk));
        document.getElementById('dashboard-xai-recommendation').textContent = numberFormatter.format(data.recommendedQuantity);
        document.getElementById('dashboard-xai-model').textContent = data.modelVersion;
        document.getElementById('dashboard-xai-date').textContent = new Date(data.predictedAt).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });

        const factors = Array.isArray(data.factors) ? data.factors : [];
        const noShap = document.getElementById('dashboard-xai-no-shap');
        const chartElement = document.getElementById('dashboard-xai-chart');
        noShap.hidden = factors.length > 0;
        chartElement.hidden = factors.length === 0;
        const chart = ensureChart('xai', 'dashboard-xai-chart');
        if (!factors.length) {
            chart.clear();
        } else {
            chart.setOption({
                animationDuration: 250,
                grid: { left: 12, right: 26, top: 8, bottom: 10, containLabel: true },
                tooltip: {
                    trigger: 'axis',
                    axisPointer: { type: 'shadow' },
                    formatter: (params) => {
                        const factor = factors[params[0].dataIndex];
                        const direction = factor.direction === 'INCREASES' ? 'augmente' : factor.direction === 'DECREASES' ? 'réduit' : 'ne modifie pas';
                        return `${featureLabels[factor.name] || factor.name}<br>Valeur : <strong>${factor.value ?? 'non disponible'}</strong><br>Cette variable contribue à ${direction} la prévision.`;
                    },
                },
                xAxis: { type: 'value', axisLabel: { color: '#64748b' } },
                yAxis: {
                    type: 'category',
                    data: factors.map((factor) => featureLabels[factor.name] || factor.name),
                    axisLabel: { color: '#475569', width: 180, overflow: 'truncate' },
                },
                series: [{
                    type: 'bar',
                    data: factors.map((factor) => ({
                        value: factor.contribution,
                        itemStyle: { color: factor.contribution >= 0 ? '#b45309' : '#047857', borderRadius: 4 },
                    })),
                    barMaxWidth: 22,
                }],
            }, { notMerge: true });
            window.setTimeout(() => chart.resize(), 0);
        }
        elements.xaiLoading.hidden = true;
        elements.xaiContent.hidden = false;
    }

    async function showRiskExplanation(variationId) {
        if (explanationController) explanationController.abort();
        explanationController = new AbortController();
        elements.xaiLoading.textContent = 'Chargement de l’explication…';
        elements.xaiLoading.hidden = false;
        elements.xaiContent.hidden = true;
        if (!elements.xaiDialog.open) elements.xaiDialog.showModal();
        try {
            const url = `${endpoints.riskExplanation}/${encodeURIComponent(variationId)}/explanation`;
            const response = await requestJson(url, explanationController.signal);
            renderExplanation(response.data);
        } catch (error) {
            if (error.name !== 'AbortError') {
                elements.xaiLoading.textContent = `Impossible de charger l’explication. ${error.message}`;
            }
        }
    }

    function snapshotTimestamps(snapshot) {
        const timestamps = [
            snapshot.summary?.lastUpdatedAt,
            snapshot.previousSummary?.lastUpdatedAt,
        ];
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
        (snapshot.risks || []).forEach((item) => {
            if (!item.lastUpdatedAt) return;
            const timestamp = new Date(item.lastUpdatedAt);
            if (!Number.isNaN(timestamp.getTime())) valid.push(timestamp);
        });

        return valid.length
            ? new Date(Math.max(...valid.map((value) => value.getTime())))
            : new Date();
    }

    function renderSnapshot(snapshot) {
        renderSummary(
            snapshot.summary,
            snapshot.previousSummary,
            snapshot.previousPeriod
        );
        renderEvolution(snapshot.evolution);
        renderProducts(snapshot.products);
        renderStatuses(snapshot.statuses);
        renderStock(snapshot.stock);
        if (hasRiskSection) renderRisks(snapshot.risks);

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
        const comparisonPeriod = previousPeriod(period);
        try {
            const [summary, previousSummary, evolution, products, statuses, stock, risks] = await Promise.all([
                requestJson(buildUrl(endpoints.summary, period), controller.signal),
                requestJson(buildUrl(endpoints.summary, comparisonPeriod), controller.signal),
                requestJson(buildUrl(endpoints.evolution, period), controller.signal),
                requestJson(buildUrl(endpoints.products, { ...period, limit: 100 }), controller.signal),
                requestJson(endpoints.statuses, controller.signal),
                requestJson(buildUrl(endpoints.stock, { limit: 100 }), controller.signal),
                hasRiskSection
                    ? requestJson(buildUrl(endpoints.risks, { risk: elements.riskLevel.value, limit: 100 }), controller.signal)
                    : Promise.resolve({ data: [] }),
            ]);

            if (controller !== activeController) return;
            const snapshot = {
                summary: summary.data,
                previousSummary: previousSummary.data,
                previousPeriod: comparisonPeriod,
                evolution: evolution.data,
                products: products.data,
                statuses: statuses.data,
                stock: stock.data,
                risks: risks.data,
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
    if (hasRiskSection) {
        elements.riskLevel.addEventListener('change', loadDashboard);
        elements.riskBody.addEventListener('click', (event) => {
            const button = event.target.closest('.dashboard-risk-explain');
            if (button) showRiskExplanation(button.dataset.variationId);
        });
        document.getElementById('dashboard-xai-close')?.addEventListener('click', () => elements.xaiDialog.close());
        elements.xaiDialog.addEventListener('close', () => {
            if (explanationController) explanationController.abort();
            explanationController = null;
        });
    }

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
        if (explanationController) explanationController.abort();
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
