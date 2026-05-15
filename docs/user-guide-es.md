# Guía De Uso De CreatorStack AI

[English](user-guide-en.md) | [Português](user-guide-pt.md) | Español

CreatorStack AI te ayuda a convertir vídeos de YouTube y archivos de audio subidos a la biblioteca de medios en borradores de entradas de WordPress. Cuando el proveedor de IA admite texto a voz, también puede crear una versión en audio de una entrada.

## Antes De Empezar

Necesitas:

- Un sitio de WordPress con las APIs de WordPress AI Client disponibles. Este plugin espera actualmente WordPress 7.0 beta o una versión posterior.
- Un proveedor de IA configurado en WordPress. Usa **Ajustes > Connectors** cuando esa pantalla esté disponible.
- El plugin **CreatorStack AI YouTube Connector** activado, una clave del conector de YouTube y el ID del canal de YouTube para los flujos basados en vídeos.
- Un cliente OAuth de Google de tipo **Web application** si quieres que CreatorStack AI lea subtítulos mediante la API oficial YouTube Captions.
- Una cuenta de WordPress con los permisos adecuados:
  - Los administradores pueden configurar los ajustes del plugin.
  - Autores, editores y administradores pueden generar y editar borradores cuando tienen `edit_posts`.

Para los flujos de audio, el proveedor de IA configurado debe admitir la capacidad necesaria:

- **YouTube to Post** requiere generación de texto.
- **Audio to Post** requiere entrada de audio y generación de texto.
- **Post to Audio** requiere texto a voz.

## Configurar El Proveedor De IA

1. En el administrador de WordPress, abre **Ajustes > Connectors**.
2. Instala o activa un conector de proveedor de IA.
3. Añade las credenciales requeridas por ese conector.
4. Vuelve a **Ajustes > AI Content Suite**.
5. En la sección **AI Provider**, haz clic en **Test AI Connection**.
6. Confirma que la prueba se completa correctamente antes de generar contenido.

Si WordPress no muestra **Ajustes > Connectors**, usa la pantalla de ajustes del AI Client enlazada desde la sección **AI Provider**.

## Configurar YouTube

1. Activa **CreatorStack AI YouTube Connector** en la pantalla de plugins.
2. Abre **Ajustes > AI Content Suite**.
3. En **YouTube Integration**, sigue el asistente de configuración.
4. Activa **YouTube Data API v3** en Google Cloud.
5. Crea una clave de YouTube Data API y añádela al conector **YouTube** en **Ajustes > Connectors**. También puedes usar la variable de entorno o constante PHP `YOUTUBE_DATA_API_KEY`.
6. Busca el ID de tu canal de YouTube y pégalo en **YouTube Channel ID** en **Ajustes > CreatorStack AI**.
7. Crea un cliente OAuth de Google con **Web application** como tipo de aplicación.
8. Copia el **Authorized redirect URI** que muestra WordPress y añádelo al cliente OAuth en Google Cloud.
9. Pega el contenido de `client_secret.json` en el asistente, haz clic en **Fill OAuth fields** y después en **Save Changes**.
10. Cuando la página se recargue, haz clic en **Connect YouTube** y completa el flujo de consentimiento de Google.

OAuth se usa para descargar subtítulos por la vía oficial. La cuenta de YouTube conectada debe poder editar los vídeos cuyos subtítulos quieres usar.

## Definir Los Valores Predeterminados De Contenido

En **Ajustes > AI Content Suite > Content Settings**, elige:

- **Default Output Language**: el idioma usado de forma predeterminada, salvo que lo cambies durante la generación.
- **Post Length**:
  - Short: alrededor de 600 a 900 palabras.
  - Medium: alrededor de 1.000 a 1.500 palabras.
  - Long: alrededor de 1.800 a 2.500 palabras.
- **Writing Persona**: orientación opcional sobre tono, audiencia, estructura o estilo.

En **AI Provider**, también puedes elegir un **Preferred AI Model**. Mantén **Automatic (recommended)** salvo que necesites un modelo específico. Si el modelo preferido no está disponible, el AI Client puede usar otro modelo compatible configurado.

