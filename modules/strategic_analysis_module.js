/**
 * @file strategic_analysis_module.js
 * @description Maneja la lógica del Agente de Análisis Estratégico.
 * @version 3.0 - Incluye una función de renderizado de Markdown robusta y completa.
 */

window.initStrategicAnalysisModule = () => {
    // --- Referencias a elementos del DOM ---
    const form = document.getElementById('strategic-analysis-form');
    const resultsContainer = document.getElementById('strategic-analysis-results');

    if (!form || !resultsContainer) {
        console.error("ERROR JS: Faltan elementos del DOM #strategic-analysis-form o #strategic-analysis-results.");
        return;
    }
    
    /**
     * [VERSIÓN DEFINITIVA] Convierte un texto con Markdown simple a formato HTML.
     * Esta versión es más robusta: procesa el texto línea por línea y maneja
     * correctamente las listas con múltiples elementos y los saltos de párrafo.
     * @param {string} text - El texto en formato Markdown.
     * @returns {string} El texto convertido a HTML.
     */
    const markdownToHtml = (text) => {
        if (!text || typeof text !== 'string') return '';
        
        const lines = text.split('\n');
        let html = '';
        let inList = false;

        for (const line of lines) {
            let processedLine = line.trim();

            // Si es una línea vacía, la tratamos como un separador de párrafo
            if (processedLine === '') {
                if (inList) {
                    html += '</ul>';
                    inList = false;
                }
                html += '<br>';
                continue;
            }

            // Convertir negrita: **texto** -> <strong>texto</strong>
            processedLine = processedLine.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');

            // Manejo de listas
            if (processedLine.startsWith('- ')) {
                if (!inList) {
                    html += '<ul>';
                    inList = true;
                }
                // Quita el "- " del principio y lo envuelve en <li>
                html += `<li>${processedLine.substring(2)}</li>`;
            } else {
                // Si la línea anterior era una lista, la cerramos
                if (inList) {
                    html += '</ul>';
                    inList = false;
                }
                // Añade la línea como un párrafo simple
                html += `<p>${processedLine}</p>`;
            }
        }

        // Cierra la lista si el texto termina con una
        if (inList) {
            html += '</ul>';
        }

        return html;
    };

    // --- Event Listener para el envío del formulario ---
    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        showLoadingState();

        const propertyData = {
            type: document.getElementById('sa-property-type').value,
            location: document.getElementById('sa-location').value,
            area: document.getElementById('sa-area').value,
            rooms: document.getElementById('sa-rooms').value,
            baths: document.getElementById('sa-baths').value,
            state: document.getElementById('sa-state').value,
            features: document.getElementById('sa-extra-features').value
        };

        try {
            const response = await fetch('api/strategic_analyzer.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(propertyData)
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Error del servidor: ${response.status}. ${errorText}`);
            }

            const result = await response.json();
            
            if (result.success) {
                renderResults(result.data);
            } else {
                showErrorState(result.message);
            }

        } catch (error) {
            console.error("Error al ejecutar el agente de IA:", error);
            showErrorState("Ocurrió un error de conexión con el agente de IA.");
        }
    });

    /**
     * Muestra una interfaz de carga (esqueleto) en el contenedor de resultados.
     */
    const showLoadingState = () => {
        resultsContainer.innerHTML = `
            <div id="market-analysis-section" class="opacity-50 space-y-2">
                <h3 class="text-xl font-bold text-gray-800">1. Análisis de Mercado (RAG)</h3>
                <div class="flex items-center space-x-3"><div class="report-loading-spinner"></div><p>Buscando comparables y analizando el mercado...</p></div>
            </div>
            <div id="swot-analysis-section" class="opacity-50 space-y-2">
                <h3 class="text-xl font-bold text-gray-800">2. Análisis F.O.D.A.</h3>
                <div class="flex items-center space-x-3"><div class="report-loading-spinner"></div><p>Generando análisis estratégico...</p></div>
            </div>
            <div id="buyer-persona-section" class="opacity-50 space-y-2">
                <h3 class="text-xl font-bold text-gray-800">3. Perfil del Comprador Ideal</h3>
                <div class="flex items-center space-x-3"><div class="report-loading-spinner"></div><p>Definiendo el cliente objetivo...</p></div>
            </div>
            <div id="marketing-content-section" class="opacity-50 space-y-2">
                <h3 class="text-xl font-bold text-gray-800">4. Contenido de Marketing</h3>
                <div class="flex items-center space-x-3"><div class="report-loading-spinner"></div><p>Redactando textos de venta personalizados...</p></div>
            </div>
        `;
    };

    /**
     * Muestra un mensaje de error en el contenedor de resultados.
     * @param {string} message - El mensaje de error a mostrar.
     */
    const showErrorState = (message) => {
        resultsContainer.innerHTML = `<div class="bg-red-50 text-red-700 p-4 rounded-lg border border-red-200">${message}</div>`;
    };

    /**
     * Renderiza los resultados del análisis en sus respectivas secciones.
     * @param {object} data - El objeto con los datos del análisis.
     */
    const renderResults = (data) => {
        const renderSection = (id, title, content) => {
            const section = document.getElementById(id);
            if(section){
                // Se usa la nueva función robusta para convertir el texto a HTML
                const formattedContent = markdownToHtml(content);
                
                section.innerHTML = `
                    <h3 class="text-xl font-bold text-gray-800 mb-3">${title}</h3>
                    <div class="prose prose-sm max-w-none text-gray-700 p-4 bg-gray-50 rounded-lg border">${formattedContent}</div>`;
                section.classList.remove('opacity-50');
            }
        };

        renderSection('market-analysis-section', '1. Análisis de Mercado (RAG)', data.market_analysis);
        renderSection('swot-analysis-section', '2. Análisis F.O.D.A.', data.swot_analysis);
        renderSection('buyer-persona-section', '3. Perfil del Comprador Ideal', data.buyer_persona);
        renderSection('marketing-content-section', '4. Contenido de Marketing', data.marketing_content);
    };
};