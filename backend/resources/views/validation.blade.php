<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validacion de Documento</title>
    <style>
        :root {
            --brand: {{ $brandColor }};
            --brand-soft: rgba(254, 79, 162, 0.12);
            --brand-line: rgba(254, 79, 162, 0.22);
            --ink: #23161f;
            --paper: #fffafc;
            --muted: #745866;
            --success: #18794e;
            --danger: #b42355;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: "Segoe UI", "Helvetica Neue", sans-serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(254, 79, 162, 0.18), transparent 28%),
                linear-gradient(145deg, #fff7fb 0%, #fff 45%, #fff5fa 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .card {
            width: min(760px, 100%);
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid var(--brand-line);
            border-radius: 24px;
            box-shadow: 0 24px 80px rgba(90, 30, 59, 0.12);
            overflow: hidden;
        }

        .hero {
            padding: 28px 28px 20px;
            background: linear-gradient(135deg, rgba(254, 79, 162, 0.16), rgba(254, 79, 162, 0.03));
            border-bottom: 1px solid var(--brand-line);
        }

        .eyebrow {
            margin: 0 0 10px;
            font-size: 0.78rem;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            color: var(--brand);
            font-weight: 700;
        }

        h1 {
            margin: 0;
            font-size: clamp(1.8rem, 3vw, 2.6rem);
            line-height: 1.05;
        }

        .status {
            margin-top: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-radius: 999px;
            font-size: 0.95rem;
            font-weight: 700;
            background: {{ $isValid ? 'rgba(24,121,78,0.14)' : 'rgba(180,35,85,0.12)' }};
            color: {{ $isValid ? 'var(--success)' : 'var(--danger)' }};
        }

        .status-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: currentColor;
        }

        .content {
            padding: 28px;
            display: grid;
            gap: 18px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 14px;
        }

        .metric,
        .panel {
            background: var(--paper);
            border: 1px solid var(--brand-line);
            border-radius: 18px;
            padding: 16px 18px;
        }

        .label {
            margin: 0 0 8px;
            font-size: 0.78rem;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--muted);
        }

        .value {
            margin: 0;
            font-size: 1rem;
            line-height: 1.45;
            word-break: break-word;
        }

        .hash {
            font-family: Consolas, "Courier New", monospace;
            font-size: 0.94rem;
        }

        .signatures {
            display: grid;
            gap: 12px;
        }

        .signature-row {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid rgba(254, 79, 162, 0.12);
        }

        .signature-row:last-child {
            border-bottom: 0;
            padding-bottom: 0;
        }

        .signature-row:first-child {
            padding-top: 0;
        }

        .signature-name {
            margin: 0 0 4px;
            font-weight: 700;
            color: var(--brand);
        }

        .signature-meta {
            margin: 0;
            color: var(--muted);
            font-size: 0.92rem;
        }

        .chip {
            padding: 8px 12px;
            border-radius: 999px;
            background: var(--brand-soft);
            color: var(--brand);
            font-size: 0.84rem;
            font-weight: 700;
            white-space: nowrap;
        }

        @media (max-width: 640px) {
            .hero,
            .content {
                padding: 22px;
            }

            .signature-row {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <main class="card">
        <section class="hero">
            <p class="eyebrow">ivoolveSignature</p>
            <h1>{{ $isValid ? 'Documento valido y verificable' : 'Documento con alerta de integridad' }}</h1>
            <div class="status">
                <span class="status-dot"></span>
                <span>{{ $integrityMessage }}</span>
            </div>
        </section>

        <section class="content">
            <div class="grid">
                <article class="metric">
                    <p class="label">Documento</p>
                    <p class="value">{{ $document->id }}</p>
                </article>
                <article class="metric">
                    <p class="label">Origen</p>
                    <p class="value">{{ $document->app_source }}</p>
                </article>
                <article class="metric">
                    <p class="label">Referencia externa</p>
                    <p class="value">{{ $document->external_id }}</p>
                </article>
                <article class="metric">
                    <p class="label">Estado</p>
                    <p class="value">{{ strtoupper($document->status->value) }}</p>
                </article>
                <article class="metric">
                    <p class="label">Firmas aplicadas</p>
                    <p class="value">{{ $completedSignatures }} / {{ $totalSignatures }}</p>
                </article>
                <article class="metric">
                    <p class="label">Ultima version</p>
                    <p class="value">v{{ $latestVersion?->version_number ?? 'N/A' }}</p>
                </article>
            </div>

            <article class="panel">
                <p class="label">Hash final</p>
                <p class="value hash">{{ $document->final_hash ?? 'No disponible' }}</p>
            </article>

            <article class="panel">
                <p class="label">Linea de firmas</p>
                <div class="signatures">
                    @foreach($document->signatures->where('version_number', '>', 0) as $signature)
                        <div class="signature-row">
                            <div>
                                <p class="signature-name">{{ $signature->user_name }}</p>
                                <p class="signature-meta">
                                    Cedula {{ $signature->user_id }}
                                    @if($signature->otp_verified_at)
                                        · {{ $signature->otp_verified_at->format('Y-m-d H:i:s') }}
                                    @endif
                                </p>
                            </div>
                            <span class="chip">{{ $signature->otp_verified_at ? 'Firmado v'.$signature->version_number : 'Pendiente v'.$signature->version_number }}</span>
                        </div>
                    @endforeach
                </div>
            </article>
        </section>
    </main>
</body>
</html>
