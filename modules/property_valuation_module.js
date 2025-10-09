/**
 * @file property_valuation_module.js
 * @description Maneja la lógica para el módulo de Valoración Predictiva de Inmuebles.
 * Envía los datos de una propiedad a un script de backend que ejecuta un modelo
 * de Machine Learning y muestra la estimación resultante.
 */

/**
 * Inicializa el módulo de valoración de propiedades.
 */
window.initPropertyValuationModule = () => {
    // --- Referencias a elementos del DOM ---
    const valuationForm = document.getElementById('valuation-form');
    // NOTA: Se ha asumido que 'valuation-status' y 'valuation-output' existen
    // según el script original. Asegúrate de que estén en public.html.
    const valuationStatus = document.getElementById('valuation-status');
    const valuationOutputDiv = document.getElementById('valuation-output');

    if (!valuationForm || !valuationStatus || !valuationOutputDiv) {
        console.error('ERROR JS: Faltan elementos del DOM para el módulo de valoración (#valuation-form, #valuation-status, #valuation-output).');
        return;
    }

    // --- Event Listener para el envío del formulario ---
    valuationForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        
        // Actualiza la UI al estado de "cargando".
        valuationStatus.textContent = 'Calculando valoración, por favor espera...';
        valuationStatus.className = 'text-sm text-blue-600 italic';
        valuationOutputDiv.innerHTML = ''; // Limpia resultados anteriores

        // Recoge los datos del formulario.
        const formData = new FormData(valuationForm);
        const data = {
            address: formData.get('val-address'),
            property_type: formData.get('val-property-type'),
            area: parseInt(formData.get('val-area'), 10),
            rooms: parseInt(formData.get('val-rooms'), 10),
            baths: parseInt(formData.get('val-baths'), 10),
            state: formData.get('val-state')
        };

        // Validación simple de los datos.
        if (!data.address || !data.area || data.area <= 0) {
            valuationStatus.textContent = 'Por favor, completa la dirección y una superficie válida.';
            valuationStatus.className = 'text-sm text-red-600 italic';
            return;
        }

        try {
            // Llama al script PHP que ejecuta el modelo de valoración.
            const response = await fetch('api/property_valuation.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });

            const result = await response.json();

            if (result.success) {
                // Muestra los resultados de la valoración exitosa.
                valuationStatus.textContent = 'Estimación completada:';
                valuationStatus.className = 'text-sm text-green-700 font-semibold';
                
                let outputHTML = `<p class="text-2xl font-bold text-sky-700">Precio Estimado: ${result.estimated_price_formatted} €</p>`;
                if (result.llm_explanation) {
                    outputHTML += `<h4 class="text-md font-semibold mt-4 mb-2 text-gray-700">Información Adicional:</h4>`;
                    outputHTML += `<p class="text-sm text-gray-600 italic whitespace-pre-line">${result.llm_explanation}</p>`;
                }
                outputHTML += `<p class="mt-4 text-xs text-gray-500">Nota: Esta es una estimación basada en un modelo de Machine Learning. Consulta a un profesional para una valoración precisa.</p>`;
                
                valuationOutputDiv.innerHTML = outputHTML;
            } else {
                // Muestra un mensaje de error si la valoración falla.
                valuationStatus.textContent = 'Error en la valoración:';
                valuationStatus.className = 'text-sm text-red-600 font-semibold';
                valuationOutputDiv.innerHTML = `<p class="text-red-700">${result.message || 'No se pudo obtener una valoración.'}</p>`;
            }
        } catch (error) {
            // Maneja errores de red o de parseo de JSON.
            console.error('Error en la solicitud de valoración:', error);
            valuationStatus.textContent = 'Error de conexión.';
            valuationStatus.className = 'text-sm text-red-600 italic';
            valuationOutputDiv.innerHTML = `<p class="text-red-700">Ocurrió un error al contactar el servidor.</p>`;
        }
    });
};