let autoRefreshInterval;
let allData = [];
let filteredData = [];
let selectedRows = new Set();
let allPcs = [];
let currentPage = 1;
const itemsPerPage = 50; // ✅ Cambiado de 20 a 50
let isLoadingData = false;

// Estado de ordenamiento - cargado desde localStorage si existe
let sortOrder = JSON.parse(localStorage.getItem('dashboardSortOrder')) || {
    column: 'fecha,hora', // ✅ Orden por fecha/hora por defecto
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

    const pcCheckboxes = document.querySelectorAll('#pcFilter input[type="checkbox"]:not(#selectAllPcs)');
    pcCheckboxes.forEach(cb => {
        cb.checked = state.selectedPcs.includes(cb.value);
    });
    updateSelectAllPcsState();

    if (debugMode) console.log('📂 Dashboard state cargado:', state);
}

function initializeDates() {
    const today = new Date().toLocaleDateString('en-CA'); // ✅ Corregido: usa zona local
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

    loadData(); // ✅ Actualiza automáticamente
}

function calculateML(ancho, largo, copias) {
    if (!ancho || !largo || !copias) return 0;
    const anchoNum = parseFloat(ancho);
    const largoNum = parseFloat(largo);
    const copiasNum = parseInt(copias);
    let dimension = (anchoNum >= 60 || largoNum >= 60) ? Math.max(anchoNum, largoNum) : Math.min(anchoNum, largoNum);
    return (dimension * copiasNum / 100).toFixed(2);
}

function calculateM2(ancho, largo, copias) {
    if (!ancho || !largo || !copias) return 0;
    const anchoNum = parseFloat(ancho);
    const largoNum = parseFloat(largo);
    const copiasNum = parseInt(copias);
    return ((anchoNum * largoNum * copiasNum) / 10000).toFixed(2);
}

