<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin();

// Traitement de la génération du PDF
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $filiere_id = isset($_POST['filiere_id']) ? intval($_POST['filiere_id']) : 0;
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;

    if ($filiere_id > 0) {
        // Inclure TCPDF
        require_once 'includes/tcpdf/tcpdf.php';
        require_once 'includes/pdf_functions.php';

        try {
            // Récupérer les informations de la filière
            $stmt = $pdo->prepare("SELECT code, nom FROM filieres WHERE id = ?");
            $stmt->execute([$filiere_id]);
            $filiere = $stmt->fetch();
            
            // Construire la requête selon les filtres
            $sql = "
                SELECT 
                    e.nom AS etudiant_nom,
                    e.prenom AS etudiant_prenom,
                    e.numero_apogee,
                    m.code AS module_code,
                    m.nom AS module_nom,
                    f.nom AS filiere_nom,
                    s.date_seance AS seance_date,
                    CASE 
                        WHEN j.id IS NOT NULL THEN 'Oui'
                        ELSE 'Non'
                    END AS justificatif_fourni
                FROM absences a
                JOIN etudiants e ON a.etudiant_id = e.id
                JOIN seances s ON a.seance_id = s.id
                JOIN modules m ON s.module_id = m.id
                JOIN filieres f ON m.filiere_id = f.id
                LEFT JOIN justificatifs j ON (j.etudiant_id = e.id AND j.module_id = m.id AND j.date_absence = s.date_seance)
                WHERE f.id = ?
            ";
            
            $params = [$filiere_id];
            $filename_parts = [$filiere['code']];
            
            if ($module_id > 0) {
                $sql .= " AND m.id = ?";
                $params[] = $module_id;
                
                // Récupérer le nom du module
                $stmt = $pdo->prepare("SELECT code FROM modules WHERE id = ?");
                $stmt->execute([$module_id]);
                $module = $stmt->fetch();
                $filename_parts[] = $module['code'];
            }
            
            $sql .= " ORDER BY e.nom, e.prenom, s.date_seance";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $absences = $stmt->fetchAll();
            
            // Définir les constantes TCPDF si elles ne sont pas définies
            if (!defined('PDF_PAGE_ORIENTATION')) {
                define('PDF_PAGE_ORIENTATION', 'P');
                define('PDF_UNIT', 'mm');
                define('PDF_PAGE_FORMAT', 'A4');
                define('PDF_FONT_NAME_MAIN', 'helvetica');
                define('PDF_FONT_SIZE_MAIN', 10);
                define('PDF_FONT_NAME_DATA', 'helvetica');
                define('PDF_FONT_SIZE_DATA', 8);
                define('PDF_MARGIN_LEFT', 15);
                define('PDF_MARGIN_TOP', 27);
                define('PDF_MARGIN_RIGHT', 15);
                define('PDF_MARGIN_HEADER', 5);
                define('PDF_MARGIN_FOOTER', 10);
                define('PDF_MARGIN_BOTTOM', 25);
            }
            
            // Créer une classe personnalisée pour le PDF
            class MYPDF extends TCPDF {
                // En-tête de page personnalisé avec logo
                public function Header() {
                    // Logo
                    $logo_path = 'assets/logo_ensa.png';
                    if (file_exists($logo_path)) {
                        $this->Image($logo_path, 10, 10, 30, 0, 'PNG', '', 'T', false, 300, '', false, false, 0, false, false, false);
                    }
                    
                    // Titre
                    $this->SetFont('helvetica', 'B', 15);
                    $this->Cell(0, 20, 'ENSA Marrakech', 0, false, 'C', 0, '', 0, false, 'M', 'M');
                    
                    // Sous-titre
                    $this->Ln(8);
                    $this->SetFont('helvetica', '', 11);
                    $this->Cell(0, 20, 'Rapport d\'Absences', 0, false, 'C', 0, '', 0, false, 'M', 'M');
                }

                // Pied de page personnalisé
                public function Footer() {
                    // Position à 15 mm du bas
                    $this->SetY(-15);
                    // Police
                    $this->SetFont('helvetica', 'I', 8);
                    // Date et numéro de page
                    $this->Cell(0, 10, 'Date de génération: ' . date('d/m/Y H:i') . ' - Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
                }
            }
            
            // Créer le PDF avec notre classe personnalisée
            $pdf = new MYPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            
            // Informations du document
            $pdf->SetCreator('Système de Gestion des Absences');
            $pdf->SetAuthor('ENSA Marrakech');
            $pdf->SetTitle('Rapport d\'Absences - ' . $filiere['nom']);
            
            // Paramètres de page
            $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
            $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
            $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
            $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
            $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
            $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
            $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
            
            // Ajouter une page
            $pdf->AddPage();
            
            // Espace après l'en-tête
            $pdf->Ln(25);
            
            // Informations du rapport
            $pdf->SetFont('helvetica', 'B', 12);
            $pdf->Cell(0, 8, 'Informations du rapport:', 0, 1);
            $pdf->SetFont('helvetica', '', 11);
            $pdf->Cell(0, 8, 'Filière: ' . $filiere['nom'], 0, 1);
            if ($module_id > 0 && isset($module)) {
                $pdf->Cell(0, 8, 'Module: ' . $module['code'], 0, 1);
            } else {
                $pdf->Cell(0, 8, 'Module: Tous les modules', 0, 1);
            }
            $pdf->Ln(5);
            
            if (count($absences) > 0) {
                // En-tête du tableau
                $pdf->SetFont('helvetica', 'B', 10);
                $pdf->SetFillColor(41, 128, 185); // Bleu plus foncé que précédemment
                $pdf->SetTextColor(255);
                
                $pdf->Cell(45, 8, 'Nom & Prénom', 1, 0, 'C', true);
                $pdf->Cell(25, 8, 'N° Apogée', 1, 0, 'C', true);
                $pdf->Cell(30, 8, 'Module', 1, 0, 'C', true);
                $pdf->Cell(25, 8, 'Filière', 1, 0, 'C', true);
                $pdf->Cell(25, 8, 'Date', 1, 0, 'C', true);
                $pdf->Cell(30, 8, 'Justificatif', 1, 1, 'C', true);
                
                // Contenu du tableau
                $pdf->SetFont('helvetica', '', 9);
                $pdf->SetTextColor(0);
                $fill = false;
                
                foreach ($absences as $absence) {
                    $pdf->SetFillColor($fill ? 235 : 255); // Alternance de couleurs plus subtile
                    
                    $pdf->Cell(45, 6, $absence['etudiant_nom'] . ' ' . $absence['etudiant_prenom'], 1, 0, 'L', $fill);
                    $pdf->Cell(25, 6, $absence['numero_apogee'], 1, 0, 'C', $fill);
                    $pdf->Cell(30, 6, $absence['module_code'], 1, 0, 'C', $fill);
                    $pdf->Cell(25, 6, $absence['filiere_nom'], 1, 0, 'C', $fill);
                    $pdf->Cell(25, 6, date('d/m/Y', strtotime($absence['seance_date'])), 1, 0, 'C', $fill);
                    
                    // Justificatif avec symbole ✅ ou ❌
                    $justif_symbol = $absence['justificatif_fourni'] === 'Oui' ? '✅ Oui' : '❌ Non';
                    $pdf->Cell(30, 6, $justif_symbol, 1, 1, 'C', $fill);
                    
                    $fill = !$fill;
                }
                
                // Statistiques
                $pdf->Ln(10);
                $pdf->SetFont('helvetica', 'B', 11);
                $pdf->Cell(0, 8, 'Statistiques:', 0, 1);
                
                $total_absences = count($absences);
                $absences_avec_justif = array_filter($absences, function($a) { 
                    return $a['justificatif_fourni'] === 'Oui'; 
                });
                $nb_avec_justif = count($absences_avec_justif);
                $pourcentage_justif = $total_absences > 0 ? round(($nb_avec_justif / $total_absences) * 100, 1) : 0;
                
                $pdf->SetFont('helvetica', '', 10);
                $pdf->Cell(0, 6, "• Total d'absences: $total_absences", 0, 1);
                $pdf->Cell(0, 6, "• Absences avec justificatif: $nb_avec_justif", 0, 1);
                $pdf->Cell(0, 6, "• Pourcentage avec justificatif: $pourcentage_justif%", 0, 1);
                
                // Ajouter un graphique simple (barre de pourcentage)
                $pdf->Ln(5);
                $pdf->Cell(40, 8, "Taux de justification: ", 0, 0);
                
                // Dessiner la barre de progression
                $bar_width = 100;
                $pdf->SetFillColor(200, 200, 200);
                $pdf->Cell($bar_width, 8, '', 1, 0, 'L', true);
                
                // Partie remplie de la barre
                $pdf->SetX($pdf->GetX() - $bar_width);
                $filled_width = $pourcentage_justif * $bar_width / 100;
                $pdf->SetFillColor(46, 204, 113); // Vert
                $pdf->Cell($filled_width, 8, '', 1, 0, 'L', true);
                
                // Texte du pourcentage
                $pdf->SetX($pdf->GetX() - $filled_width + ($bar_width / 2) - 10);
                $pdf->SetTextColor(0);
                $pdf->Cell(20, 8, $pourcentage_justif . "%", 0, 1, 'C', false);
                
            } else {
                $pdf->SetFont('helvetica', 'I', 12);
                $pdf->Cell(0, 20, 'Aucune absence trouvée pour les critères sélectionnés.', 0, 1, 'C');
            }
            
            // Créer le dossier PDF s'il n'existe pas
            $pdf_dir = __DIR__ . '/pdf/';
            if (!is_dir($pdf_dir)) {
                mkdir($pdf_dir, 0755, true);
            }
            
            // Nom du fichier
            $filename = 'absences_' . implode('_', $filename_parts) . '_' . date('Y-m-d') . '.pdf';
            $filepath = $pdf_dir . $filename;
            
            // Sauvegarder le fichier
            $pdf->Output($filepath, 'F');
            
            // Télécharger le fichier
            $pdf->Output($filename, 'D');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error_message'] = "Erreur lors de la génération du PDF: " . $e->getMessage();
        }
    } else {
        $_SESSION['error_message'] = "Veuillez sélectionner une filière.";
    }

    header("Location: generer_rapport.php");
    exit;
}

