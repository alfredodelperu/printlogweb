<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🖨️ PrintologWeb - Monitor de Impresiones</title>
    <link rel="stylesheet" href="estilos.css">
</head>

<body>
<div class="container">
    <div class="header">
        <h1>🖨️ PrintologWeb</h1>
        <p>Monitor de impresiones en tiempo real - Historial y En Proceso</p>
    </div>

    <!-- PESTAÑAS DE TABLAS -->
    <div class="table-selector">
        <button class="tab-btn active" data-table="HistoryTasks">Historial</button>
        <button class="tab-btn" data-table="RecordTasks">En Proceso</button>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-number" id="totalJobs">-</div>
            <div class="stat-number-selected" id="totalJobsSelected" style="display:none;">Selec: -</div>
            <div class="stat-label">Total Jobs</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="completedJobs">-</div>
            <div class="stat-number-selected" id="completedJobsSelected" style="display:none;">Selec: -</div>
            <div class="stat-label">Completadas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="incompleteJobs">-</div>
            <div class="stat-number-selected" id="incompleteJobsSelected" style="display:none;">Selec: -</div>
            <div class="stat-label">Incompletas</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="mlTotal">-</div>
            <div class="stat-number-selected" id="mlTotalSelected" style="display:none;">Selec: -</div>
            <div class="stat-label">ML Total (m)</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="m2Total">-</div>
            <div class="stat-number-selected" id="m2TotalSelected" style="display:none;">Selec: -</div>
            <div class="stat-label">M² Total</div>
        </div>
        <div class="stat-card">
            <div class="stat-number" id="uniquePcs">-</div>
            <div class="stat-number-selected" id="uniquePcsSelected" style="display:none;">Selec: -</div>
            <div class="stat-label">PCs Únicas</div>
        </div>
    </div>

    <div class="filters-container">
        <h3 style="margin-bottom: 20px; color: #333;">🔍 Filtros</h3>
        
        <div class="filters-row">
            <div class="filter-group">
                <label class="filter-label">📅 Rango de Fechas</label>
                <div style="display: flex; gap: 10px;">
                    <input type="date" class="filter-input" id="dateFrom" style="flex: 1;">
                    <input type="date" class="filter-input" id="dateTo" style="flex: 1;">
                </div>
                <div class="date-shortcuts">
                    <button class="date-shortcut active" onclick="setDateRange('today')">Hoy</button>
                    <button class="date-shortcut" onclick="setDateRange('yesterday')">Ayer</button>
                    <button class="date-shortcut" onclick="setDateRange('thisWeek')">Esta Semana</button>
                    <button class="date-shortcut" onclick="setDateRange('lastWeek')">Semana Pasada</button>
                    <button class="date-shortcut" onclick="setDateRange('thisMonth')">Este Mes</button>
                    <button class="date-shortcut" onclick="setDateRange('lastMonth')">Mes Pasado</button>
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">📝 Nombre de Archivo</label>
                <input type="text" class="filter-input" id="filenameFilter" placeholder="Buscar archivos... (separa con comas)">
                <div class="filename-filter-options">
                    <div class="checkbox-container">
                        <input type="radio" name="filenameLogic" value="or" id="filenameOr" checked>
                        <label for="filenameOr">OR (cualquier término)</label>
                    </div>
                    <div class="checkbox-container">
                        <input type="radio" name="filenameLogic" value="and" id="filenameAnd">
                        <label for="filenameAnd">AND (todos los términos)</label>
                    </div>
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">💻 Computadoras</label>
                <div class="multi-select" id="pcFilter">
                    <div class="loading">Cargando PCs...</div>
                </div>
            </div>

            <div class="filter-group">
                <label class="filter-label">🎯 Estado</label>
                <select class="filter-select" id="eventFilter">
                    <option value="">Todos los estados</option>
                    <option value="1">Completadas</option>
                    <option value="0">Incompletas</option>
                </select>
            </div>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header">
            <h2 class="table-title">📊 Registro de Actividad</h2>
            <div class="table-controls">
                <div class="show-size-toggle">
                    <label class="switch">
                        <input type="checkbox" id="showSizeColumn">
                        <span class="slider"></span>
                    </label>
                    <span>Mostrar M²</span>
                </div>
                <div class="export-buttons">
                    <!-- Exportar página actual -->
                    <button class="export-icon" onclick="exportData('excel')" title="Exportar página actual como Excel">📊</button>
                    <button class="export-icon" onclick="exportData('csv')" title="Exportar página actual como CSV">📄</button>
                    <button class="export-icon" onclick="exportData('pdf')" title="Exportar página actual como PDF">📋</button>

                    <hr style="margin: 15px 0; border-color: #ddd;">

                    <!-- Exportar TODO el resultado filtrado -->
                    <button class="export-icon" onclick="exportData('excel', true)" title="Exportar todos los registros filtrados (Excel)">🚀</button>
                    <button class="export-icon" onclick="exportData('csv', true)" title="Exportar todos los registros filtrados (CSV)">🚀</button>
                    <button class="export-icon" onclick="exportData('pdf', true)" title="Exportar todos los registros filtrados (PDF)">🚀</button>

                    <p style="font-size: 0.8em; color: #666; margin-top: 10px;">
                        📌 Haz clic en 🚀 para exportar todos los registros filtrados (hasta 1000).
                    </p>
                </div>
                <div class="auto-refresh">
                    <label class="switch">
                        <input type="checkbox" id="autoRefresh" checked>
                        <span class="slider"></span>
                    </label>
                    <span>Auto-refresh (30s)</span>
                </div>
            </div>
        </div>
        
        <div id="tableContent">
            <div class="loading">
                <div class="spinner"></div>
                Cargando datos...
            </div>
        </div>
        
        <div class="last-update" id="lastUpdate"></div>
    </div>
