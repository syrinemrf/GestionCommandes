document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('supplier-products-dashboard');
    if (!root || !window.ComdelyDashboard || !window.jQuery || !$.fn.DataTable) return;
    const ui = window.ComdelyDashboard;
    const elements = { refresh: document.getElementById('dashboard-refresh'), feedback: document.getElementById('dashboard-feedback'), lastUpdated: document.getElementById('dashboard-last-updated'), stockTable: document.getElementById('dashboard-stock-table'), dialog: document.getElementById('dashboard-stock-risk-dialog'), dialogLoading: document.getElementById('dashboard-stock-risk-loading'), dialogContent: document.getElementById('dashboard-stock-risk-content') };
    const controllers = new Map();
    let explanationController = null;
    const snapshot = {};
    let stockDataTable = null;
    const riskLabels = { HIGH: 'Élevé', MEDIUM: 'Modéré', LOW: 'Faible', INSUFFICIENT_DATA: 'Non évalué' };

    function renderStockKpis(items) {
        const active = items.filter((item) => !item.productDeleted && !item.variationDeleted);
        document.getElementById('stock-active-count').textContent = ui.integer.format(active.length);
        document.getElementById('stock-out-count').textContent = ui.integer.format(active.filter((item) => item.stockAvailable <= 0).length);
        document.getElementById('stock-low-count').textContent = ui.integer.format(active.filter((item) => item.stockAvailable > 0 && item.stockAvailable <= 5).length);
        document.getElementById('stock-reserved-count').textContent = ui.integer.format(active.reduce((total, item) => total + item.stockReserved, 0));
    }

    function escapeHtml(value) {
        const element = document.createElement('span'); element.textContent = String(value ?? ''); return element.innerHTML;
    }

    function stockState(row) {
        if (row.currentlyOutOfStock || row.stockAvailable <= 0) return { label: 'Rupture', className: 'is-out' };
        if (row.stockAvailable <= 5) return { label: 'Stock faible', className: 'is-low' };
        return { label: 'Disponible', className: 'is-ok' };
    }

    function riskState(row) {
        if (row.risk === 'CURRENT_STOCKOUT') return { label: 'Rupture actuelle', className: 'is-high' };
        if (row.risk === 'INSUFFICIENT_DATA') return { label: 'Non évalué', className: 'is-insufficient-data' };
        return { label: riskLabels[row.risk] || row.risk, className: `is-${String(row.risk).toLowerCase()}` };
    }

    function initializeStockTable() {
        stockDataTable = $(elements.stockTable).DataTable({
            processing: true,
            serverSide: true,
            pageLength: 6,
            lengthChange: false,
            searching: false,
            ajax: {
                url: root.dataset.stockTableUrl,
                type: 'GET',
                error: function (xhr) {
                    setSectionError('stock', xhr.responseJSON?.message || 'Impossible de charger le détail du stock.', () => stockDataTable.ajax.reload());
                }
            },
            columns: [
                { data: null, className: 'dashboard-product-cell', render: (data, type, row) => type === 'display' ? escapeHtml(ui.productLabel(row)) : ui.productLabel(row) },
                { data: 'stockRegistered' },
                { data: 'stockUsed' },
                { data: 'stockReserved' },
                { data: 'stockAvailable', render: (value, type, row) => type === 'display' ? `<span class="dashboard-stock-value ${stockState(row).className}">${ui.integer.format(value)}</span>` : value },
                { data: null, render: (data, type, row) => { const state = stockState(row); return type === 'display' ? `<span class="dashboard-stock-badge ${state.className}">${state.label}</span>` : state.label; } },
                { data: 'risk', render: (value, type, row) => { const risk = riskState(row); return type === 'display' ? `<span class="dashboard-risk-badge ${risk.className}">${risk.label}</span>` : risk.label; } },
                { data: null, className: 'dashboard-stock-help-cell', orderable: false, searchable: false, render: (data, type, row) => type === 'display' ? `<button type="button" class="dashboard-risk-explain" title="Comprendre ce risque" aria-label="Comprendre le risque de rupture" data-variation-id="${Number(row.variationId)}">?</button>` : '' },
            ],
            order: [[4, 'asc']],
            createdRow: (row, data) => row.dataset.id = data.variationId,
        });
        $(elements.stockTable).on('xhr.dt', function (event, settings, json) {
            if (!json) return;
            setSectionError('stock');
            snapshot.tableUpdated = (json.data || []).map((item) => item.lastUpdatedAt);
            updateLastUpdated();
        });
    }

    function showExplanation(stock, prediction, explanation = null) {
        const data = explanation || prediction;
        document.getElementById('dashboard-stock-risk-product').textContent = ui.productLabel(stock);
        document.getElementById('dashboard-stock-risk-available').textContent = ui.integer.format(stock.stockAvailable);
        document.getElementById('dashboard-stock-risk-forecast').textContent = data ? `${ui.number.format(data.forecastCentral7d)} unités` : 'Non disponible';
        document.getElementById('dashboard-stock-risk-trend').textContent = data?.recentTrend || 'Non disponible';
        const observed = document.getElementById('dashboard-stock-risk-observed');
        const isOut = stock.currentlyOutOfStock || stock.stockAvailable <= 0;
        observed.hidden = !isOut;
        observed.textContent = isOut ? 'Rupture constatée : le stock disponible est nul. Cette situation réelle prévaut sur toute estimation IA.' : '';
        const insights = [prediction?.reason, ...(Array.isArray(data?.insights) ? data.insights : [])].filter((value, index, values) => value && values.indexOf(value) === index);
        const list = document.getElementById('dashboard-stock-risk-insights'); list.replaceChildren();
        insights.forEach((text) => { const item = document.createElement('li'); item.textContent = text; list.append(item); });
        document.getElementById('dashboard-stock-risk-no-forecast').hidden = data !== null;
        document.getElementById('dashboard-stock-risk-action').textContent = isOut ? 'Réapprovisionner rapidement après vérification du stock physique.' : (data?.recommendedAction || 'Surveiller manuellement le stock en attendant davantage de données.');
        document.getElementById('dashboard-stock-risk-model').textContent = data?.modelVersion || 'Non disponible';
        document.getElementById('dashboard-stock-risk-date').textContent = data?.predictedAt ? new Date(data.predictedAt).toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) : 'Non disponible';
        elements.dialogLoading.hidden = true; elements.dialogContent.hidden = false;
    }

    async function explain(stock) {
        if (!stock) return;
        explanationController?.abort(); explanationController = new AbortController();
        elements.dialogLoading.textContent = 'Chargement de l’explication...'; elements.dialogLoading.hidden = false; elements.dialogContent.hidden = true;
        if (!elements.dialog.open) elements.dialog.showModal();
        if (!stock.hasPrediction) return showExplanation(stock, null);
        try {
            const response = await ui.requestJson(`${root.dataset.riskExplanationUrl}/${encodeURIComponent(stock.variationId)}/explanation`, explanationController.signal);
            showExplanation(stock, null, response.data);
        } catch (error) {
            if (error.name !== 'AbortError') showExplanation(stock, null);
        }
    }

    function setSectionLoading(section, loading, hasContent = false) {
        document.querySelector(`[data-dashboard-section="${section}"]`)?.classList.toggle('is-loading', loading && !hasContent);
        const indicator = document.querySelector(`[data-loading-for="${section}"]`);
        if (indicator) indicator.hidden = !loading || hasContent;
    }

    function setSectionError(section, message = '', retry = null) {
        const target = document.querySelector(`[data-error-for="${section}"]`); if (!target) return;
        target.replaceChildren(); target.hidden = !message; if (!message) return; target.append(document.createTextNode(message));
        if (retry) { const button = document.createElement('button'); button.type = 'button'; button.textContent = 'Réessayer'; button.addEventListener('click', retry); target.append(button); }
    }

    function updateLastUpdated() {
        const updated = ui.latestTimestamp([snapshot.stock?.map((item) => item.lastUpdatedAt) || [], snapshot.tableUpdated || []]);
        elements.lastUpdated.textContent = updated ? updated.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) : 'Aucune donnée actualisée';
    }

    function updateRequestState() { elements.refresh.classList.toggle('is-loading', controllers.size > 0); }

    async function loadStockKpis() {
        const key = 'stock'; controllers.get(key)?.abort(); const partController = new AbortController(); controllers.set(key, partController); updateRequestState(); setSectionLoading('stock-kpis', true, Boolean(snapshot.stock));
        try { const response = await ui.requestJson(ui.buildUrl(root.dataset.stockUrl, { limit: 200 }), partController.signal); if (controllers.get(key) !== partController) return; snapshot.stock = response.data; renderStockKpis(snapshot.stock); updateLastUpdated(); }
        catch (error) { if (error.name !== 'AbortError' && controllers.get(key) === partController) ui.setFeedback(elements.feedback, `Indicateurs de stock indisponibles. ${error.message}`, loadStockKpis); }
        finally { if (controllers.get(key) === partController) { controllers.delete(key); setSectionLoading('stock-kpis', false); updateRequestState(); } }
    }

    function load(reloadTable = true) {
        controllers.forEach((item) => item.abort()); controllers.clear(); ui.setFeedback(elements.feedback);
        loadStockKpis(); if (reloadTable && stockDataTable) stockDataTable.ajax.reload(null, false);
    }
    elements.refresh.addEventListener('click', load);
    elements.stockTable.addEventListener('click', (event) => { const button = event.target.closest('.dashboard-risk-explain'); if (!button) return; explain(stockDataTable.row(button.closest('tr')).data()); });
    document.getElementById('dashboard-stock-risk-close').addEventListener('click', () => elements.dialog.close());
    elements.dialog.addEventListener('close', () => { explanationController?.abort(); explanationController = null; });
    window.addEventListener('pagehide', () => { controllers.forEach((item) => item.abort()); explanationController?.abort(); }); initializeStockTable(); load(false);
});
