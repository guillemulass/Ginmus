/**
 * @file social_media_module.js
 * @description Gestiona la generación de contenido para redes sociales y portales
 * a partir de los datos de una propiedad, usando un servicio de IA en el backend.
 */

/**
 * Inicializa el módulo de generación de contenido para redes sociales.
 */
window.initSocialMediaModule = () => {
    // --- Referencias a elementos del DOM ---
    const form = document.getElementById('social-media-form');
    const resultsContainer = document.getElementById('social-media-results');

    if (!form || !resultsContainer) {
        console.error("ERROR JS: Faltan elementos del DOM #social-media-form o #social-media-results.");
        return;
    }

    // --- Event Listener para el envío del formulario ---
    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        // Muestra un indicador de carga.
        resultsContainer.innerHTML = `
            <div class="text-center">
                <p class="text-gray-600 mb-2">Generando textos con IA, por favor espera...</p>
                <div class="report-loading-spinner mx-auto"></div>
            </div>`;

        // Recoge los datos del formulario.
        const propertyData = {
            propertyType: document.getElementById('sm-property-type').value,
            location: document.getElementById('sm-location').value,
            area: document.getElementById('sm-area').value,
            rooms: document.getElementById('sm-rooms').value,
            baths: document.getElementById('sm-baths').value,
            state: document.getElementById('sm-state').value,
            extraFeatures: document.getElementById('sm-extra-features').value
        };

        const selectedPlatforms = Array.from(document.querySelectorAll('input[name="platform"]:checked')).map(cb => cb.value);

        // Valida que al menos una plataforma esté seleccionada.
        if (selectedPlatforms.length === 0) {
            resultsContainer.innerHTML = `<p class="text-red-600">Por favor, selecciona al menos una plataforma.</p>`;
            return;
        }

        // Construye una descripción unificada para enviar al backend.
        const description = `
            - Tipo de inmueble: ${propertyData.propertyType}
            - Ubicación: ${propertyData.location}
            - Superficie: ${propertyData.area} m²
            - Habitaciones: ${propertyData.rooms}
            - Baños: ${propertyData.baths}
            - Estado: ${propertyData.state}
            - Características adicionales: ${propertyData.extraFeatures || 'No especificadas'}
        `.trim();

        try {
            // Llama al endpoint de backend.
            const response = await fetch('api/social_media_generator.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ description, platforms: selectedPlatforms })
            });
            
            if (!response.ok) {
                throw new Error(`Error del servidor: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                renderResults(result.data);
            } else {
                resultsContainer.innerHTML = `<p class="text-red-600">Error: ${result.message}</p>`;
            }

        } catch (error) {
            console.error("Error al generar contenido:", error);
            resultsContainer.innerHTML = `<p class="text-red-600">Ocurrió un error de conexión.</p>`;
        }
    });

    /**
     * Renderiza los resultados generados por la IA en tarjetas separadas por plataforma.
     * @param {object} data - Un objeto donde las claves son las plataformas y los valores son los textos.
     */
    const renderResults = (data) => {
        resultsContainer.innerHTML = ''; // Limpia el contenedor
        
        const platformNames = {
            facebook: 'Facebook',
            instagram: 'Instagram',
            portales: 'Portales Inmobiliarios'
        };

        for (const platform in data) {
            const card = document.createElement('div');
            card.className = 'bg-gray-50 p-4 rounded-lg border border-gray-200 mb-4';
            card.innerHTML = `
                <div class="flex justify-between items-center mb-2">
                    <h4 class="text-lg font-semibold text-gray-800">${platformNames[platform] || platform}</h4>
                    <button class="copy-button bg-sky-100 text-sky-700 text-xs font-medium py-1 px-3 rounded-md hover:bg-sky-200 transition">
                        Copiar
                    </button>
                </div>
                <p class="text-gray-700 text-sm whitespace-pre-wrap">${data[platform]}</p>
            `;
            resultsContainer.appendChild(card);
        }

        // Añade la funcionalidad de "copiar al portapapeles" a los botones.
        document.querySelectorAll('.copy-button').forEach(button => {
            button.addEventListener('click', (e) => {
                const textToCopy = e.target.closest('div.bg-gray-50').querySelector('p').textContent;
                navigator.clipboard.writeText(textToCopy).then(() => {
                    e.target.textContent = '¡Copiado!';
                    setTimeout(() => { e.target.textContent = 'Copiar'; }, 2000);
                }).catch(err => {
                    console.error('Error al copiar texto: ', err);
                    alert('No se pudo copiar el texto.');
                });
            });
        });
    };
};