</div>

<!-- MODAL PARA VER IMAGEN Y DETALLES -->
<div id="imageModal" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close">&times;</span>
        <h2>Detalle de la Impresión</h2>
        <div class="modal-image-container">
            <img id="modalImage" src="" alt="Imagen de impresión" style="max-width: 100%; max-height: 400px; object-fit: contain; margin: 20px auto; display: block;">
        </div>
        <div class="modal-details">
            <p><strong>Archivo:</strong> <span id="modalBmpPath">-</span></p>
            <p><strong>Dimensiones:</strong> <span id="modalDimensions">-</span></p>
            <p><strong>Largo total:</strong> <span id="modalLargoTotal">-</span> m</p>
            <p><strong>Copias:</strong> <span id="modalCopiasReq">-</span> enviadas / <span id="modalCopiasImp">-</span> impresas</p>
            <p><strong>Duración:</strong> <span id="modalDuracion">-</span></p>
            <p><strong>Producción:</strong> <span id="modalProduccion">-</span>%</p>
            <p><strong>Fecha inicio:</strong> <span id="modalFecha1">-</span></p>
            <p><strong>Fecha fin:</strong> <span id="modalFecha2">-</span></p>
            <p><strong>Modo de impresión:</strong> <span id="modalModo">-</span></p>
            <p><strong>PC:</strong> <span id="modalPcName">-</span></p>
            <p><strong>UID:</strong> <span id="modalUID">-</span></p>
        </div>
    </div>
</div>

<script src="funciones.js"></script>

<script>
let autoRefreshInterval;
let allData = [];
let filteredData = [];
let selectedRows = new Set();
let allPcs = [];
let currentPage = 1;
const itemsPerPage = 50;
let isLoadingData = false;
let currentTable = 'HistoryTasks'; // Por defecto

// Estado de ordenamiento
let sortOrder = JSON.parse(localStorage.getItem('dashboardSortOrder')) || {
    column: 'fecha1',
    direction: 'desc'
};

// Estado del dashboard guardado en localStorage
function saveDashboardState() {
    const state = {
        dateFrom: document.getElementById('dateFrom').value,
        dateTo: document.getElementById('dateTo').value,
        filenameFilter: document.getElementById('filenameFilter').value,
        filenameLogic: document.querySelector('input[name="filenameLogic"]:checked')?.value || 'or',
        eventFilter: document.getElementById('eventFilter').value,
        showSizeColumn: document.getElementById('showSizeColumn').checked,
        autoRefresh: document.getElementById('autoRefresh').checked,
        selectedPcs: Array.from(document.querySelectorAll('#pcFilter input[type="checkbox"]:checked:not(#selectAllPcs)')).map(cb => cb.value),
        currentTable: currentTable
    };
    localStorage.setItem('dashboardState', JSON.stringify(state));
    if (debugMode) console.log('💾 Dashboard state guardado:', state);
}

