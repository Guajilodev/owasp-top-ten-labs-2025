<?php
/**
 * OWASP Top 10 Labs 2025 - Nexo
 * Panel lateral con información del lab
 * 
 * Variables esperadas:
 * - $labInfo: array con la siguiente estructura:
 *   - id: string (e.g., 'A01')
 *   - name: string (e.g., 'Broken Access Control')
 *   - description: string - explicación en lenguaje simple
 *   - exploit: string - comandos/pasos para explotar
 *   - prevention: string - código de la versión segura
 *   - caseStudy: array - ['title' => string, 'description' => string]
 *   - cwes: array - lista de CWE IDs
 *   - tools: array - lista de herramientas sugeridas
 *   - difficulty: string - 'Básica' | 'Intermedia' | 'Avanzada'
 */

if (!isset($labInfo)) {
    return;
}

$difficultyColors = [
    'Básica' => 'success',
    'Intermedia' => 'warning',
    'Avanzada' => 'danger',
];
$difficultyColor = $difficultyColors[$labInfo['difficulty']] ?? 'secondary';
?>

<!-- Panel lateral del lab -->
<aside class="lab-panel" id="labPanel">
    <div class="p-3">
        
        <!-- Header del lab -->
        <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
            <div>
                <span class="badge bg-danger fs-6"><?= htmlspecialchars($labInfo['id']) ?></span>
                <span class="badge bg-<?= $difficultyColor ?>"><?= htmlspecialchars($labInfo['difficulty']) ?></span>
            </div>
            <span class="badge bg-dark">🔴 VULNERABLE</span>
        </div>
        
        <h5 class="text-danger mb-3">
            <?= htmlspecialchars($labInfo['name']) ?>
        </h5>
        
        <!-- Secciones colapsables -->
        <div class="accordion accordion-flush" id="labAccordion">
            
            <!-- ¿Qué está pasando? -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseWhat">
                        📖 ¿Qué está pasando?
                    </button>
                </h2>
                <div id="collapseWhat" class="accordion-collapse collapse show" data-bs-parent="#labAccordion">
                    <div class="accordion-body small">
                        <?= $labInfo['description'] ?>
                    </div>
                </div>
            </div>
            
            <!-- Cómo explotarlo -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExploit">
                        🛠 Cómo explotarlo
                    </button>
                </h2>
                <div id="collapseExploit" class="accordion-collapse collapse" data-bs-parent="#labAccordion">
                    <div class="accordion-body">
                        <pre class="vulnerable p-2 rounded small mb-2"><code><?= htmlspecialchars($labInfo['exploit']) ?></code></pre>
                        
                        <?php if (!empty($labInfo['tools'])): ?>
                        <div class="mt-2">
                            <strong>Herramientas:</strong>
                            <?php foreach ($labInfo['tools'] as $tool): ?>
                                <span class="badge bg-secondary me-1"><?= htmlspecialchars($tool) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Cómo se previene -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePrevention">
                        🛡 Cómo se previene
                    </button>
                </h2>
                <div id="collapsePrevention" class="accordion-collapse collapse" data-bs-parent="#labAccordion">
                    <div class="accordion-body">
                        <pre class="secure p-2 rounded small"><code><?= htmlspecialchars($labInfo['prevention']) ?></code></pre>
                    </div>
                </div>
            </div>
            
            <!-- Caso real -->
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCase">
                        🌐 Caso real
                    </button>
                </h2>
                <div id="collapseCase" class="accordion-collapse collapse" data-bs-parent="#labAccordion">
                    <div class="accordion-body small">
                        <strong><?= htmlspecialchars($labInfo['caseStudy']['title']) ?></strong>
                        <p class="mb-0 mt-1 text-muted">
                            <?= htmlspecialchars($labInfo['caseStudy']['description']) ?>
                        </p>
                    </div>
                </div>
            </div>
            
        </div>
        
        <!-- CWEs -->
        <?php if (!empty($labInfo['cwes'])): ?>
        <div class="mt-3 pt-2 border-top">
            <small class="text-muted">
                <strong>CWEs:</strong>
                <?php foreach ($labInfo['cwes'] as $cwe): ?>
                    <a href="https://cwe.mitre.org/data/definitions/<?= intval(str_replace('CWE-', '', $cwe)) ?>.html" 
                       target="_blank" rel="noopener" class="text-decoration-none">
                        <?= htmlspecialchars($cwe) ?>
                    </a>
                <?php endforeach; ?>
            </small>
        </div>
        <?php endif; ?>
        
        <!-- Boton version segura -->
        <?php if (!empty($labInfo['secureVersion'])): ?>
        <div class="mt-3 pt-3 border-top">
            <a href="<?= htmlspecialchars($labInfo['secureVersion']) ?>" class="btn btn-success w-100">
                🛡️ Ver versión segura
            </a>
        </div>
        <?php endif; ?>
        
    </div>
</aside>

<!-- Toggle button -->
<button class="lab-panel-toggle" type="button">
    ▶ Ocultar
</button>
