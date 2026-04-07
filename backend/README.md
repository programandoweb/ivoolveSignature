✒️ ivoolveSignature

ivoolveSignature es un microservicio independiente desarrollado en Laravel 11 diseñado para gestionar la confianza, integridad y validez legal de documentos digitales mediante Firma Electrónica Avanzada.

Este servicio actúa como un "Tercero de Confianza" para el ecosistema Ivoolve, permitiendo que empleados y colaboradores firmen documentos (como solicitudes de vacaciones o llamados de atención) con trazabilidad completa y seguridad criptográfica.
🚀 Características Principales

    Cadena de Custodia: Mantiene un historial de versiones inmutable (v0 Original, v1 Firma A, v2 Firma B).

    Validación OTP: Integración nativa para verificación de identidad mediante códigos de un solo uso enviados por WhatsApp/Email.

    Integridad Criptográfica: Generación y validación de Hashes SHA-256 para asegurar que ningún documento ha sido alterado entre firmas.

    Estampado de Evidencia: Inserta automáticamente en el PDF un sello de auditoría con:

        Nombre e identificación del firmante.

        Dirección IP y User Agent.

        Timestamp (Sello de tiempo).

        Código QR único para validación pública instantánea.

    Arquitectura Desacoplada: API RESTful lista para ser consumida por múltiples aplicaciones (Ivoolve Flow, Movex, Bululú).

🛠️ Stack Tecnológico

    Framework: Laravel 11

    Base de Datos: MariaDB

    Manipulación de PDF: FPDI / TCPDF

    Seguridad: API Token Authentication & SHA-256 Hashing

    QR Generation: Simple-QRCode

📋 Flujo de Firma

    Initiate: La App origen envía el PDF original. Se registra la v0.

    Challenge: El sistema genera un OTP y lo dispara al canal configurado.

    Sign: Al validar el OTP, el microservicio toma la última versión, estampa los datos de auditoría, genera la nueva versión física y actualiza el Hash global.

    Verify: Cualquier usuario con el documento físico puede escanear el QR para verificar su autenticidad en tiempo real.

    Desarrollado por Programandoweb.net para el ecosistema de gestión empresarial Ivoolve.