function loadDashboardState() {
    const saved = localStorage.getItem('dashboardState');
    if (!saved) return;

    const state = JSON.parse(saved);

    document.getElementById('dateFrom').value = state.dateFrom || '';
    document.getElementById('dateTo').value = state.dateTo || '';
    document.getElementById('filenameFilter').value = state.filenameFilter || '';
    document.querySelector(`input[name="filenameLogic"][value="${state.filenameLogic}"]`)?.click();
    document.getElementById('eventFilter').value = state.eventFilter || '';
    document.getElementById('showSizeColumn').checked = state.showSizeColumn || false;
    document.getElementById('autoRefresh').checked = state.autoRefresh || true;
    currentTable = state.currentTable || 'HistoryTasks';

    // Actualizar pestaña activa
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.table === currentTable) {
            btn.classList.add('active');
        }
    });

    const pcCheckboxes = document.querySelectorAll('#pcFilter input[type="checkbox"]:not(#selectAllPcs)');
    pcCheckboxes.forEach(cb => {
        cb.checked = state.selectedPcs.includes(cb.value);
    });
    updateSelectAllPcsState();

    if (debugMode) console.log('📂 Dashboard state cargado:', state);
}

function initializeDates() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dateFrom').value = today;
    document.getElementById('dateTo').value = today;
    console.log('✅ Fechas inicializadas:', today);
}

function setDateRange(range) {
    console.log('🗓️ Estableciendo rango de fecha:', range);
    const today = new Date();
    let startDate, endDate;

    document.querySelectorAll('.date-shortcut').forEach(btn => btn.classList.remove('active'));

    switch(range) {
        case 'today':
            startDate = new Date(today);
            endDate = new Date(today);
            break;
        case 'yesterday':
            startDate = new Date(today.getTime() - 24 * 60 * 60 * 1000);
            endDate = new Date(today.getTime() - 24 * 60 * 60 * 1000);
            break;
        case 'thisWeek':
            const dayOfWeek = today.getDay();
            const daysFromMonday = dayOfWeek === 0 ? 6 : dayOfWeek - 1;
            startDate = new Date(today.getTime() - (daysFromMonday * 24 * 60 * 60 * 1000));
            endDate = new Date(today);
            break;
        case 'lastWeek':
            const dayOfWeek2 = today.getDay();
            const daysFromMonday2 = dayOfWeek2 === 0 ? 6 : dayOfWeek2 - 1;
            endDate = new Date(today.getTime() - (daysFromMonday2 + 1) * 24 * 60 * 60 * 1000);
            startDate = new Date(endDate.getTime() - 6 * 24 * 60 * 60 * 1000);
            break;
        case 'thisMonth':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1);
            endDate = new Date(today);
            break;
        case 'lastMonth':
            startDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            endDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
    }

    const dateFromStr = startDate.toISOString().split('T')[0];
    const dateToStr = endDate.toISOString().split('T')[0];

    document.getElementById('dateFrom').value = dateFromStr;
    document.getElementById('dateTo').value = dateToStr;

    if (typeof event !== 'undefined' && event.target) {
        event.target.classList.add('active');
    } else {
        document.querySelector(`[onclick="setDateRange('${range}')"]`)?.classList.add('active');
    }

    loadData(); // Actualiza automáticamente
}

async function loadData() {
    if (isLoadingData) return;
    isLoadingData = true;

    try {
        const params = new URLSearchParams();
        params.append('table', currentTable); // ← NUEVO PARÁMETRO: tabla activa

        const dateFromValue = document.getElementById('dateFrom').value;
        const dateToValue = document.getElementById('dateTo').value;

        if (dateFromValue) params.append('dateFrom', dateFromValue);
        if (dateToValue) params.append('dateTo', dateToValue);

        const filenameFilter = document.getElementById('filenameFilter').value.trim();
        if (filenameFilter) {
            params.append('filename', filenameFilter);
            const filenameLogic = document.querySelector('input[name="filenameLogic"]:checked')?.value || 'or';
            params.append('filenameLogic', filenameLogic);
        }

        const selectedPcs = Array.from(document.querySelectorAll('#pcFilter input[type="checkbox"]:checked:not(#selectAllPcs)')).map(cb => cb.value);
        const allPcsChecked = document.getElementById('selectAllPcs')?.checked;

        if (!allPcsChecked && selectedPcs.length > 0) {
            params.append('pcs', selectedPcs.join(','));
        }

        const eventFilter = document.getElementById('eventFilter').value;
        if (eventFilter) params.append('event', eventFilter);

        if (sortOrder.column && sortOrder.direction) {
            params.append('order_by', sortOrder.column);
            params.append('order_dir', sortOrder.direction);
        }

        const apiUrl = `api.php?${params.toString()}`;
        console.log('🌐 URL API:', apiUrl);

        const response = await fetch(apiUrl);
        if (!response.ok) throw new Error(`HTTP ${response.status}: ${response.statusText}`);

        const result = await response.json();
        if (result.error) throw new Error(result.error);

        allData = result.data || [];
        filteredData = [...allData];

        if (result.pcs_list && JSON.stringify(result.pcs_list) !== JSON.stringify(allPcs)) {
            allPcs = result.pcs_list;
            updatePcFilter();
        }

        updateStatsFromServer(result.stats || {});
        selectedRows.clear();
        currentPage = 1;
        updateTable();

        document.getElementById('lastUpdate').textContent = `Última actualización: ${new Date().toLocaleString()}`;

    } catch (error) {
        console.error('❌ Error al cargar datos:', error);
        document.getElementById('tableContent').innerHTML = `
            <div class="error" style="text-align: center; padding: 40px; color: #dc3545;">
                <h3>❌ Error al cargar los datos</h3>
                <p style="margin: 15px 0;">${error.message}</p>
                <button onclick="loadData()" class="refresh-btn" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                    🔄 Reintentar
                </button>
            </div>
        `;
    } finally {
        isLoadingData = false;
    }
}

