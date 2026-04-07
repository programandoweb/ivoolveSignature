# PROYECTO: ivoolveSignature (Microservicio de Firma Electrónica)
# FRAMEWORK: Laravel 11
# COLOR BRANDING: #FE4FA2 (Pink Delice)
# ARQUITECTURA: Microservicio Independiente / Clean Code / SOLID

Actúa como un Desarrollador Senior Fullstack. Genera el código completo para el microservicio "ivoolveSignature".

## 1. REQUERIMIENTOS DE BASE DE DATOS
- Tabla `documents`: id (UUID), external_id, app_source, status (pending, partial, completed), final_hash.
- Tabla `signatures`: id, document_id (FK), version_number, user_id, user_name, otp_code, otp_verified_at, ip_address, user_agent, file_path, current_hash.

## 2. LÓGICA DE PDF (PdfProcessorService)
- Usa 'setasign/fpdi' y 'tecnickcom/tcpdf'.
- El "Sello de Evidencia" debe ir al pie de página o en un área blanca libre.
- **ESTILO VISUAL:** El texto del sello, los bordes del recuadro de evidencia y los elementos gráficos deben usar el color hexadecimal #FE4FA2.
- Debe incluir un código QR (generado con simple-qrcode) que apunte a la URL de validación.

## 3. API ENDPOINTS
- [POST] `/api/v1/signatures/initiate`: Recibe el PDF, guarda v0 y calcula Hash SHA-256.
- [POST] `/api/v1/signatures/verify`: 
    1. Valida el OTP de 6 dígitos.
    2. Carga la versión anterior del PDF.
    3. Estampa el sello con color #FE4FA2 conteniendo: Nombre, Cédula, Fecha/Hora, IP, Hash y QR.
    4. Guarda como nueva versión física (v1, v2...) y registra el nuevo Hash.
- [GET] `/api/v1/validate/{uuid}`: Vista Blade minimalista con el color #FE4FA2 confirmando la validez.

## 4. SERVICIOS Y SEGURIDAD
- `IntegrityService`: Valida que el archivo físico no haya sido alterado comparando el Hash antes de cada firma.
- `OtpService`: Genera y valida códigos (con Log::info para el envío).
- `Middleware`: Implementar una 'X-API-KEY' para proteger los endpoints.

## 5. INSTRUCCIONES DE INSTALACIÓN (INSTALL.md)
Genera los pasos para:
- Instalar dependencias: `composer require setasign/fpdi simplesoftwareio/simple-qrcode`.
- Configurar el sistema de archivos privado: `storage/app/private/signatures`.
- Ejecutar migraciones y `php artisan storage:link`.

Genera todos los archivos de la estructura de Laravel (Models, Migrations, Controllers, Services, Routes).