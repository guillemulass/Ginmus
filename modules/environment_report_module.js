/**
 * @file environment_report_module.js
 * @description Maneja la lógica para generar y descargar un informe de entorno
 * de una dirección específica, interactuando con un endpoint de backend.
 */

/**
 * Inicializa el módulo de Informe de Entorno.
 */
window.initEnvironmentReportModule = () => {
    const environmentReportAppContainer = document.getElementById('environment-report-app-container');
    if (!environmentReportAppContainer) {
        console.error('ERROR JS: Elemento #environment-report-app-container no encontrado.');
        return;
    }

    // Inyecta la UI del módulo.
    environmentReportAppContainer.innerHTML = `
        <div class="p-4 bg-white rounded-lg shadow-sm w-full h-full flex flex-col">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Datos a introducir para generar informe</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <input type="text" id="address-input" class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Introduce la dirección (ej. Calle Mayor 1, Madrid)">
                <input type="number" id="radius-input" class="p-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500" placeholder="Radio en metros (ej. 500)" value="500">
            </div>
            <div class="flex gap-2 mb-4">
                <button id="generate-report-button" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-md transition duration-200">Generar Informe</button>
                <button id="download-pdf-button" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-md transition duration-200 disabled:bg-gray-400 disabled:cursor-not-allowed" disabled>Descargar PDF</button>
            </div>
            <div id="report-status" class="text-gray-600 text-sm mb-4"></div>
            <!-- Contenedor para mostrar el informe generado -->
            <div id="report-content" class="flex-1 overflow-y-auto border-2 border-blue-400 rounded-lg shadow-xl p-6 bg-white text-base leading-relaxed text-gray-800">
                <p class="text-gray-500">El informe del entorno se generará aquí.</p>
            </div>
        </div>
    `;

    // --- Referencias a elementos del DOM ---
    const addressInput = document.getElementById('address-input');
    const radiusInput = document.getElementById('radius-input');
    const generateReportButton = document.getElementById('generate-report-button');
    const downloadPdfButton = document.getElementById('download-pdf-button');
    const reportStatusDiv = document.getElementById('report-status');
    const reportContentDiv = document.getElementById('report-content');

    // --- Estado del Módulo ---
    let currentReport = ''; // Almacena el texto del último informe generado.
    let currentAddress = ''; // Almacena la dirección del último informe.

    /**
     * Limpia el texto de formato Markdown para una visualización más limpia como texto plano.
     * @param {string} text - El texto con formato Markdown.
     * @returns {string} El texto sin caracteres de Markdown.
     */
    const cleanMarkdown = (text) => {
        return text
            .replace(/\*\*(.*?)\*\*/g, '$1') // Quita negritas
            .replace(/\*(.*?)\*/g, '$1')     // Quita cursivas
            .replace(/#+\s*/g, '')          // Quita cabeceras (#, ##, etc.)
            .replace(/^-\s+/gm, '• ')       // Convierte guiones de lista a viñetas
            .replace(/\n\s*\n\s*\n/g, '\n\n') // Normaliza saltos de línea
            .trim();
    };

    /**
     * Solicita la generación de un informe al backend.
     */
    const generateReport = async () => {
        const address = addressInput.value.trim();
        const radius = parseInt(radiusInput.value, 10);

        // Validación de entradas
        if (!address) {
            reportStatusDiv.textContent = 'Por favor, introduce una dirección.';
            return;
        }
        if (isNaN(radius) || radius <= 0) {
            reportStatusDiv.textContent = 'Por favor, introduce un radio válido.';
            return;
        }

        // Actualiza la UI al estado de "cargando".
        reportStatusDiv.textContent = 'Generando informe... Esto puede tardar unos segundos.';
        reportContentDiv.innerHTML = '<p class="text-gray-500">Cargando...</p>';
        generateReportButton.disabled = true;
        downloadPdfButton.disabled = true;

        try {
            const response = await fetch('api/environment_report.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ address, radius }),
            });

            const data = await response.json();

            if (data.success) {
                reportStatusDiv.textContent = 'Informe generado con éxito.';
                const formattedText = cleanMarkdown(data.report);
                
                // Guarda el informe para la descarga y lo muestra en pantalla.
                currentReport = formattedText;
                currentAddress = address;
                reportContentDiv.innerHTML = `<pre class="whitespace-pre-wrap font-sans">${formattedText}</pre>`;
                
                downloadPdfButton.disabled = false;
                reportContentDiv.scrollTop = 0; // Scroll al inicio del informe
            } else {
                reportStatusDiv.textContent = `Error: ${data.message}`;
                reportContentDiv.innerHTML = `<p class="text-red-500">Error al generar el informe: ${data.message}</p>`;
            }
        } catch (error) {
            reportStatusDiv.textContent = 'Error de conexión con el servidor.';
            reportContentDiv.innerHTML = `<p class="text-red-500">Error de conexión o de red.</p>`;
            console.error('Error en la solicitud de informe de entorno:', error);
        } finally {
            generateReportButton.disabled = false;
        }
    };

    /**
     * Genera y descarga un archivo PDF del informe actual usando jsPDF.
     */
    const downloadPDF = () => {
        if (!currentReport) {
            alert('No hay informe disponible para descargar.');
            return;
        }
        // jsPDF se carga globalmente desde el CDN en public.html
        if (typeof window.jsPDF === 'undefined') {
            alert('Error: La librería jsPDF no está cargada.');
            return;
        }

        try {
            const { jsPDF } = window.jsPDF;
            const doc = new jsPDF();

            // Configuración de márgenes y dimensiones
            const pageWidth = doc.internal.pageSize.width;
            const margin = 20;
            const maxWidth = pageWidth - (margin * 2);
            
            // Título y metadatos del PDF
            doc.setFontSize(16).setFont(undefined, 'bold');
            doc.text('INFORME DE ENTORNO', margin, margin + 10);
            doc.setFontSize(12).setFont(undefined, 'normal');
            doc.text(`Dirección: ${currentAddress}`, margin, margin + 25);
            doc.text(`Fecha: ${new Date().toLocaleDateString('es-ES')}`, margin, margin + 35);
            doc.line(margin, margin + 45, pageWidth - margin, margin + 45);
            
            // Contenido principal del informe
            doc.setFontSize(10);
            const splitText = doc.splitTextToSize(currentReport, maxWidth);
            
            let yPosition = margin + 60;
            const lineHeight = 5;
            
            // Itera sobre las líneas y añade páginas nuevas si es necesario.
            splitText.forEach((line) => {
                if (yPosition + lineHeight > doc.internal.pageSize.height - margin) {
                    doc.addPage();
                    yPosition = margin + 10;
                }
                doc.text(line, margin, yPosition);
                yPosition += lineHeight;
            });
            
            // Guarda el archivo
            const fileName = `Informe_Entorno_${currentAddress.replace(/[^a-zA-Z0-9]/g, '_')}.pdf`;
            doc.save(fileName);
            
        } catch (error) {
            console.error('Error al generar PDF:', error);
            alert('Ocurrió un error al generar el PDF.');
        }
    };

    // --- Event Listeners ---
    generateReportButton.addEventListener('click', generateReport);
    downloadPdfButton.addEventListener('click', downloadPDF);
};