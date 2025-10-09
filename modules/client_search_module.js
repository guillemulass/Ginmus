// public_html/plataforma/modules/client_search_module.js - Módulo para mostrar anuncios.csv

console.log("DEBUG JS: client_search_module.js file is being parsed.");

window.initClientSearchModule = () => {
    console.log("DEBUG JS: initClientSearchModule function called.");
    const clientSearchAppContainer = document.getElementById('client-search-app-container');
    if (!clientSearchAppContainer) {
        console.error('ERROR JS: Elemento #client-search-app-container no encontrado dentro de initClientSearchModule. Asegúrate de que index.html lo incluya.');
        return;
    }
    console.log("DEBUG JS: client-search-app-container encontrado. Inyectando HTML.");

    // Renderizar la interfaz de usuario del módulo de búsqueda de clientes
    clientSearchAppContainer.innerHTML = `
        <div class="p-6 bg-white rounded-lg shadow-md max-w-full mx-auto my-8">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">
                Anuncios 
                <span id="lastUpdateInfo" class="text-sm font-normal text-gray-500 ml-2"></span>
            </h2>
            
            <div class="summary-container mb-4 p-4 bg-blue-50 rounded-lg text-blue-800 font-semibold flex flex-wrap gap-x-6 gap-y-2 justify-between items-center">
                <p>Total de Anuncios: <span id="total-ads">0</span></p>
                <p>Ventas: <span id="sales-count">0</span></p>
                <p>Alquileres: <span id="rentals-count">0</span></p>
                <button id="export-excel-button" class="action-button bg-green-600 hover:bg-green-700 focus:ring-green-500 text-white px-4 py-2 rounded-md transition duration-150 ease-in-out">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Exportar a Excel
                </button>
            </div>

            <div class="mb-4 flex flex-col sm:flex-row gap-4">
                <input type="text" id="search-input" placeholder="Buscar por cualquier campo..."
                       class="flex-1 p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                <select id="sort-by-select"
                        class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Ordenar por...</option>
                </select>
                <select id="sort-order-select"
                        class="p-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="asc">Ascendente</option>
                    <option value="desc">Descendente</option>
                </select>
            </div>
            <div id="loading-status" class="text-blue-600 text-center py-4">Cargando datos...</div>
            <div id="error-message" class="text-red-600 text-center py-4 hidden"></div>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border border-gray-200 rounded-lg">
                    <thead class="bg-blue-600 text-white">
                        <tr id="table-headers">
                            </tr>
                    </thead>
                    <tbody id="table-body">
                        </tbody>
                </table>
            </div>
            <div id="no-results-message" class="text-gray-500 text-center py-4 hidden">No se encontraron resultados.</div>
        </div>
    `;

    const searchInput = document.getElementById('search-input');
    const sortBySelect = document.getElementById('sort-by-select');
    const sortOrderSelect = document.getElementById('sort-order-select');
    const loadingStatus = document.getElementById('loading-status');
    const errorMessage = document.getElementById('error-message');
    const tableHeaders = document.getElementById('table-headers');
    const tableBody = document.getElementById('table-body');
    const noResultsMessage = document.getElementById('no-results-message');
    const exportExcelButton = document.getElementById('export-excel-button');
    const totalAdsSpan = document.getElementById('total-ads');
    const salesCountSpan = document.getElementById('sales-count');
    const rentalsCountSpan = document.getElementById('rentals-count');
    const lastUpdateInfoSpan = document.getElementById('lastUpdateInfo');

    let allData = [];
    let currentHeaders = [];
    let filteredAndSortedData = [];

    function showErrorMessage(message) {
        errorMessage.textContent = message;
        errorMessage.classList.remove('hidden');
        loadingStatus.classList.add('hidden');
        tableBody.innerHTML = '';
        noResultsMessage.classList.add('hidden');
        updateSummary([]);
        exportExcelButton.disabled = true;
        if (lastUpdateInfoSpan) {
            lastUpdateInfoSpan.textContent = '(Error al cargar datos)';
        }
    }

    function renderTable(dataToRender) {
        tableBody.innerHTML = '';
        noResultsMessage.classList.add('hidden');

        if (dataToRender.length === 0) {
            noResultsMessage.classList.remove('hidden');
            exportExcelButton.disabled = true;
            return;
        }

        dataToRender.forEach(rowData => {
            const row = document.createElement('tr');
            row.classList.add('border-b', 'border-gray-200', 'hover:bg-gray-50');
            currentHeaders.forEach(header => {
                const cell = document.createElement('td');
                cell.classList.add('p-3', 'text-sm', 'text-gray-700');
                
                if (header === 'Precio' && rowData[header] !== undefined && rowData[header] !== null) {
                    let priceString = String(rowData[header]).replace('€', '').trim();
                    priceString = priceString.replace(/\./g, '').replace(',', '.');
                    const priceValue = parseFloat(priceString);
                    cell.textContent = !isNaN(priceValue) ? priceValue.toLocaleString('es-ES', { style: 'currency', currency: 'EUR' }) : (rowData[header] || '');
                } 
                else if (header === 'Enlace' && rowData[header]) {
                    const link = document.createElement('a');
                    link.href = rowData[header];
                    link.textContent = 'Ver Anuncio';
                    link.target = '_blank';
                    link.classList.add('text-blue-600', 'hover:underline');
                    cell.appendChild(link);
                }
                else {
                    cell.textContent = rowData[header] || '';
                }
                row.appendChild(cell);
            });
            tableBody.appendChild(row);
        });
        exportExcelButton.disabled = false;
    }

    function updateSummary(data) {
        const totalAds = data.length;
        let sales = 0;
        let rentals = 0;

        data.forEach(item => {
            if (item.Tipo && typeof item.Tipo === 'string' && item.Tipo.toLowerCase().includes('venta')) {
                sales++;
            } else if (item.Tipo && typeof item.Tipo === 'string' && item.Tipo.toLowerCase().includes('alquiler')) {
                rentals++;
            }
        });

        totalAdsSpan.textContent = totalAds;
        salesCountSpan.textContent = sales;
        rentalsCountSpan.textContent = rentals;
    }

    function filterAndSortData() {
        let filteredData = [...allData];

        const searchTerm = searchInput.value.toLowerCase();
        if (searchTerm) {
            filteredData = filteredData.filter(row =>
                Object.values(row).some(value =>
                    String(value).toLowerCase().includes(searchTerm)
                )
            );
        }

        const sortBy = sortBySelect.value;
        const sortOrder = sortOrderSelect.value;

        if (sortBy) {
            filteredData.sort((a, b) => {
                const valA = a[sortBy];
                const valB = b[sortBy];

                if (sortBy.includes('Precio')) {
                    const cleanValA = String(valA || '').replace('€', '').replace(/\./g, '').replace(',', '.').trim();
                    const cleanValB = String(valB || '').replace('€', '').replace(/\./g, '').replace(',', '.').trim();
                    const numA = parseFloat(cleanValA) || 0;
                    const numB = parseFloat(cleanValB) || 0;
                    return sortOrder === 'asc' ? numA - numB : numB - numA;
                }
                
                if (sortBy.includes('Fecha')) {
                    const dateA = new Date(valA);
                    const dateB = new Date(valB);
                    return sortOrder === 'asc' ? dateA - dateB : dateB - dateA;
                }

                if (valA === undefined || valA === null) return sortOrder === 'asc' ? -1 : 1;
                if (valB === undefined || valB === null) return sortOrder === 'asc' ? 1 : -1;

                if (typeof valA === 'string' && typeof valB === 'string') {
                    return sortOrder === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                }
                return 0;
            });
        }
        filteredAndSortedData = filteredData;
        renderTable(filteredAndSortedData);
        updateSummary(filteredAndSortedData);
    }

    function exportToExcel(filename, data, headers) {
        if (!data || data.length === 0 || !headers || headers.length === 0) return;
        const wsData = [headers.map(header => String(header))];
        data.forEach(row => {
            const rowArray = headers.map(header => {
                let value = row[header] !== undefined && row[header] !== null ? row[header] : '';
                if (header === 'Precio') {
                    let priceString = String(value).replace('€', '').trim().replace(/\./g, '').replace(',', '.');
                    value = parseFloat(priceString) || 0;
                }
                return value;
            });
            wsData.push(rowArray);
        });

        const ws = XLSX.utils.aoa_to_sheet(wsData);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, "Anuncios");
        XLSX.writeFile(wb, filename);
    }

    async function loadCsvData() {
        loadingStatus.classList.remove('hidden');
        errorMessage.classList.add('hidden');
        noResultsMessage.classList.add('hidden');
        tableBody.innerHTML = '';
        updateSummary([]);
        exportExcelButton.disabled = true;
        if (lastUpdateInfoSpan) lastUpdateInfoSpan.textContent = '(Cargando...)';

        try {
            // CAMBIO: URL relativa para el servidor local
            const response = await fetch('api/get_anuncios_csv.php');
            
            if (!response.ok) {
                 const errorText = await response.text();
                 throw new Error(`Error en la respuesta del servidor: ${response.status} ${response.statusText}. Detalles: ${errorText}`);
            }

            const result = await response.json();

            if (result.success) {
                allData = result.data;
                currentHeaders = result.headers;
                tableHeaders.innerHTML = '';
                currentHeaders.forEach(header => {
                    const th = document.createElement('th');
                    th.classList.add('p-3', 'text-left', 'text-sm', 'font-semibold', 'tracking-wider', 'cursor-pointer', 'hover:bg-blue-700');
                    th.textContent = header;
                    th.setAttribute('data-column', header);
                    tableHeaders.appendChild(th);
                });

                sortBySelect.innerHTML = '<option value="">Ordenar por...</option>';
                currentHeaders.forEach(header => {
                    const option = document.createElement('option');
                    option.value = header;
                    option.textContent = header;
                    sortBySelect.appendChild(option);
                });

                filteredAndSortedData = allData;
                renderTable(filteredAndSortedData);
                updateSummary(filteredAndSortedData);
                loadingStatus.classList.add('hidden');
                exportExcelButton.disabled = false;

                if (lastUpdateInfoSpan && result.lastModified) {
                    const csvDate = new Date(result.lastModified);
                    const formattedDate = csvDate.toLocaleString('es-ES', {
                        year: 'numeric', month: 'numeric', day: 'numeric',
                        hour: '2-digit', minute: '2-digit'
                    });
                    lastUpdateInfoSpan.textContent = `(Última actualización: ${formattedDate})`;
                } else if (lastUpdateInfoSpan) {
                    lastUpdateInfoSpan.textContent = '(Fecha no disponible)';
                }

            } else {
                throw new Error(`Error del servidor: ${result.message}`);
            }
        } catch (error) {
            console.error('Error al cargar datos CSV:', error);
            showErrorMessage(error.message);
        }
    }

    searchInput.addEventListener('input', filterAndSortData);
    sortBySelect.addEventListener('change', filterAndSortData);
    sortOrderSelect.addEventListener('change', filterAndSortData);

    tableHeaders.addEventListener('click', (event) => {
        const clickedTh = event.target.closest('th');
        if (clickedTh) {
            const column = clickedTh.getAttribute('data-column');
            if (column) {
                if (sortBySelect.value === column) {
                    sortOrderSelect.value = sortOrderSelect.value === 'asc' ? 'desc' : 'asc';
                } else {
                    sortBySelect.value = column;
                    sortOrderSelect.value = 'asc';
                }
                filterAndSortData();
            }
        }
    });

    exportExcelButton.addEventListener('click', () => {
        const filename = `anuncios_ginmus_${new Date().toISOString().slice(0,10)}.xlsx`;
        exportToExcel(filename, filteredAndSortedData, currentHeaders);
    });

    loadCsvData();
};