## Crear Un Borrador Desde Un Vídeo De YouTube

Puedes empezar desde el widget del escritorio o desde la página completa de vídeos.

Desde el escritorio:

1. Abre **Dashboard**.
2. Busca el widget **YouTube to Blog**.
3. Haz clic en **Generate Post** en un vídeo reciente.

Desde la página completa:

1. Abre **Tube-to-Blog > YouTube Content**.
2. Explora los vídeos del canal.
3. Haz clic en **Generate Post** en el vídeo que quieras usar.
4. Usa **Load More Videos** si necesitas vídeos más antiguos.

Cuando se abra el modal de generación:

1. Elige el idioma de salida.
2. Ajusta la persona de escritura si hace falta.
3. Si faltan los subtítulos de YouTube o no son fiables, activa **Use a custom transcript instead of fetching captions** y pega al menos 50 caracteres de transcripción.
4. Haz clic en **Generate**.
5. Revisa el **Draft Preview**.
6. Haz clic en **Regenerate** si quieres una nueva versión.
7. Haz clic en **Save as Draft** cuando la vista previa esté lista.
8. Abre **Edit Draft** para revisar, editar y publicar la entrada.

Los borradores creados desde YouTube incluyen el artículo generado, una incrustación del vídeo, metadatos de origen y la miniatura de YouTube como imagen destacada cuando WordPress puede descargarla.

## Crear Un Borrador Desde Un Archivo De Audio

1. Abre **Tube-to-Blog > Audio to Post**.
2. Haz clic en **Create Draft From Audio**. Esto abre un nuevo borrador.
3. En la barra lateral del editor, abre el panel **AI Content Suite**.
4. En **Audio to Post**, haz clic en **Select Audio**.
5. Elige un archivo de audio de la biblioteca de medios o sube uno nuevo.
6. Selecciona el idioma de salida.
7. Ajusta la persona de escritura si hace falta.
8. Haz clic en **Generate Draft**.

CreatorStack AI actualiza el borrador actual con un título y contenido generados, y luego guarda el borrador.

Las extensiones de audio admitidas son `mp3`, `m4a`, `wav`, `ogg`, `webm`, `flac` y `aac`. El tamaño máximo es 25 MB o el límite de subida del sitio, el que sea menor.

## Generar Audio Desde Una Entrada

1. Abre una entrada o borrador existente.
2. En la barra lateral del editor, abre el panel **AI Content Suite**.
3. En **Post to Audio**, introduce un nombre de voz si tu proveedor admite selección de voz.
4. Haz clic en **Generate Audio**.

Si la entrada tiene cambios sin guardar, CreatorStack AI los guarda primero. Después crea un adjunto de audio, inserta un bloque de Audio al principio de la entrada, reemplaza cualquier bloque de audio anterior de CreatorStack AI, guarda el ID del adjunto en los metadatos de la entrada y vuelve a guardar la entrada.

## Revisar El Uso De IA

Los administradores pueden abrir **Ajustes > AI Content Suite > AI Usage** para ver generaciones recientes. La tabla muestra fecha, origen, estado, proveedor, modelo y uso de tokens cuando el proveedor informa de esos datos.

## Solución De Problemas

- **IA no disponible**: configura un proveedor de IA y ejecuta **Test AI Connection**.
- **No se encontraron subtítulos**: conecta OAuth de YouTube, elige un vídeo con subtítulos o usa una transcripción manual.
- **Transcripción manual demasiado corta**: pega al menos 50 caracteres.
- **Ya se está generando una entrada**: espera a que termine la generación actual. El plugin impide generaciones simultáneas por usuario.
- **Aviso de imagen destacada**: el borrador se creó, pero WordPress no pudo descargar la miniatura de YouTube.
- **Archivo de audio rechazado**: revisa la extensión, el tipo MIME y el tamaño del archivo.
- **Problemas en localhost**: localhost es compatible para desarrollo, pero WordPress necesita acceso HTTPS saliente a YouTube y al proveedor de IA configurado.
