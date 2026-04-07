<?php

namespace App\Services;

use setasign\Fpdi\Tcpdf\Fpdi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PdfProcessorService
{
    public function stamp(string $sourcePath, string $destinationPath, array $stampData, string $validationUrl): void
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->SetCreator(config('app.name'));
        $pdf->SetAuthor(config('app.name'));
        $pdf->SetTitle('ivoolveSignature Evidence');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetAutoPageBreak(false);

        $pageCount = $pdf->setSourceFile($sourcePath);
        $qrCodeBinary = $this->makeQrCode($validationUrl);

        for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
            $templateId = $pdf->importPage($pageNumber);
            $pageSize = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($pageSize['orientation'], [$pageSize['width'], $pageSize['height']]);
            $pdf->useTemplate($templateId);

            if ($pageNumber === $pageCount) {
                $this->renderEvidenceStamp($pdf, $pageSize, $stampData, $validationUrl, $qrCodeBinary);
            }
        }

        $pdf->Output($destinationPath, 'F');
    }

    private function renderEvidenceStamp(Fpdi $pdf, array $pageSize, array $stampData, string $validationUrl, string $qrCodeBinary): void
    {
        [$red, $green, $blue] = $this->hexToRgb((string) config('signature.branding_color', '#FE4FA2'));

        $margin = 10.0;
        $panelHeight = min(48.0, max(42.0, $pageSize['height'] * 0.22));
        $panelWidth = $pageSize['width'] - ($margin * 2);
        $panelX = $margin;
        $panelY = $pageSize['height'] - $panelHeight - $margin;
        $qrSize = min(32.0, $panelHeight - 12.0);
        $textWidth = $panelWidth - $qrSize - 12.0;

        $pdf->SetDrawColor($red, $green, $blue);
        $pdf->SetTextColor($red, $green, $blue);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetLineWidth(0.5);
        $pdf->RoundedRect($panelX, $panelY, $panelWidth, $panelHeight, 2.5, '1111', 'DF');

        $textX = $panelX + 4.0;
        $textY = $panelY + 4.0;

        $pdf->SetXY($textX, $textY);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell($textWidth, 5, 'Sello de Evidencia', 0, 1);

        $pdf->SetFont('helvetica', '', 8.2);
        $pdf->SetX($textX);
        $lines = [
            'Nombre: '.$stampData['user_name'],
            'Cedula: '.$stampData['user_id'],
            'Fecha/Hora: '.$stampData['signed_at'],
            'IP: '.$stampData['ip_address'],
            'Hash: '.chunk_split($stampData['hash'], 32, ' '),
        ];

        foreach ($lines as $line) {
            $pdf->SetX($textX);
            $pdf->MultiCell($textWidth, 4.0, $line, 0, 'L', false, 1);
        }

        $pdf->SetX($textX);
        $pdf->SetFont('helvetica', 'I', 7.4);
        $pdf->MultiCell($textWidth, 3.4, 'Escanea el QR para validar este documento.', 0, 'L', false, 1);

        $qrX = $panelX + $panelWidth - $qrSize - 4.0;
        $qrY = $panelY + 5.0;
        $pdf->Image('@'.$qrCodeBinary, $qrX, $qrY, $qrSize, $qrSize, 'PNG');
        $pdf->Link($qrX, $qrY, $qrSize, $qrSize, $validationUrl);

        $pdf->SetXY($qrX - 2.0, $qrY + $qrSize + 1.0);
        $pdf->SetFont('helvetica', 'B', 7.2);
        $pdf->MultiCell($qrSize + 4.0, 3.2, 'Validar documento', 0, 'C', false, 1);
    }

    private function makeQrCode(string $validationUrl): string
    {
        [$red, $green, $blue] = $this->hexToRgb((string) config('signature.branding_color', '#FE4FA2'));

        return QrCode::format('png')
            ->size(280)
            ->margin(1)
            ->color($red, $green, $blue)
            ->generate($validationUrl);
    }

    private function hexToRgb(string $hexColor): array
    {
        $normalized = ltrim($hexColor, '#');

        if (strlen($normalized) === 3) {
            $normalized = preg_replace('/(.)/', '$1$1', $normalized);
        }

        return [
            hexdec(substr($normalized, 0, 2)),
            hexdec(substr($normalized, 2, 2)),
            hexdec(substr($normalized, 4, 2)),
        ];
    }
}