// Récupérer les filières pour le formulaire
$stmt = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY nom");
$filieres = $stmt->fetchAll();

// Inclure l'en-tête
$title = "Génération de rapports PDF";
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1>Génération de rapports PDF</h1>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($_SESSION['error_message']); ?>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($_SESSION['success_message']); ?>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5><i class="fas fa-file-pdf me-2"></i>Paramètres du rapport</h5>
                </div>
                <div class="card-body">
                    <form method="post" id="reportForm">
                        <div class="mb-3">
                            <label for="filiere_id" class="form-label">Filière *</label>
                            <select class="form-select" id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionnez une filière</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>">
                                        <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="module_id" class="form-label">Module (optionnel)</label>
                            <select class="form-select" id="module_id" name="module_id">
                                <option value="">Tous les modules</option>
                                <!-- Les modules seront chargés dynamiquement -->
                            </select>
                            <div class="form-text">Laissez vide pour inclure tous les modules de la filière.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-download me-2"></i>Générer et télécharger le rapport PDF
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5><i class="fas fa-info-circle me-2"></i>Informations</h5>
                </div>
                <div class="card-body">
                    <h6>Contenu du rapport :</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-check text-success me-2"></i>En-tête avec logo de l'école</li>
                        <li><i class="fas fa-check text-success me-2"></i>Tableau des absences</li>
                        <li><i class="fas fa-check text-success me-2"></i>Indication des justificatifs fournis (✅/❌)</li>
                        <li><i class="fas fa-check text-success me-2"></i>Statistiques avec visualisation</li>
                        <li><i class="fas fa-check text-success me-2"></i>Date de génération</li>
                    </ul>
                    
                    <hr>
                    
                    <h6>Filtres disponibles :</h6>
                    <ul class="list-unstyled">
                        <li><i class="fas fa-filter text-primary me-2"></i>Par filière (obligatoire)</li>
                        <li><i class="fas fa-filter text-primary me-2"></i>Par module (optionnel)</li>
                    </ul>
                    
                    <hr>
                    
                    <p class="text-muted small">
                        <i class="fas fa-save me-1"></i>
                        Les rapports sont automatiquement sauvegardés dans le dossier <code>/pdf/</code>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <a href="dashboard_admin.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
        </a>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filiereSelect = document.getElementById('filiere_id');
    const moduleSelect = document.getElementById('module_id');
    
    // Chargement des modules en fonction de la filière sélectionnée
    filiereSelect.addEventListener('change', function() {
        const filiereId = this.value;
        
        // Réinitialiser la liste des modules
        moduleSelect.innerHTML = '<option value="">Tous les modules</option>';
        
        if (filiereId) {
            // Requête AJAX pour récupérer les modules
            fetch('get_modules.php?filiere_id=' + filiereId)
                .then(response => response.json())
                .then(data => {
                    data.forEach(module => {
                        const option = document.createElement('option');
                        option.value = module.id;
                        option.textContent = module.code + ' - ' + module.nom + ' (' + module.semestre + ')';
                        moduleSelect.appendChild(option);
                    });
                })
                .catch(error => {
                    console.error('Erreur lors du chargement des modules:', error);
                });
        }
    });
    
    // Confirmation avant génération
    document.getElementById('reportForm').addEventListener('submit', function(e) {
        const filiere = filiereSelect.options[filiereSelect.selectedIndex].text;
        const module = moduleSelect.value ? moduleSelect.options[moduleSelect.selectedIndex].text : 'Tous les modules';
        
        const message = `Générer le rapport pour :\n- Filière: ${filiere}\n- Module: ${module}`;
        
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});
</script>

<?php include 'includes/footer.php'; ?>