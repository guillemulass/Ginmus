# GINMUS - Plataforma Inmobiliaria con IA

<p align="center">
  <img src="logoGinmus.png" alt="Logo de Ginmus" width="250"/>
  <br>
  <b>+ clientes - tiempo</b>
</p>


GINMUS es una plataforma web integral diseñada para agentes inmobiliarios, que utiliza el poder de la Inteligencia Artificial para optimizar tareas clave, desde el análisis de mercado y la valoración de propiedades hasta la generación de contenido y la búsqueda de clientes.

---

###  Capturas

<img width="1920" height="1080" alt="Captura Ginmus 1" src="https://github.com/user-attachments/assets/f6c1ffe8-95a4-4b73-a2b9-4fd5d0c012ee" />

<img width="1920" height="1080" alt="Captura Ginmus 4" src="https://github.com/user-attachments/assets/b0180328-00ed-42da-b90c-766437644257" />

---

### ✨ Características Principales

*   **📈 Panel Principal Dinámico:** Vista general con accesos directos y estadísticas clave de la base de datos de anuncios.
*   **🧠 Agente de Análisis Estratégico:** Un sistema de IA encadenado que realiza un análisis 360° de una propiedad:
    *   **Análisis de Mercado (RAG):** Busca comparables en una BBDD local (`anuncios.csv`) para contextualizar la propiedad.
    *   **Análisis F.O.D.A. y Perfil del Comprador:** Define la estrategia de venta y el público objetivo.
    *   **Contenido de Marketing:** Crea textos de venta personalizados para múltiples plataformas.
*   **📊 Búsqueda de Clientes:** Interfaz para visualizar, filtrar, ordenar y exportar a Excel una base de datos de anuncios.
*   **🌍 Informe de Entorno:** Genera informes detallados de una zona utilizando la API de Google Maps y un LLM para resumir la información.
*   **🤖 Agente Conversacional Avanzado (IA):** No es un simple chatbot, es un **agente inteligente** con capacidades complejas:
    *   **Lógica ReAct (Reason-Act):** Simula un proceso de razonamiento para decidir qué acción tomar ante una pregunta.
    *   **RAG Multi-Fuente:** Puede consultar información de **fuentes locales** (`anuncios.csv`, `conocimiento.txt`) y realizar **búsquedas en la web en tiempo real** usando la API de Tavily.
    *   **Guardián de Dominio:** Está entrenado para identificar y rechazar cortésmente preguntas que no estén relacionadas con el sector inmobiliario, manteniéndose siempre profesional.
    *   **Visualización del Pensamiento:** La interfaz muestra al usuario los pasos que el agente está tomando, ofreciendo total transparencia.
*   **✍️ Creación de Contenido y Valoración Predictiva (Simulada):** Módulos adicionales que completan el conjunto de herramientas del agente.

---

### 🚀 Stack Tecnológico

#### Frontend
*   **HTML5** y **CSS3**
*   **Tailwind CSS:** Para un diseño moderno y adaptable.
*   **JavaScript (Vanilla JS, ES6 Modules):** Lógica de la interfaz modular, carga perezosa (`lazy loading`) y peticiones `fetch` asíncronas.

#### Backend
*   **PHP 8+:** Gestiona toda la lógica del servidor, orquesta el flujo del agente de IA, actúa como proxy para las APIs y ejecuta scripts externos de forma segura.
*   **Python 3:** Utilizado para los scripts de Machine Learning (módulo de valoración).

#### APIs y Servicios Externos
*   **OpenRouter / OpenAI API:** Potencia todos los módulos de IA.
*   **Tavily API:** Proporciona al agente la capacidad de búsqueda en la web en tiempo real.
*   **Google Maps API (Geocoding & Places):** Para el módulo de Informe de Entorno.

---

### 💡 Arquitectura y Aspectos Destacados

*   **Arquitectura SPA (Single Page Application) simulada:** Interfaz dinámica gestionada con JavaScript sin recargas de página, con carga perezosa de módulos para un rendimiento óptimo.
*   **Agente ReAct con Router Lógico:** El corazón del chatbot. Implementa en PHP un flujo de agente de dos pasos:
    1.  **Router:** Una primera llamada al LLM analiza la pregunta y decide qué "herramienta" usar (`buscar_anuncios`, `buscar_documentos`, `buscar_en_la_web` o `fuera_de_tema`).
    2.  **Sintetizador:** Una segunda llamada utiliza el resultado de la herramienta para formular la respuesta final, asegurando que sea contextual y precisa.
*   **RAG Híbrido:** El sistema combina la recuperación de información de **fuentes de datos locales** (CSV, TXT) con **búsquedas externas en tiempo real**, permitiendo responder tanto a preguntas sobre datos privados como sobre eventos actuales.
*   **Seguridad en el Backend:**
    *   **Proxy Seguro:** Las claves API nunca se exponen en el frontend.
    *   **Prevención de Inyección de Comandos:** Uso de `escapeshellarg` para ejecutar scripts de Python de forma segura.
    *   **Sanitización de Entradas:** Todas las entradas del usuario son validadas y limpiadas.

---

### 🔧 Instalación y Puesta en Marcha

Para ejecutar este proyecto en un entorno local, necesitarás un servidor web compatible con PHP (como XAMPP) y Python 3.

1.  **Clona el repositorio:**
    ```bash
    git clone https://github.com/tu-usuario/ginmus.git
    cd ginmus
    ```
2.  **Configura las variables de entorno:**
    *   Crea un archivo `.env` en la raíz del proyecto a partir de `.env.example`.
    *   Añade tus propias claves API en el archivo `.env`.

3.  **Despliega en tu servidor local:**
    *   Copia los archivos del proyecto a la carpeta `htdocs` (en XAMPP) o la raíz de tu servidor.

4.  **Accede a la aplicación:**
    *   Abre tu navegador y navega a `http://localhost/nombre-de-tu-carpeta/public_html/plataforma/public.html`.

---

### 🔑 Configuración (`.env.example`)

Tu archivo `.env` debe tener la siguiente estructura:

```dotenv
# Clave API para el servicio de LLMs (GPT-4o, etc.)
OPENROUTER_API_KEY="sk-or-v1-..."

# Clave API para el servicio de búsqueda web Tavily
TAVILY_API_KEY="tvly-..."

# Clave API para Google Maps Platform (Geocoding API, Places API)
GOOGLE_MAPS_API_KEY="AIzaSy..."

# (Opcional) Clave API para el servicio de observabilidad Helicone
HELICONE_API_KEY="sk-helicone-..."
```

---

## 🧠 Personalización del Chatbot (RAG)

Una de las características más potentes de este proyecto es su sistema RAG (Retrieval-Augmented Generation). Puedes expandir y mejorar fácilmente el conocimiento del chatbot:

- **Para añadir más información legal o de procesos**: Simplemente edita el archivo `conocimiento.txt`. Añade nuevo texto sobre hipotecas, contratos, impuestos, etc. El chatbot podrá usar esta nueva información en sus respuestas.
- **Para actualizar los anuncios**: Modifica o reemplaza el archivo `anuncios.csv` con nuevos datos de propiedades.

---

### 📜 Licencia

Este proyecto se distribuye bajo la Licencia MIT. Consulta el archivo `LICENSE` para más detalles.

---

### 👤 Contacto

guillemuba13@gmail.com 

[[LinkedIn](https://www.linkedin.com/in/guillermo-mulas-batista-0185a52ab)] 
