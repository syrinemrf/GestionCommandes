document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('supplier-predictions-dashboard');
    if (!root || typeof echarts === 'undefined' || !window.ComdelyDashboard) return;
    const ui = window.ComdelyDashboard;
    const elements = { risk: document.getElementById('dashboard-risk-level'), refresh: document.getElementById('dashboard-refresh'), feedback: document.getElementById('dashboard-feedback'), loader: document.getElementById('dashboard-loader'), lastUpdated: document.getElementById('dashboard-last-updated'), body: document.getElementById('dashboard-risk-body'), dialog: document.getElementById('dashboard-xai-dialog'), loading: document.getElementById('dashboard-xai-loading'), content: document.getElementById('dashboard-xai-content') };
    let controller = null;
    let explanationController = null;
    let snapshot = null;
    const charts = new Map();
    const riskLabels = { HIGH: 'Élevé', MEDIUM: 'Modéré', LOW: 'Faible', INSUFFICIENT_DATA: 'Données insuffisantes' };

    function badge(risk) { const value = document.createElement('span'); value.className = `dashboard-risk-badge is-${risk.toLowerCase().replaceAll('_', '-')}`; value.textContent = riskLabels[risk] || risk; return value; }
    function disposeCharts() { charts.forEach((chart) => chart.dispose()); charts.clear(); }
    function historyChart(item) {
        const element = document.getElementById(`risk-history-${item.variationId}`); if (!element) return; const chart = echarts.init(element); charts.set(item.variationId, chart); const history = Array.isArray(item.demandHistory) ? item.demandHistory : [];
        chart.setOption({ animation: false, grid: { left: 2, right: 2, top: 4, bottom: 2 }, xAxis: { type: 'category', show: false, data: history.map((point) => point.date) }, yAxis: { type: 'value', show: false, min: 0 }, tooltip: { trigger: 'axis', confine: true, formatter: (params) => `${params[0].axisValue}<br><strong>${ui.integer.format(params[0].value)} unité(s)</strong>` }, series: [{ type: 'line', data: history.map((point) => Number(point.quantity) || 0), showSymbol: false, smooth: true, lineStyle: { color: '#047857', width: 2 }, areaStyle: { color: 'rgba(4,120,87,.12)' } }] });
    }

    function render(items, allItems) {
        disposeCharts(); elements.body.replaceChildren(); const empty = items.length === 0; document.querySelector('[data-empty-for="risks"]').hidden = !empty; document.querySelector('.dashboard-table-wrapper').hidden = empty;
        document.getElementById('risk-high-count').textContent = ui.integer.format(allItems.filter((item) => item.risk === 'HIGH').length);
        document.getElementById('risk-medium-count').textContent = ui.integer.format(allItems.filter((item) => item.risk === 'MEDIUM').length);
        document.getElementById('risk-recommended-total').textContent = ui.integer.format(allItems.reduce((total, item) => total + Number(item.recommendedQuantity || 0), 0));
        items.forEach((item) => { const row = document.createElement('tr'); const product = document.createElement('td'); product.className = 'dashboard-product-cell'; product.textContent = ui.productLabel(item); const risk = document.createElement('td'); risk.append(badge(item.risk)); const why = document.createElement('td'); why.className = 'dashboard-prediction-copy'; why.textContent = item.reason; const action = document.createElement('td'); action.className = 'dashboard-prediction-copy'; action.textContent = item.recommendedAction; const history = document.createElement('td'); const graph = document.createElement('div'); graph.id = `risk-history-${item.variationId}`; graph.className = 'dashboard-risk-history'; history.append(graph); const controls = document.createElement('td'); const button = document.createElement('button'); button.type = 'button'; button.className = 'dashboard-risk-explain'; button.dataset.variationId = item.variationId; button.textContent = 'Pourquoi ce risque ?'; controls.append(button); row.append(product, risk, why, action, history, controls); elements.body.append(row); historyChart(item); });
    }

    function loading(value) { elements.loader.hidden = !value || snapshot !== null; elements.refresh.disabled = value; elements.refresh.classList.toggle('is-loading', value); if (value && snapshot) ui.setFeedback(elements.feedback, 'Actualisation en cours. Le dernier snapshot reste affiché.'); }
    async function load() {
        controller?.abort(); controller = new AbortController(); const active = controller; loading(true);
        try { const allRequest = ui.requestJson(ui.buildUrl(root.dataset.risksUrl, { limit: 200 }), active.signal); const filteredRequest = elements.risk.value ? ui.requestJson(ui.buildUrl(root.dataset.risksUrl, { risk: elements.risk.value, limit: 200 }), active.signal) : allRequest; const [all, filtered] = await Promise.all([allRequest, filteredRequest]); if (active !== controller) return; const next = { all: all.data, visible: filtered.data }; render(next.visible, next.all); const updated = ui.latestTimestamp(next.all.map((item) => item.lastUpdatedAt)); elements.lastUpdated.textContent = updated ? updated.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) : 'Aucune prédiction actualisée'; snapshot = next; ui.setFeedback(elements.feedback); }
        catch (error) { if (error.name !== 'AbortError') ui.setFeedback(elements.feedback, `${snapshot ? 'Actualisation impossible. Le dernier snapshot reste affiché.' : 'Impossible de charger les prévisions.'} ${error.message}`, load); }
        finally { if (active === controller) { loading(false); controller = null; } }
    }

    function renderExplanation(data) {
        const suffix = data.variationName && data.variationName.toLowerCase() !== 'standard' ? ` - ${data.variationName}` : '';
        document.getElementById('dashboard-xai-product').textContent = `${data.productName}${suffix}`; document.getElementById('dashboard-xai-stock').textContent = ui.integer.format(data.stockAvailable); document.getElementById('dashboard-xai-central').textContent = `${ui.number.format(data.forecastCentral7d)} unités`; document.getElementById('dashboard-xai-trend').textContent = data.recentTrend; document.getElementById('dashboard-xai-variability').textContent = data.variability; document.getElementById('dashboard-xai-action').textContent = data.recommendedAction; document.getElementById('dashboard-xai-model').textContent = data.modelVersion; document.getElementById('dashboard-xai-date').textContent = new Date(data.predictedAt).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' });
        const list = document.getElementById('dashboard-xai-insights'); list.replaceChildren(); const insights = Array.isArray(data.insights) ? data.insights : []; insights.forEach((text) => { const item = document.createElement('li'); item.textContent = text; list.append(item); }); document.getElementById('dashboard-xai-no-shap').hidden = insights.length > 0; list.hidden = insights.length === 0; elements.loading.hidden = true; elements.content.hidden = false;
    }

    async function explain(variationId) {
        explanationController?.abort(); explanationController = new AbortController(); elements.loading.textContent = 'Chargement de l’explication…'; elements.loading.hidden = false; elements.content.hidden = true; if (!elements.dialog.open) elements.dialog.showModal();
        try { const response = await ui.requestJson(`${root.dataset.riskExplanationUrl}/${encodeURIComponent(variationId)}/explanation`, explanationController.signal); renderExplanation(response.data); }
        catch (error) { if (error.name !== 'AbortError') elements.loading.textContent = `Impossible de charger l’explication. ${error.message}`; }
    }

    elements.risk.addEventListener('change', load); elements.refresh.addEventListener('click', load); elements.body.addEventListener('click', (event) => { const button = event.target.closest('.dashboard-risk-explain'); if (button) explain(button.dataset.variationId); }); document.getElementById('dashboard-xai-close').addEventListener('click', () => elements.dialog.close()); elements.dialog.addEventListener('close', () => { explanationController?.abort(); explanationController = null; });
    window.addEventListener('resize', () => charts.forEach((chart) => chart.resize())); window.addEventListener('pagehide', () => { controller?.abort(); explanationController?.abort(); disposeCharts(); }); load();
});
