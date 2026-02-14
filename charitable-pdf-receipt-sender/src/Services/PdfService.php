<?php

namespace CPRS\Services;

use CPRS\Plugin;

class PdfService
{
    public function generate(array $receiptData, array $settings): string
    {
        $templatePath = $this->resolveTemplatePath((int) ($settings['template_id'] ?? 0));
        if (! $templatePath) {
            throw new \RuntimeException('Template not configured or missing.');
        }

        $this->loadPdfDependencies();

        if (! class_exists('\FPDM')) {
            throw new \RuntimeException('FPDM class not available.');
        }

        $fields = $this->buildMappedFields($receiptData, (array) ($settings['field_map'] ?? []));

        $fpdm = new \FPDM($templatePath, $fields);
        $fpdm->Merge();
        if (method_exists($fpdm, 'Flatten')) {
            $fpdm->Flatten();
        }

        return (string) $fpdm->Output('S');
    }

    public function resolveTemplatePath(int $templateId): string
    {
        if ($templateId <= 0) {
            return '';
        }

        $path = get_attached_file($templateId);

        return is_string($path) && is_file($path) ? $path : '';
    }

    public function loadPdfDependencies(): void
    {
        $autoload = Plugin::pluginDir() . 'vendor/autoload.php';
        if (file_exists($autoload)) {
            require_once $autoload;
        }

        if (! class_exists('\FPDM')) {
            $fpdmPath = Plugin::pluginDir() . 'src/ThirdParty/fpdm/FPDM.php';
            if (file_exists($fpdmPath)) {
                require_once $fpdmPath;
            }
        }
    }

    private function buildMappedFields(array $receiptData, array $fieldMap): array
    {
        $mapped = [];
        foreach ($fieldMap as $internalKey => $pdfFieldName) {
            $pdfFieldName = trim((string) $pdfFieldName);
            if ($pdfFieldName === '' || ! array_key_exists($internalKey, $receiptData)) {
                continue;
            }
            $mapped[$pdfFieldName] = (string) $receiptData[$internalKey];
        }

        return $mapped;
    }
}