function updatePcFilter() {
    const pcFilter = document.getElementById('pcFilter');
    let html = `
        <div class="multi-select-option pc-option">
            <input type="checkbox" id="selectAllPcs" checked onchange="toggleAllPcs(this)">
            <label for="selectAllPcs"><strong>Seleccionar Todas (${allPcs.length})</strong></label>
        </div>
    `;
    html += allPcs.map(pc => `
        <div class="multi-select-option pc-option">
            <input type="checkbox" id="pc_${pc.replace(/[^a-zA-Z0-9]/g, '_')}" value="${pc}" checked onchange="onPcChange(this)">
            <label for="pc_${pc.replace(/[^a-zA-Z0-9]/g, '_')}">${pc}</label>
        </div>
    `).join('');
    pcFilter.innerHTML = html;
}

function onPcChange(checkbox) {
    console.log('💻 PC cambiada:', checkbox.value, checkbox.checked);
    updateSelectAllPcsState();
    debounceLoadData();
}

function updateSelectAllPcsState() {
    const selectAllPcs = document.getElementById('selectAllPcs');
    if (!selectAllPcs) return;

    const pcCheckboxes = document.querySelectorAll('#pcFilter input[type="checkbox"]:not(#selectAllPcs)');
    const checkedPcs = document.querySelectorAll('#pcFilter input[type="checkbox"]:not(#selectAllPcs):checked');

    if (checkedPcs.length === 0) {
        selectAllPcs.indeterminate = false;
        selectAllPcs.checked = false;
    } else if (checkedPcs.length === pcCheckboxes.length) {
        selectAllPcs.indeterminate = false;
        selectAllPcs.checked = true;
    } else {
        selectAllPcs.indeterminate = true;
        selectAllPcs.checked = false;
    }
}

function toggleAllPcs(checkbox) {
    console.log('🔄 Toggle todas las PCs:', checkbox.checked);
    const pcCheckboxes = document.querySelectorAll('#pcFilter input[type="checkbox"]:not(#selectAllPcs)');
    pcCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    debounceLoadData();
}

let loadDataTimeout;
function debounceLoadData() {
    clearTimeout(loadDataTimeout);
    loadDataTimeout = setTimeout(() => {
        loadData();
    }, 300);
}

function handleRowCheckboxClick(checkbox, id) {
    event.stopPropagation();
    const numId = parseInt(id);
    if (checkbox.checked) {
        selectedRows.add(numId);
    } else {
        selectedRows.delete(numId);
    }
    updateTable();
    updateSelectedStats();
}

function selectRow(id) {
    const numId = parseInt(id);
    if (selectedRows.has(numId)) {
        selectedRows.delete(numId);
    } else {
        selectedRows.add(numId);
    }
    updateTable();
    updateSelectedStats();
}

function toggleSelectAll(checkbox) {
    const visibleRows = filteredData.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);
    visibleRows.forEach(item => {
        if (checkbox.checked) {
            selectedRows.add(item.id);
        } else {
            selectedRows.delete(item.id);
        }
    });
    updateTable();
    updateSelectedStats();
}

