# Documentacion de la API

## Resumen

`ivoolveSignature` expone una API para:

- registrar un PDF original y preparar la cadena de firmas;
- validar el OTP de un firmante y generar la nueva version fisica del documento;
- consultar la validez publica del documento desde una URL o desde el QR embebido.

Base path de la API:

```text
/api/v1
```

## Autenticacion

Los endpoints protegidos requieren la cabecera:

```http
X-API-KEY: tu-api-key
```

Configuracion relacionada:

- `SIGNATURE_API_KEY`
- `APP_URL`
- `SIGNATURE_STORAGE_DISK`
- `SIGNATURE_BRANDING_COLOR`
- `SIGNATURE_MAX_UPLOAD_SIZE_KB`
- `SIGNATURE_WHATSAPP_ENDPOINT`
- `SIGNATURE_WHATSAPP_TIMEOUT`
- `SIGNATURE_WHATSAPP_CONNECT_TIMEOUT`
- `SIGNATURE_WHATSAPP_VERIFY_SSL`

Si `SIGNATURE_API_KEY` no esta configurada, la API responde:

```json
{
  "message": "The signature API key is not configured."
}
```

con estado `500`.

Si la API key es invalida o no fue enviada, la API responde:

```json
{
  "message": "Invalid API key."
}
```

con estado `401`.

## Flujo recomendado

1. Consumir `POST /api/v1/signatures/initiate` para registrar el PDF original y los firmantes.
2. Si el firmante tiene `phone_number` y existe `SIGNATURE_WHATSAPP_ENDPOINT`, el OTP se envia por WhatsApp. Si no, queda registrado en el log de Laravel.
3. Consumir `POST /api/v1/signatures/verify` una vez por cada firmante, respetando el orden de version.
4. Usar la `validation_url` devuelta por la API o el QR dentro del PDF para validar el documento.

## Estados del documento

- `pending`: solo existe la version `v0` y aun no se ha aplicado ninguna firma.
- `partial`: ya existe al menos una firma aplicada, pero faltan firmas pendientes.
- `completed`: todas las firmas registradas fueron aplicadas.

## Versionado del documento

- `v0`: PDF original cargado en `initiate`.
- `v1`, `v2`, `v3`...: nuevas versiones fisicas creadas al verificar cada firma.

La API no permite saltarse el orden. Si una firma previa sigue pendiente, el siguiente firmante no podra firmar.

## 1. Iniciar proceso de firma

**Endpoint**

```http
POST /api/v1/signatures/initiate
```

**Headers**

```http
X-API-KEY: tu-api-key
Accept: application/json
Content-Type: multipart/form-data
```

**Body**

Campos requeridos:

- `external_id`: identificador externo del documento en el sistema origen.
- `app_source`: nombre de la aplicacion origen.
- `pdf`: archivo PDF original.
- `signers`: arreglo de firmantes.
- `signers.*.user_id`: identificador del firmante.
- `signers.*.user_name`: nombre del firmante.
- `signers.*.phone_number`: numero de WhatsApp del firmante. Es opcional, pero recomendado para envio automatico del OTP.

**Ejemplo cURL**

```bash
curl --request POST "http://localhost/api/v1/signatures/initiate" \
  --header "X-API-KEY: tu-api-key" \
  --header "Accept: application/json" \
  --form "external_id=VAC-2026-0001" \
  --form "app_source=ivoolve-flow" \
  --form "pdf=@/ruta/contrato.pdf" \
  --form "signers[0][user_id]=10101010" \
  --form "signers[0][user_name]=Ana Lopez" \
  --form "signers[0][phone_number]=573001112233" \
  --form "signers[1][user_id]=20202020" \
  --form "signers[1][user_name]=Luis Perez" \
  --form "signers[1][phone_number]=573009998877"
```

**Respuesta exitosa**

Estado: `201 Created`

```json
{
  "message": "Document registration completed successfully.",
  "data": {
    "document_id": "019d6550-6696-7162-bc40-aa53cba96960",
    "external_id": "VAC-2026-0001",
    "app_source": "ivoolve-flow",
    "status": "pending",
    "final_hash": "c8dca66c8f67f0c9fe9dfd27db53f8f403f0d7f9921974f2f0e17ef7f7b52f60",
    "validation_url": "http://localhost/api/v1/validate/019d6550-6696-7162-bc40-aa53cba96960",
    "signatures": [
      {
        "id": 2,
        "version_number": 1,
        "user_id": "10101010",
        "user_name": "Ana Lopez",
        "phone_number": "573001112233",
        "status": "pending"
      },
      {
        "id": 3,
        "version_number": 2,
        "user_id": "20202020",
        "user_name": "Luis Perez",
        "phone_number": "573009998877",
        "status": "pending"
      }
    ]
  }
}
```

**Notas**

- Al iniciar, el sistema guarda la version original como `v0-original.pdf`.
- Se calcula y registra el `final_hash` inicial del archivo original.
- Se genera un OTP por cada firmante.
- Si existe `signers.*.phone_number` y `SIGNATURE_WHATSAPP_ENDPOINT`, el OTP se envia al endpoint de WhatsApp.
- Siempre se registra trazabilidad en logs mediante `Log::info`.

**Errores comunes**

Estado `422 Unprocessable Entity`

Ejemplos:

```json
{
  "message": "The signers field is required.",
  "errors": {
    "signers": [
      "Debes enviar al menos un firmante."
    ]
  }
}
```

