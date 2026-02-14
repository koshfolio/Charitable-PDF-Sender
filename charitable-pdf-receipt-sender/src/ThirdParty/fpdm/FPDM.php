<?php

/**
 * Minimal FPDM-compatible adapter backed by pdftk for AcroForm field filling.
 */
class FPDM
{
    private string $template;
    private array $data;
    private bool $flatten = false;
    private ?string $outputPath = null;

    public function __construct(string $pdfPath, array $data)
    {
        $this->template = $pdfPath;
        $this->data = $data;
    }

    public function Load(string $pdfPath, bool $parse = false): void
    {
        $this->template = $pdfPath;
    }

    public function Merge(): void
    {
        // compatibility no-op
    }

    public function Flatten(): void
    {
        $this->flatten = true;
    }

    public function Output(string $dest = 'F', string $name = ''): string
    {
        if (! is_file($this->template)) {
            throw new RuntimeException('Template file does not exist.');
        }

        $fdfPath = tempnam(sys_get_temp_dir(), 'cprs_fdf_');
        $outPath = tempnam(sys_get_temp_dir(), 'cprs_pdf_');
        if ($fdfPath === false || $outPath === false) {
            throw new RuntimeException('Unable to create temporary files.');
        }

        file_put_contents($fdfPath, $this->buildFdf($this->data));

        $flattenArg = $this->flatten ? ' flatten' : '';
        $cmd = sprintf(
            'pdftk %s fill_form %s output %s%s 2>&1',
            escapeshellarg($this->template),
            escapeshellarg($fdfPath),
            escapeshellarg($outPath),
            $flattenArg
        );
        exec($cmd, $output, $code);

        @unlink($fdfPath);

        if ($code !== 0) {
            @unlink($outPath);
            throw new RuntimeException('FPDM command failed: ' . implode("\n", $output));
        }

        $bytes = (string) file_get_contents($outPath);

        if ($dest === 'S') {
            @unlink($outPath);
            return $bytes;
        }

        if ($dest === 'F' && $name !== '') {
            if (! @rename($outPath, $name)) {
                copy($outPath, $name);
                @unlink($outPath);
            }
            return $name;
        }

        $this->outputPath = $outPath;
        return $outPath;
    }

    private function buildFdf(array $fields): string
    {
        $chunks = [];
        foreach ($fields as $name => $value) {
            $n = $this->escape((string) $name);
            $v = $this->escape((string) $value);
            $chunks[] = "<< /T ({$n}) /V ({$v}) >>";
        }

        return "%FDF-1.2\n1 0 obj\n<< /FDF << /Fields [" . implode('', $chunks) . "] >> >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF";
    }

    private function escape(string $value): string
    {
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $value);
    }
}