function exportData(format, exportAll = false) {
    const params = new URLSearchParams();
    params.append('format', format);
    params.append('table', currentTable); // ← NUEVO: enviar tabla activa

    if (selectedRows.size > 0) {
        params.append('selected', Array.from(selectedRows).join(','));
    } else if (exportAll) {
        const dateFromValue = document.getElementById('dateFrom').value;
        const dateToValue = document.getElementById('dateTo').value;
        if (dateFromValue) params.append('dateFrom', dateFromValue);
        if (dateToValue) params.append('dateTo', dateToValue);
        const filenameFilter = document.getElementById('filenameFilter').value.trim();
        if (filenameFilter) {
            params.append('filename', filenameFilter);
            params.append('filenameLogic', document.querySelector('input[name="filenameLogic"]:checked')?.value || 'or');
        }
        const selectedPcs = Array.from(document.querySelectorAll('#pcFilter input[type="checkbox"]:checked:not(#selectAllPcs)')).map(cb => cb.value);
        if (selectedPcs.length > 0) {
            params.append('pcs', selectedPcs.join(','));
        }
        const eventFilter = document.getElementById('eventFilter').value;
        if (eventFilter) params.append('event', eventFilter);
    } else {
        const dateFromValue = document.getElementById('dateFrom').value;
        const dateToValue = document.getElementById('dateTo').value;
        if (dateFromValue) params.append('dateFrom', dateFromValue);
        if (dateToValue) params.append('dateTo', dateToValue);
        const filenameFilter = document.getElementById('filenameFilter').value.trim();
        if (filenameFilter) {
            params.append('filename', filenameFilter);
            params.append('filenameLogic', document.querySelector('input[name="filenameLogic"]:checked')?.value || 'or');
        }
        const selectedPcs = Array.from(document.querySelectorAll('#pcFilter input[type="checkbox"]:checked:not(#selectAllPcs)')).map(cb => cb.value);
        if (selectedPcs.length > 0) {
            params.append('pcs', selectedPcs.join(','));
        }
        const eventFilter = document.getElementById('eventFilter').value;
        if (eventFilter) params.append('event', eventFilter);
        params.append('page', currentPage);
        params.append('limit', itemsPerPage);
    }

    window.open(`export.php?${params.toString()}`, '_blank');
}

function updateStatsFromServer(stats) {
    document.getElementById('totalJobs').textContent = stats.total || 0;
    document.getElementById('completedJobs').textContent = stats.completed_count || 0;
    document.getElementById('incompleteJobs').textContent = stats.incomplete_count || 0;
    document.getElementById('mlTotal').textContent = stats.ml_total || 0;
    document.getElementById('m2Total').textContent = stats.m2_total || 0;
    document.getElementById('uniquePcs').textContent = stats.unique_pcs || 0;
    updateSelectedStats();
}

function updateStats() {
    const stats = {
        total: filteredData.length,
        completed_count: filteredData.filter(item => item.completado === 1).length,
        incomplete_count: filteredData.filter(item => item.completado === 0).length,
        ml_total: filteredData.reduce((sum, item) => sum + parseFloat(item.largototal || 0), 0).toFixed(2),
        m2_total: filteredData.reduce((sum, item) => sum + parseFloat(item.anchomm * item.largomm / 1000000 || 0), 0).toFixed(2),
        unique_pcs: new Set(filteredData.map(item => item.pc_name).filter(pc => pc)).size
    };

    document.getElementById('totalJobs').textContent = stats.total;
    document.getElementById('completedJobs').textContent = stats.completed_count;
    document.getElementById('incompleteJobs').textContent = stats.incomplete_count;
    document.getElementById('mlTotal').textContent = stats.ml_total;
    document.getElementById('m2Total').textContent = stats.m2_total;
    document.getElementById('uniquePcs').textContent = stats.unique_pcs;
    updateSelectedStats();
}

function updateSelectedStats() {
    if (selectedRows.size === 0) {
        document.querySelectorAll('.stat-number-selected').forEach(el => el.style.display = 'none');
        return;
    }
    const selectedData = filteredData.filter(item => selectedRows.has(item.id));
    const selectedStats = {
        total: selectedData.length,
        completed_count: selectedData.filter(item => item.completado === 1).length,
        incomplete_count: selectedData.filter(item => item.completado === 0).length,
        ml_total: selectedData.reduce((sum, item) => sum + parseFloat(item.largototal || 0), 0).toFixed(2),
        m2_total: selectedData.reduce((sum, item) => sum + parseFloat(item.anchomm * item.largomm / 1000000 || 0), 0).toFixed(2),
        unique_pcs: new Set(selectedData.map(item => item.pc_name).filter(pc => pc)).size
    };

    document.getElementById('totalJobsSelected').textContent = `Selec: ${selectedStats.total}`;
    document.getElementById('completedJobsSelected').textContent = `Selec: ${selectedStats.completed_count}`;
    document.getElementById('incompleteJobsSelected').textContent = `Selec: ${selectedStats.incomplete_count}`;
    document.getElementById('mlTotalSelected').textContent = `Selec: ${selectedStats.ml_total}`;
    document.getElementById('m2TotalSelected').textContent = `Selec: ${selectedStats.m2_total}`;
    document.getElementById('uniquePcsSelected').textContent = `Selec: ${selectedStats.unique_pcs}`;
    document.querySelectorAll('.stat-number-selected').forEach(el => el.style.display = 'block');
}