async function loadData() {
    if (isLoadingData) return;
    isLoadingData = true;

    const refreshBtn = document.querySelector('.refresh-btn');

    try {
        const params = new URLSearchParams();

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
            updatePcFilter(); // ← ¡Aquí se generan los checkboxes!
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
        if (refreshBtn) {
            refreshBtn.classList.remove('loading');
        }
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
    document.getElementById('ripJobs').textContent = stats.rip_count || 0;
    document.getElementById('printJobs').textContent = stats.print_count || 0;
    document.getElementById('mlTotal').textContent = stats.ml_total || 0;
    document.getElementById('m2Total').textContent = stats.m2_total || 0;
    document.getElementById('uniquePcs').textContent = stats.unique_pcs || 0;
    updateSelectedStats();
}

function updateStats() {
    const stats = {
        total: filteredData.length,
        rip_count: filteredData.filter(item => item.evento === 'RIP').length,
        print_count: filteredData.filter(item => item.evento === 'PRINT').length,
        ml_total: filteredData.reduce((sum, item) => sum + parseFloat(item.ml_total || 0), 0).toFixed(2),
        m2_total: filteredData.reduce((sum, item) => sum + parseFloat(item.m2_total || 0), 0).toFixed(2),
        unique_pcs: new Set(filteredData.map(item => item.pc_name).filter(pc => pc)).size
    };

    document.getElementById('totalJobs').textContent = stats.total;
    document.getElementById('ripJobs').textContent = stats.rip_count;
    document.getElementById('printJobs').textContent = stats.print_count;
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
        rip_count: selectedData.filter(item => item.evento === 'RIP').length,
        print_count: selectedData.filter(item => item.evento === 'PRINT').length,
        ml_total: selectedData.reduce((sum, item) => sum + parseFloat(item.ml_total || 0), 0).toFixed(2),
        m2_total: selectedData.reduce((sum, item) => sum + parseFloat(item.m2_total || 0), 0).toFixed(2),
        unique_pcs: new Set(selectedData.map(item => item.pc_name).filter(pc => pc)).size
    };

    document.getElementById('totalJobsSelected').textContent = `Selec: ${selectedStats.total}`;
    document.getElementById('ripJobsSelected').textContent = `Selec: ${selectedStats.rip_count}`;
    document.getElementById('printJobsSelected').textContent = `Selec: ${selectedStats.print_count}`;
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

    const sizeColumnHeader = showSizeColumn ? '<th>Tamaño (m²)</th>' : '';

    let tableHTML = `
        <div class="table-responsive">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 12px; text-align: left;">
                            <input type="checkbox" onchange="toggleSelectAll(this)" 
                                   ${selectedRows.size > 0 && selectedRows.size === pageData.length ? 'checked' : ''}>
                        </th>
                        <th class="filename-col" style="cursor: pointer;" data-column="archivo">
                            Archivo <span class="sort-indicator"></span>
                        </th>
                        <th class="event-col" style="cursor: pointer;" data-column="evento">
                            Evento <span class="sort-indicator"></span>
                        </th>
                        <th class="dimensions-col" style="cursor: pointer;" data-column="ancho,largo">
                            Dimensiones <span class="sort-indicator"></span>
                        </th>
                        <th class="copies-col" style="cursor: pointer;" data-column="copias">
                            Copias <span class="sort-indicator"></span>
                        </th>
                        <th class="ml-col" style="cursor: pointer;" data-column="ml_total">
                            ML Total <span class="sort-indicator"></span>
                        </th>
                        ${sizeColumnHeader}
                        <th class="pc-col" style="cursor: pointer;" data-column="pc_name">
                            PC <span class="sort-indicator"></span>
                        </th>
                        <th class="date-col" style="cursor: pointer;" data-column="fecha,hora">
                            Fecha/Hora <span class="sort-indicator"></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
    `;

    pageData.forEach(item => {
        const isSelected = selectedRows.has(item.id);
        const statusClass = item.evento === 'RIP' ? 'rip-event' : 'print-event';
        const dimensions = item.ancho && item.largo ? `${item.ancho} × ${item.largo} cm` : '-';
        const mlTotal = item.ml_total ? parseFloat(item.ml_total).toFixed(2) : '0.00';
        const m2Total = item.m2_total ? parseFloat(item.m2_total).toFixed(2) : '0.00';
        const sizeColumn = showSizeColumn ? `<td class="m2-col" style="padding: 12px;">${m2Total}</td>` : '';

        tableHTML += `
            <tr 
                data-row-id="${item.id}"
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
                <td class="filename-col" title="${item.archivo || ''}">
                    ${item.archivo || '-'}
                </td>
                <td style="padding: 12px;">
                    <span class="${statusClass}" style="padding: 4px 8px; border-radius: 4px; font-size: 12px; 
                          background: ${item.evento === 'RIP' ? '#e3f2fd' : '#f3e5f5'}; 
                          color: ${item.evento === 'RIP' ? '#1976d2' : '#7b1fa2'};">
                        ${item.evento}
                    </span>
                </td>
                <td style="padding: 12px;">${dimensions}</td>
                <td style="padding: 12px;">${item.copias || 1}</td>
                <td style="padding: 12px;">${mlTotal} m</td>
                ${sizeColumn}
                <td style="padding: 12px;">
                    <span style="background: #e8f5e8; color: #2e7d32; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                        ${item.pc_name || '-'}
                    </span>
                </td>
                <td style="padding: 12px; font-size: 12px;">
                    ${item.fecha ? new Date(item.fecha).toLocaleString('es-ES') : '-'}
                </td>
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
    console.log('🚀 Dashboard inicializando...');
    
    try {
        initializeDates();
        loadDashboardState();
        loadData();
        setupAutoRefresh();
        setupFilterListeners();
        updateSortIndicators();
        document.addEventListener('click', handleColumnClick); // ✅ REGISTRADO AHORA
        console.log('🎉 Dashboard inicializado correctamente');
    } catch (error) {
        console.error('❌ Error en inicialización:', error);
    }
});

// Guardar estado antes de cerrar
window.addEventListener('beforeunload', function() {
    saveDashboardState();
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
});