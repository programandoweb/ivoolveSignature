# Instalacion de ivoolveSignature

## 1. Instalar dependencias PHP

```bash
composer require setasign/fpdi tecnickcom/tcpdf simplesoftwareio/simple-qrcode
```

## 2. Configurar variables de entorno

Define al menos estas variables en `.env`:

```dotenv
APP_URL=http://localhost
SIGNATURE_API_KEY=tu-api-key-segura
SIGNATURE_STORAGE_DISK=signatures
SIGNATURE_BRANDING_COLOR=#FE4FA2
SIGNATURE_MAX_UPLOAD_SIZE_KB=20480
SIGNATURE_WHATSAPP_ENDPOINT=https://app2.delicetiendavirtual.com/whatsapp/delice_bot/send
SIGNATURE_WHATSAPP_TIMEOUT=10
SIGNATURE_WHATSAPP_CONNECT_TIMEOUT=5
SIGNATURE_WHATSAPP_VERIFY_SSL=true
```

## 3. Preparar almacenamiento privado

Crear el directorio donde quedaran las versiones del PDF:

```bash
mkdir -p storage/app/private/signatures
```

En Windows PowerShell:

```powershell
New-Item -ItemType Directory -Force storage/app/private/signatures
```

## 4. Ejecutar migraciones y enlace publico

```bash
php artisan migrate
php artisan storage:link
```

## 5. Consumir la API protegida

Enviar la cabecera obligatoria en los endpoints protegidos:

```http
X-API-KEY: tu-api-key-segura
```

## 6. Endpoints disponibles

- `POST /api/v1/signatures/initiate`
- `POST /api/v1/signatures/verify`
- `GET /api/v1/validate/{uuid}`

## 7. Flujo minimo esperado

1. Enviar el PDF original, `external_id`, `app_source` y el arreglo `signers` a `/api/v1/signatures/initiate`.
2. Si cada firmante incluye `phone_number`, el OTP sera enviado al endpoint de WhatsApp configurado en `.env`. Si no se incluye, el OTP quedara registrado en el log.
3. Confirmar cada firma con `/api/v1/signatures/verify` usando `document_id`, `user_id` y `otp_code`.
4. Validar el documento desde la URL entregada por el microservicio o usando el QR embebido en el PDF.