function updateTable() {
    const showSizeColumn = document.getElementById('showSizeColumn').checked;
    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const pageData = filteredData.slice(startIndex, endIndex);

    if (pageData.length === 0) {
        document.getElementById('tableContent').innerHTML = `
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>📭 No hay datos para mostrar</h3>
                <p>Intenta ajustar los filtros o cambiar el rango de fechas</p>
                <small>Total de registros en BD: ${allData.length}</small>
            </div>
        `;
        return;
    }

    const sizeColumnHeader = showSizeColumn ? '<th>M²</th>' : '';

    let tableHTML = `
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">
                            <input type="checkbox" onchange="toggleSelectAll(this)" 
                                   ${selectedRows.size > 0 && selectedRows.size === pageData.length ? 'checked' : ''}>
                        </th>
                        <th class="filename-col" style="cursor: pointer;" data-column="bmppath">
                            Archivo <span class="sort-indicator"></span>
                        </th>
                        <th class="dimensions-col" style="cursor: pointer;" data-column="anchomm,largomm">
                            Dimensiones (cm) <span class="sort-indicator"></span>
                        </th>
                        <th class="largo-col" style="cursor: pointer;" data-column="largototal">
                            Largo Total (m) <span class="sort-indicator"></span>
                        </th>
                        <th class="copies-col" style="cursor: pointer;" data-column="copiasrequeridas">
                            Copias <span class="sort-indicator"></span>
                        </th>
                        <th class="produccion-col" style="cursor: pointer;" data-column="produccion">
                            Producción (%) <span class="sort-indicator"></span>
                        </th>
                        <th class="pc-col" style="cursor: pointer;" data-column="pc_name">
                            PC <span class="sort-indicator"></span>
                        </th>
                        <th class="date-col" style="cursor: pointer;" data-column="fecha1">
                            Fecha/Hora <span class="sort-indicator"></span>
                        </th>
                        ${sizeColumnHeader}
                    </tr>
                </thead>
                <tbody>
    `;

    pageData.forEach(item => {
        const isSelected = selectedRows.has(item.id);
        const statusClass = item.completado === 1 ? 'completed' : 'incomplete';
        const anchoCm = item.anchomm ? (item.anchomm / 10).toFixed(1) : '-';
        const largoCm = item.largomm ? (item.largomm / 10).toFixed(1) : '-';
        const dimensions = `${anchoCm} × ${largoCm}`;
        const largoTotal = item.largototal ? parseFloat(item.largototal).toFixed(2) : '0.00';
        const produccion = item.produccion ? parseFloat(item.produccion).toFixed(1) : '0.0';
        const copias = `${item.copiasrequeridas} / ${item.copiasimpresas || 0}`;
        const m2Total = item.anchomm && item.largomm ? (item.anchomm * item.largomm / 1000000).toFixed(2) : '0.00';
        const fecha1 = item.fecha1 ? new Date(item.fecha1).toLocaleString('es-ES') : '-';

        tableHTML += `
            <tr 
                data-row-id="${item.id}"
                data-codigoimagen="${item.codigoimagen}"
                data-pcname="${item.pc_name}"
                ${isSelected ? 'selected' : ''}
                style="border-bottom: 1px solid #eee; cursor: pointer; 
                       ${isSelected ? 'border-left: 4px solid #1e88e5; background-color: #f0f7ff; box-shadow: 2px 0 8px rgba(30, 136, 229, 0.15);' : ''}"
                onmouseover="this.style.backgroundColor='${isSelected ? '#e6f0ff' : '#f8f9fa'}'"
                onmouseout="this.style.backgroundColor='${isSelected ? '#f0f7ff' : 'transparent'}'">
                <td style="padding: 12px;">
                    <input type="checkbox" ${isSelected ? 'checked' : ''} 
                           onclick="handleRowCheckboxClick(this, '${item.id}')" 
                           style="margin: 0; cursor: pointer;">
                </td>
                <td class="filename-col" 
                    title="${item.bmppath || ''}"
                    style="cursor: pointer; word-break: break-all; padding: 12px;"
                    onclick="openImageModal(${item.id})"
                >
                    ${item.bmppath || '-'}
                </td>
                <td style="padding: 12px;">${dimensions}</td>
                <td style="padding: 12px;">${largoTotal} m</td>
                <td style="padding: 12px;">${copias}</td>
                <td style="padding: 12px;">
                    <span style="background: ${item.completado === 1 ? '#e8f5e8' : '#fff3cd'}; 
                          color: ${item.completado === 1 ? '#2e7d32' : '#856404'}; 
                          padding: 2px 8px; border-radius: 12px; font-weight: bold;">
                        ${produccion}%
                    </span>
                </td>
                <td style="padding: 12px;">
                    <span style="background: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                        ${item.pc_name || '-'}
                    </span>
                </td>
                <td style="padding: 12px; font-size: 12px;">${fecha1}</td>
                ${showSizeColumn ? `<td style="padding: 12px;">${m2Total}</td>` : ''}
            </tr>
        `;
    });

    tableHTML += `
                </tbody>
            </table>
        </div>
    `;

    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    if (totalPages > 1) {
        tableHTML += `
            <div class="pagination" style="display: flex; justify-content: center; align-items: center; padding: 20px; gap: 10px;">
                <button onclick="changePage(1)" ${currentPage === 1 ? 'disabled' : ''} 
                        style="padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px;">
                    « Primera
                </button>
                <button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}
                        style="padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px;">
                    ‹ Anterior
                </button>
                
                <span style="margin: 0 15px; font-weight: bold;">
                    Página ${currentPage} de ${totalPages} 
                    <small>(${filteredData.length} registros)</small>
                </span>
                
                <button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}
                        style="padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px;">
                    Siguiente ›
                </button>
                <button onclick="changePage(${totalPages})" ${currentPage === totalPages ? 'disabled' : ''}
                        style="padding: 8px 12px; border: 1px solid #ddd; background: white; cursor: pointer; border-radius: 4px;">
                    Última »
                </button>
            </div>
        `;
    }

    document.getElementById('tableContent').innerHTML = tableHTML;
}

