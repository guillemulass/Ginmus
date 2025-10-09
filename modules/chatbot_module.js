/**
 * @file chatbot_module.js
 * @description Maneja toda la lógica del frontend para el módulo de Chatbot IA.
 * Se encarga de construir la interfaz, enviar los mensajes del usuario al backend,
 * y renderizar las respuestas del agente de IA.
 */

// Se define la función de inicialización dentro del objeto global 'window' para que pueda ser
// llamada desde el script principal en el archivo public.html.
window.initChatbotModule = () => {
    // Busca el div principal que actuará como contenedor para la aplicación del chatbot.
    const chatbotAppContainer = document.getElementById('chatbot-app-container');
    // Si el contenedor no existe en la página, la función se detiene para evitar errores.
    if (!chatbotAppContainer) return;

    // Se inyecta la estructura HTML completa del chatbot dentro del contenedor.
    // Esto mantiene el HTML principal limpio y permite cargar el módulo dinámicamente.
    chatbotAppContainer.innerHTML = `
        <div class="p-4 bg-white rounded-t-xl h-full flex flex-col">
            <!-- 1. Área de historial de chat: se llenará con las burbujas de mensajes -->
            <div id="chat-history" class="flex-1 overflow-y-auto p-4 space-y-4">
                <!-- Mensaje de bienvenida inicial del bot -->
                <div class="message-bubble bot-message">
                    Bienvenido/a. Soy tu asistente inmobiliario con RAG. Puedo consultar mi base de datos de anuncios y documentos. ¿En qué te ayudo?
                </div>
            </div>
            
            <!-- 2. Área de entrada del usuario -->
            <div class="border-t border-slate-200 p-4 bg-white rounded-b-xl">
                 <form id="chatbot-form" class="flex items-center gap-2">
                    <!-- Campo de texto para que el usuario escriba -->
                    <input type="text" id="user-input" class="flex-1 w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Pregunta sobre anuncios, leyes, etc." autocomplete="off">
                    <!-- Botón para enviar el mensaje -->
                    <button type="submit" id="send-button" class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        Enviar
                    </button>
                </form>
                <!-- 3. Área de estado: muestra lo que el agente está "pensando" -->
                <div id="chatbot-status" class="mt-2 text-xs text-slate-500 h-4"></div>
            </div>
        </div>
    `;

    // Una vez creado el HTML, obtenemos referencias a los elementos clave para poder manipularlos.
    const chatHistory = document.getElementById('chat-history');
    const userInput = document.getElementById('user-input');
    const sendButton = document.getElementById('send-button');
    const chatbotStatus = document.getElementById('chatbot-status');
    const chatForm = document.getElementById('chatbot-form');

    /**
     * Crea y añade una nueva burbuja de mensaje al historial del chat.
     * @param {object} options - Un objeto que contiene los detalles del mensaje.
     * @param {string} options.sender - Quién envía el mensaje ('user' o 'bot').
     * @param {string} options.content - El contenido de texto del mensaje.
     */
    const appendMessage = (options) => {
        // Guarda de seguridad: Esta función está diseñada para dibujar SÓLO los mensajes del usuario y la respuesta final del bot.
        if (options.sender !== 'user' && options.sender !== 'bot') {
            return; 
        }

        const messageElement = document.createElement('div');
        messageElement.classList.add('message-bubble');
        
        let formattedContent = options.content.replace(/\n\n/g, '<br><br>').replace(/\n/g, '<br>');
        formattedContent = formattedContent.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        formattedContent = formattedContent.replace(/\*(.*?)\*/g, '<em>$1</em>');
        formattedContent = formattedContent.replace(/((?:<br>|^)\s*[-\*]\s.*)+/g, (match) => {
            const items = match.trim().split(/<br>\s*[-\*]\s|^\s*[-\*]\s/);
            return '<ul>' + items.filter(item => item.trim() !== '').map(item => `<li>${item.trim()}</li>`).join('') + '</ul>';
        });

        if (options.sender === 'user') {
            messageElement.classList.add('user-message');
        } else if (options.sender === 'bot') {
            messageElement.classList.add('bot-message');
        }
        
        messageElement.innerHTML = formattedContent;
        chatHistory.appendChild(messageElement);
        chatHistory.scrollTop = chatHistory.scrollHeight;
    };

    /**
     * Orquesta todo el proceso de enviar un mensaje y recibir una respuesta.
     */
    const sendMessage = async () => {
        const message = userInput.value.trim();
        if (message === '') return;

        // 1. Muestra el mensaje del usuario
        appendMessage({ sender: 'user', content: message });

        // 2. Prepara la interfaz para la espera
        userInput.value = '';
        sendButton.disabled = true;
        chatbotStatus.textContent = 'Agente pensando...';

        try {
            // 3. Realiza la llamada a la API
            const response = await fetch('api/chatbot_RAG_agent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: message }),
            });

            if (!response.ok) throw new Error(`Error del servidor: ${response.status}`);
            
            const data = await response.json();

            // 4. Procesa la respuesta del backend.
            if (data.success) {
                // ===================== INICIO DE LA CORRECCIÓN =====================
                // La lógica clave está aquí. Iteramos sobre el "proceso de pensamiento"
                // pero en lugar de llamar a `appendMessage`, actualizamos el `chatbotStatus`.
                if (data.thinking_process && data.thinking_process.length > 0) {
                    for (const step of data.thinking_process) {
                        // Hacemos una pequeña pausa para que el cambio de estado sea visible
                        await new Promise(res => setTimeout(res, 800));
                        
                        // **CAMBIO IMPORTANTE**: Actualizamos el texto de estado, NO creamos una burbuja de chat.
                        chatbotStatus.textContent = step.title;
                    }
                }
                
                // Damos una última pausa antes de mostrar la respuesta final.
                await new Promise(res => setTimeout(res, 500));
                chatbotStatus.textContent = 'Respuesta generada.';
                
                // **CAMBIO IMPORTANTE**: Ahora, y solo ahora, llamamos a `appendMessage`
                // con la respuesta final y definitiva.
                appendMessage({ sender: 'bot', content: data.final_response });
                // ====================== FIN DE LA CORRECCIÓN ======================

            } else {
                // Si el backend reportó un error, lo mostramos
                appendMessage({ sender: 'bot', content: `Error: ${data.error || 'No se pudo procesar la solicitud.'}` });
            }
            
        } catch (error) {
            // Error de red
            appendMessage({ sender: 'bot', content: 'Error de conexión. Por favor, inténtalo de nuevo.' });
            console.error('Error al enviar mensaje al agente:', error);
        } finally {
            // 5. Limpieza final.
            setTimeout(() => {
                chatbotStatus.textContent = ''; // Limpiamos el estado después de un par de segundos
            }, 2000);
            sendButton.disabled = false; // Reactivamos el botón
            userInput.focus(); // Devolvemos el foco al input
        }
    }

    // Asigna la función `sendMessage` al evento 'submit' del formulario.
    chatForm.addEventListener('submit', (e) => {
        e.preventDefault();
        sendMessage();
    });
};