```json
{
  "message": "The pdf field must be a file of type: application/pdf.",
  "errors": {
    "pdf": [
      "El archivo debe ser un PDF valido."
    ]
  }
}
```

## 2. Verificar OTP y firmar

**Endpoint**

```http
POST /api/v1/signatures/verify
```

**Headers**

```http
X-API-KEY: tu-api-key
Accept: application/json
Content-Type: application/json
```

**Body**

- `document_id`: UUID del documento.
- `user_id`: identificador del firmante pendiente.
- `otp_code`: codigo OTP de 6 digitos.

**Ejemplo cURL**

```bash
curl --request POST "http://localhost/api/v1/signatures/verify" \
  --header "X-API-KEY: tu-api-key" \
  --header "Accept: application/json" \
  --header "Content-Type: application/json" \
  --data "{
    \"document_id\": \"019d6550-6696-7162-bc40-aa53cba96960\",
    \"user_id\": \"10101010\",
    \"otp_code\": \"123456\"
  }"
```

**Que hace este endpoint**

- valida el OTP;
- valida la integridad del PDF anterior antes de firmar;
- toma la ultima version fisica disponible;
- inserta el sello de evidencia en color `#FE4FA2`;
- inserta QR apuntando a la URL de validacion;
- guarda una nueva version fisica del PDF;
- recalcula el hash SHA-256;
- actualiza el estado del documento.

**Respuesta exitosa**

Estado: `200 OK`

```json
{
  "message": "The electronic signature was applied successfully.",
  "data": {
    "document_id": "019d6550-6696-7162-bc40-aa53cba96960",
    "status": "partial",
    "final_hash": "53b350fcb9d4ef8b29d9472ff0f69d9a4b1f6e2f3180f34fef6d122b2d82f6e1",
    "validation_url": "http://localhost/api/v1/validate/019d6550-6696-7162-bc40-aa53cba96960",
    "signature": {
      "id": 2,
      "version_number": 1,
      "user_id": "10101010",
      "user_name": "Ana Lopez",
      "signed_at": "2026-04-06T23:10:00+00:00",
      "file_path": "019d6550-6696-7162-bc40-aa53cba96960/v1-signed.pdf",
      "current_hash": "53b350fcb9d4ef8b29d9472ff0f69d9a4b1f6e2f3180f34fef6d122b2d82f6e1"
    }
  }
}
```

Si era la ultima firma pendiente, `status` cambiara a `completed`.

**Errores comunes**

Estado `422 Unprocessable Entity`

OTP invalido o ya consumido:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "otp_code": [
      "The provided OTP code is invalid or has already been consumed."
    ]
  }
}
```

Firma fuera de secuencia:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "document_id": [
      "The signature cannot be applied yet because previous versions are still pending."
    ]
  }
}
```

No existe firma pendiente para el usuario:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "user_id": [
      "No pending signature was found for this user in the requested document."
    ]
  }
}
```

Estado `409 Conflict`

Si el archivo fisico fue alterado y falla la validacion de integridad:

```json
{
  "message": "The document integrity check failed. The physical file hash does not match the stored version hash."
}
```

## 3. Validacion publica del documento

**Endpoint**

```http
GET /api/v1/validate/{document_id}
```

**Autenticacion**

No requiere `X-API-KEY`.

**Respuesta**

- devuelve una vista Blade HTML;
- muestra estado del documento;
- muestra hash final;
- muestra cantidad de firmas aplicadas;
- muestra si la ultima version fisica coincide con el hash final registrado.

**Uso esperado**

- consumo humano en navegador;
- acceso desde el QR embebido en el PDF;
- confirmacion publica de validez del documento.

## OTP y trazabilidad

Cuando `phone_number` esta presente y `SIGNATURE_WHATSAPP_ENDPOINT` esta configurado, el microservicio envia un `POST` JSON al endpoint de WhatsApp con esta estructura:

```json
{
  "to": "573001112233",
  "message": "Hola Ana Lopez, tu codigo OTP para firmar el documento VAC-2026-0001 es 123456. No lo compartas con nadie."
}
```

Si no se envia `phone_number`, el OTP queda disponible solo en logs.

Busca entradas similares a esta en `storage/logs/laravel.log`:

```text
Signature OTP dispatched.
```

Cada entrada incluye:

- `document_id`
- `signature_id`
- `version_number`
- `user_id`
- `phone_number`
- `otp_code`
- `channel`

## Datos que se estampan en el PDF

Cada firma agrega un sello de evidencia con:

- nombre del firmante;
- cedula o identificador;
- fecha y hora de firma;
- direccion IP;
- hash de la version previa;
- QR con enlace de validacion.

El sello visual usa el color corporativo:

```text
#FE4FA2
```

## Consideraciones de integracion

- `initiate` debe enviarse como `multipart/form-data`.
- `verify` debe enviarse como JSON.
- `document_id` es el UUID interno del microservicio; no usar `external_id` para firmar.
- `external_id` sirve para relacionar el documento con tu sistema origen.
- Para envio automatico del OTP, envia `signers.*.phone_number`.
- El QR siempre apunta a la `validation_url` del documento.
- El hash final cambia despues de cada firma aplicada.

## Rutas disponibles

| Metodo | Ruta | Protegida |
| --- | --- | --- |
| `POST` | `/api/v1/signatures/initiate` | Si |
| `POST` | `/api/v1/signatures/verify` | Si |
| `GET` | `/api/v1/validate/{uuid}` | No |