function changePage(page) {
    const totalPages = Math.ceil(filteredData.length / itemsPerPage);
    if (page >= 1 && page <= totalPages) {
        currentPage = page;
        updateTable();
    }
}

// Manejar clic en encabezados para ordenar
function updateSortIndicators() {
    document.querySelectorAll('th[data-column] .sort-indicator').forEach(indicator => {
        indicator.textContent = '';
    });
    const currentHeader = document.querySelector(`th[data-column="${sortOrder.column}"]`);
    if (currentHeader) {
        const indicator = currentHeader.querySelector('.sort-indicator');
        indicator.textContent = sortOrder.direction === 'asc' ? ' ↑' : ' ↓';
    }
}

function handleColumnClick(event) {
    const th = event.target.closest('th[data-column]');
    if (!th) return;

    const column = th.getAttribute('data-column');
    if (column === sortOrder.column) {
        sortOrder.direction = sortOrder.direction === 'asc' ? 'desc' : 'asc';
    } else {
        sortOrder.column = column;
        sortOrder.direction = 'asc';
    }

    updateSortIndicators();
    currentPage = 1;
    loadData();
    localStorage.setItem('dashboardSortOrder', JSON.stringify(sortOrder));
}

// Manejar clic en cualquier celda para seleccionar fila
document.addEventListener('click', function(e) {
    const td = e.target.closest('td');
    if (!td) return;

    if (e.target.type === 'checkbox' ||
        e.target.tagName === 'BUTTON' ||
        e.target.tagName === 'A' ||
        e.target.closest('.export-icon') ||
        e.target.closest('.date-shortcut') ||
        e.target.closest('label')) {
        return;
    }

    const tr = td.closest('tr');
    if (!tr) return;

    const id = tr.dataset.rowId;
    if (!id) return;

    const numId = parseInt(id);
    if (selectedRows.has(numId)) {
        selectedRows.delete(numId);
    } else {
        selectedRows.add(numId);
    }

    updateTable();
    updateSelectedStats();
});

