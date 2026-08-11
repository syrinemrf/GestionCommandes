document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('supplier-products-dashboard');
    if (!root || typeof echarts === 'undefined' || !window.ComdelyDashboard) return;
    const ui = window.ComdelyDashboard;
    const elements = { from: document.getElementById('dashboard-date-from'), to: document.getElementById('dashboard-date-to'), apply: document.getElementById('dashboard-apply-filters'), refresh: document.getElementById('dashboard-refresh'), feedback: document.getElementById('dashboard-feedback'), loader: document.getElementById('dashboard-loader'), lastUpdated: document.getElementById('dashboard-last-updated'), search: document.getElementById('dashboard-stock-search'), stockBody: document.getElementById('dashboard-stock-body'), dialog: document.getElementById('dashboard-stock-risk-dialog'), dialogLoading: document.getElementById('dashboard-stock-risk-loading'), dialogContent: document.getElementById('dashboard-stock-risk-content') };
    const chart = echarts.init(document.getElementById('dashboard-products-chart'));
    let controller = null;
    let explanationController = null;
    let snapshot = null;
    const riskLabels = { HIGH: 'Élevé', MEDIUM: 'Modéré', LOW: 'Faible', INSUFFICIENT_DATA: 'Non évalué' };

    function aggregateProducts(items) {
        const products = new Map();
        items.filter((item) => !item.productDeleted).forEach((item) => { const current = products.get(item.productId) || { name: item.productName, revenue: 0, units: 0, orders: 0 }; current.revenue += Number(item.revenueHt) || 0; current.units += Number(item.unitsSold) || 0; current.orders += Number(item.orderCount) || 0; products.set(item.productId, current); });
        return Array.from(products.values()).sort((a, b) => b.revenue - a.revenue || b.units - a.units).slice(0, 10).reverse();
    }

    function renderProducts(items) {
        const products = aggregateProducts(items); const empty = products.length === 0; document.querySelector('[data-empty-for="products"]').hidden = !empty; chart.getDom().hidden = empty;
        if (empty) return chart.clear();
        chart.setOption({ color: ['#047857'], tooltip: { trigger: 'axis', axisPointer: { type: 'shadow' }, backgroundColor: '#0f172a', borderWidth: 0, textStyle: { color: '#fff' }, formatter: (params) => { const item = products[params[0].dataIndex]; return `${item.name}<br><strong>${ui.exactMoney.format(item.revenue)} TND HT</strong><br>${ui.integer.format(item.units)} unités`; } }, grid: { left: 12, right: 30, top: 12, bottom: 12, containLabel: true }, xAxis: { type: 'value', axisLabel: { color: '#64748b', formatter: (value) => ui.money(value).replace(' TND', '') } }, yAxis: { type: 'category', data: products.map((item) => item.name), axisLabel: { color: '#475569', width: 180, overflow: 'truncate' } }, series: [{ type: 'bar', data: products.map((item) => item.revenue), barMaxWidth: 24, itemStyle: { borderRadius: [0, 5, 5, 0] } }] }, { notMerge: true });
    }

    function stockState(item) {
        if (item.currentlyOutOfStock || item.stockAvailable <= 0) return { label: 'Rupture', className: 'is-out' };
        if (item.stockAvailable <= 5) return { label: 'Stock faible', className: 'is-low' };
        return { label: 'Disponible', className: 'is-ok' };
    }

    function riskState(stock, prediction) {
        if (stock.currentlyOutOfStock || stock.stockAvailable <= 0) return { label: 'Rupture actuelle', className: 'is-high' };
        if (!prediction || prediction.risk === 'INSUFFICIENT_DATA') return { label: 'Non évalué', className: 'is-insufficient-data' };
        return { label: riskLabels[prediction.risk] || prediction.risk, className: `is-${prediction.risk.toLowerCase()}` };
    }

    function renderStock(items, risks) {
        const active = items.filter((item) => !item.productDeleted && !item.variationDeleted);
        const risksByVariation = new Map(risks.map((item) => [Number(item.variationId), item]));
        document.getElementById('stock-active-count').textContent = ui.integer.format(active.length);
        document.getElementById('stock-out-count').textContent = ui.integer.format(active.filter((item) => item.stockAvailable <= 0).length);
        document.getElementById('stock-low-count').textContent = ui.integer.format(active.filter((item) => item.stockAvailable > 0 && item.stockAvailable <= 5).length);
        document.getElementById('stock-reserved-count').textContent = ui.integer.format(active.reduce((total, item) => total + item.stockReserved, 0));
        const query = elements.search.value.trim().toLocaleLowerCase('fr');
        const visible = active.filter((item) => ui.productLabel(item).toLocaleLowerCase('fr').includes(query)).sort((a, b) => a.stockAvailable - b.stockAvailable || a.productName.localeCompare(b.productName, 'fr'));
        elements.stockBody.replaceChildren(); document.querySelector('[data-empty-for="stock"]').hidden = visible.length > 0; document.querySelector('.dashboard-table-wrapper').hidden = visible.length === 0;
        visible.forEach((item) => {
            const state = stockState(item);
            const prediction = risksByVariation.get(Number(item.variationId)) || null;
            const risk = riskState(item, prediction);
            const row = document.createElement('tr');
            const values = [ui.productLabel(item), item.stockRegistered, item.stockUsed, item.stockReserved, item.stockAvailable];
            values.forEach((value, index) => { const cell = document.createElement('td'); cell.textContent = index === 0 ? value : ui.integer.format(value); if (index === 0) cell.className = 'dashboard-product-cell'; if (index === 4) cell.className = `dashboard-stock-value ${state.className}`; row.append(cell); });
            const status = document.createElement('td'); const stockBadge = document.createElement('span'); stockBadge.className = `dashboard-stock-badge ${state.className}`; stockBadge.textContent = state.label; status.append(stockBadge);
            const riskCell = document.createElement('td'); riskCell.className = 'dashboard-stock-risk-cell'; const riskBadge = document.createElement('span'); riskBadge.className = `dashboard-risk-badge ${risk.className}`; riskBadge.textContent = risk.label; const why = document.createElement('button'); why.type = 'button'; why.className = 'dashboard-risk-explain'; why.dataset.variationId = item.variationId; why.textContent = '?'; why.title = 'Comprendre ce risque'; why.setAttribute('aria-label', `Comprendre le risque de rupture de ${ui.productLabel(item)}`); riskCell.append(riskBadge, why);
            row.append(status, riskCell); elements.stockBody.append(row);
        });
    }

    function showExplanation(stock, prediction, explanation = null) {
        const data = explanation || prediction;
        document.getElementById('dashboard-stock-risk-product').textContent = ui.productLabel(stock);
        document.getElementById('dashboard-stock-risk-available').textContent = ui.integer.format(stock.stockAvailable);
        document.getElementById('dashboard-stock-risk-forecast').textContent = data ? `${ui.number.format(data.forecastCentral7d)} unités` : 'Non disponible';
        document.getElementById('dashboard-stock-risk-trend').textContent = data?.recentTrend || 'Non disponible';
        document.getElementById('dashboard-stock-risk-variability').textContent = data?.variability || 'Non disponible';
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

    async function explain(variationId) {
        const stock = snapshot?.stock.find((item) => Number(item.variationId) === Number(variationId));
        if (!stock) return;
        const prediction = snapshot.risks.find((item) => Number(item.variationId) === Number(variationId)) || null;
        explanationController?.abort(); explanationController = new AbortController();
        elements.dialogLoading.textContent = 'Chargement de l’explication...'; elements.dialogLoading.hidden = false; elements.dialogContent.hidden = true;
        if (!elements.dialog.open) elements.dialog.showModal();
        if (!prediction) return showExplanation(stock, null);
        try {
            const response = await ui.requestJson(`${root.dataset.riskExplanationUrl}/${encodeURIComponent(variationId)}/explanation`, explanationController.signal);
            showExplanation(stock, prediction, response.data);
        } catch (error) {
            if (error.name !== 'AbortError') showExplanation(stock, prediction);
        }
    }

    function loading(value) { elements.loader.hidden = !value || snapshot !== null; elements.refresh.disabled = value; elements.apply.disabled = value; elements.refresh.classList.toggle('is-loading', value); if (value && snapshot) ui.setFeedback(elements.feedback, 'Actualisation en cours. Le dernier snapshot reste affiché.'); }
    async function load() {
        if (!elements.from.value || !elements.to.value || elements.from.value > elements.to.value) return ui.setFeedback(elements.feedback, 'La période sélectionnée est invalide.');
        controller?.abort(); controller = new AbortController(); const active = controller; loading(true); const period = { from: elements.from.value, to: elements.to.value };
        try { const [products, stock, risks] = await Promise.all([ui.requestJson(ui.buildUrl(root.dataset.productsUrl, { ...period, limit: 100 }), active.signal), ui.requestJson(ui.buildUrl(root.dataset.stockUrl, { limit: 200 }), active.signal), ui.requestJson(ui.buildUrl(root.dataset.risksUrl, { limit: 200 }), active.signal)]); if (active !== controller) return; const next = { products: products.data, stock: stock.data, risks: risks.data }; renderProducts(next.products); renderStock(next.stock, next.risks); const updated = ui.latestTimestamp([next.products.map((item) => item.lastUpdatedAt), next.stock.map((item) => item.lastUpdatedAt), next.risks.map((item) => item.lastUpdatedAt)]); elements.lastUpdated.textContent = updated ? updated.toLocaleString('fr-FR', { dateStyle: 'medium', timeStyle: 'short' }) : 'Aucune donnée actualisée'; snapshot = next; ui.setFeedback(elements.feedback); }
        catch (error) { if (error.name !== 'AbortError') ui.setFeedback(elements.feedback, `${snapshot ? 'Actualisation impossible. Le dernier snapshot reste affiché.' : 'Impossible de charger les produits.'} ${error.message}`, load); }
        finally { if (active === controller) { loading(false); controller = null; } }
    }
    document.querySelectorAll('.dashboard-period').forEach((button) => button.addEventListener('click', () => { ui.selectPeriod(elements.from, elements.to, Number(button.dataset.periodDays)); load(); }));
    elements.apply.addEventListener('click', load); elements.refresh.addEventListener('click', load); elements.search.addEventListener('input', () => { if (snapshot) renderStock(snapshot.stock, snapshot.risks); });
    elements.stockBody.addEventListener('click', (event) => { const button = event.target.closest('.dashboard-risk-explain'); if (button) explain(button.dataset.variationId); });
    document.getElementById('dashboard-stock-risk-close').addEventListener('click', () => elements.dialog.close());
    elements.dialog.addEventListener('close', () => { explanationController?.abort(); explanationController = null; });
    window.addEventListener('resize', () => chart.resize()); window.addEventListener('pagehide', () => { controller?.abort(); explanationController?.abort(); chart.dispose(); }); ui.selectPeriod(elements.from, elements.to, 90); load();
});
