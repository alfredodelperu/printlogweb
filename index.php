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
</body>
</html>