// ABRE EL MODAL CON LA IMAGEN Y DETALLES
function openImageModal(rowId) {
    const row = filteredData.find(r => r.id === rowId);
    if (!row) return;

    const imgSrc = `get_image.php?codigoimagen=${row.codigoimagen}&pc_name=${encodeURIComponent(row.pc_name)}`;
    
    document.getElementById('modalImage').src = imgSrc;
    document.getElementById('modalBmpPath').textContent = row.bmppath || '-';
    document.getElementById('modalDimensions').textContent = `${(row.anchomm/10).toFixed(1)} × ${(row.largomm/10).toFixed(1)} cm`;
    document.getElementById('modalLargoTotal').textContent = row.largototal ? row.largototal.toFixed(2) + ' m' : '-';
    document.getElementById('modalCopiasReq').textContent = row.copiasrequeridas || 0;
    document.getElementById('modalCopiasImp').textContent = row.copiasimpresas || 0;
    document.getElementById('modalProduccion').textContent = row.produccion ? row.produccion.toFixed(1) + '%' : '-';
    document.getElementById('modalFecha1').textContent = row.fecha1 ? new Date(row.fecha1).toLocaleString('es-ES') : '-';
    document.getElementById('modalFecha2').textContent = row.fecha2 ? new Date(row.fecha2).toLocaleString('es-ES') : '-';
    document.getElementById('modalModo').textContent = row.modoimpresion || '-';
    document.getElementById('modalPcName').textContent = row.pc_name || '-';
    document.getElementById('modalUID').textContent = row.uid || '-';

    const duracionSegundos = row.tiempotranscurrido2 ? row.tiempotranscurrido2 / 1000 : 0;
    const horas = Math.floor(duracionSegundos / 3600);
    const minutos = Math.floor((duracionSegundos % 3600) / 60);
    const segundos = duracionSegundos % 60;
    const duracionFormat = `${horas.toString().padStart(2, '0')}:${minutos.toString().padStart(2, '0')}:${segundos.toString().padStart(2, '0')}`;
    document.getElementById('modalDuracion').textContent = duracionFormat;

    document.getElementById('imageModal').style.display = 'block';
}

// Cerrar modal
document.querySelector('.close').onclick = function() {
    document.getElementById('imageModal').style.display = 'none';
}

window.onclick = function(event) {
    const modal = document.getElementById('imageModal');
    if (event.target === modal) {
        modal.style.display = 'none';
    }
}

// Configuración de auto-refresh
function setupAutoRefresh() {
    const autoRefreshCheckbox = document.getElementById('autoRefresh');
    
    function updateAutoRefresh() {
        if (autoRefreshInterval) {
            clearInterval(autoRefreshInterval);
            autoRefreshInterval = null;
        }
        if (autoRefreshCheckbox.checked) {
            autoRefreshInterval = setInterval(() => {
                if (!isLoadingData) {
                    loadData();
                }
            }, 30000);
            console.log('🔄 Auto-refresh activado');
        } else {
            console.log('⏸️ Auto-refresh desactivado');
        }
    }
    
    updateAutoRefresh();
    autoRefreshCheckbox.addEventListener('change', updateAutoRefresh);
}

// Configurar listeners de filtros
function setupFilterListeners() {
    document.getElementById('dateFrom').addEventListener('change', () => { loadData(); saveDashboardState(); });
    document.getElementById('dateTo').addEventListener('change', () => { loadData(); saveDashboardState(); });

    let filenameTimeout;
    document.getElementById('filenameFilter').addEventListener('input', () => {
        clearTimeout(filenameTimeout);
        filenameTimeout = setTimeout(() => { loadData(); saveDashboardState(); }, 500);
    });

    document.querySelectorAll('input[name="filenameLogic"]').forEach(radio => {
        radio.addEventListener('change', () => { loadData(); saveDashboardState(); });
    });

    document.getElementById('eventFilter').addEventListener('change', () => { loadData(); saveDashboardState(); });
    document.getElementById('showSizeColumn').addEventListener('change', () => { updateTable(); saveDashboardState(); });
    document.getElementById('autoRefresh').addEventListener('change', () => { setupAutoRefresh(); saveDashboardState(); });
}

// Inicializar al cargar
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 PrintologWeb inicializando...');
    
    try {
        initializeDates();
        loadDashboardState();
        loadData();
        setupAutoRefresh();
        setupFilterListeners();
        updateSortIndicators();
        document.addEventListener('click', handleColumnClick);

        // Asociar pestañas
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                currentTable = btn.dataset.table;
                currentPage = 1;
                loadData();
                saveDashboardState();
            });
        });

        console.log('🎉 PrintologWeb inicializado correctamente');
    } catch (error) {
        console.error('❌ Error en inicialización:', error);
    }
});

// Guardar estado antes de cerrar
window.addEventListener('beforeunload', function() {
    saveDashboardState();
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
});
</script>

</body>  
</html>