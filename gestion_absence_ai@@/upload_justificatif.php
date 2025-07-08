<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'étudiant
require_etudiant();

$error = '';
$success = '';

// Traitement du formulaire d'upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etudiant_id = $_SESSION['user_id'];
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $date_absence = isset($_POST['date_absence']) ? $_POST['date_absence'] : '';
    
    // Validation des données
    if ($module_id <= 0) {
        $error = "Veuillez sélectionner un module.";
    } elseif (empty($date_absence)) {
        $error = "Veuillez spécifier une date d'absence.";
    } elseif (!isset($_FILES['justificatif']) || $_FILES['justificatif']['error'] !== UPLOAD_ERR_OK) {
        $error = "Veuillez sélectionner un fichier justificatif.";
    } else {
        // Vérification du fichier
        $file = $_FILES['justificatif'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $error = "Format de fichier non autorisé. Seuls les fichiers PDF, JPG et PNG sont acceptés.";
        } elseif ($file['size'] > $max_size) {
            $error = "Le fichier est trop volumineux. Taille maximum : 5MB.";
        } else {
            try {
                // Vérifier que l'étudiant est inscrit à ce module
                $stmt = $pdo->prepare("
                    SELECT 1 FROM inscriptions_modules 
                    WHERE etudiant_id = ? AND module_id = ?
                ");
                $stmt->execute([$etudiant_id, $module_id]);
                
                if (!$stmt->fetch()) {
                    $error = "Vous n'êtes pas inscrit à ce module.";
                } else {
                    // Vérifier si un justificatif n'existe pas déjà pour cette date et ce module
                    $stmt = $pdo->prepare("
                        SELECT id FROM justificatifs 
                        WHERE etudiant_id = ? AND module_id = ? AND date_absence = ?
                    ");
                    $stmt->execute([$etudiant_id, $module_id, $date_absence]);
                    
                    if ($stmt->fetch()) {
                        $error = "Un justificatif a déjà été soumis pour cette date et ce module.";
                    } else {
                        // Obtenir l'apogée de l'étudiant pour créer le dossier
                        $stmt = $pdo->prepare("SELECT numero_apogee FROM etudiants WHERE id = ?");
                        $stmt->execute([$etudiant_id]);
                        $etudiant = $stmt->fetch();
                        
                        // Créer le dossier si nécessaire
                        $upload_dir = "justificatifs/" . $etudiant['numero_apogee'] . "/";
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }
                        
                        // Générer le nom du fichier
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        $filename = "justif_" . $date_absence . "." . $extension;
                        $filepath = $upload_dir . $filename;
                        
                        // Déplacer le fichier
                        if (move_uploaded_file($file['tmp_name'], $filepath)) {
                            // Enregistrer en base de données
                            $stmt = $pdo->prepare("
                                INSERT INTO justificatifs (etudiant_id, module_id, date_absence, fichier_path) 
                                VALUES (?, ?, ?, ?)
                            ");
                            $stmt->execute([$etudiant_id, $module_id, $date_absence, $filepath]);
                            
                            $success = "Justificatif soumis avec succès!";
                        } else {
                            $error = "Erreur lors du téléchargement du fichier.";
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = "Erreur de base de données: " . $e->getMessage();
            }
        }
    }
}

// Récupérer les modules de l'étudiant
$etudiant_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("
    SELECT m.id, m.code, m.nom 
    FROM modules m 
    JOIN inscriptions_modules im ON m.id = im.module_id 
    WHERE im.etudiant_id = ?
    ORDER BY m.nom
");
$stmt->execute([$etudiant_id]);
$modules = $stmt->fetchAll();

// Récupérer les justificatifs existants
$stmt = $pdo->prepare("
    SELECT j.*, m.code as module_code, m.nom as module_nom 
    FROM justificatifs j 
    JOIN modules m ON j.module_id = m.id 
    WHERE j.etudiant_id = ?
    ORDER BY j.date_soumission DESC
");
$stmt->execute([$etudiant_id]);
$justificatifs = $stmt->fetchAll();

// Inclure l'en-tête
$title = "Soumission de justificatifs";
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1>Soumission de justificatifs d'absence</h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-upload me-2"></i>Nouveau justificatif</h5>
                </div>
                <div class="card-body">
                    <form method="post" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label for="module_id" class="form-label">Module</label>
                            <select class="form-select" id="module_id" name="module_id" required>
                                <option value="">Sélectionnez un module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>">
                                        <?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="date_absence" class="form-label">Date d'absence</label>
                            <input type="date" class="form-control" id="date_absence" name="date_absence" 
                                   max="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="justificatif" class="form-label">Fichier justificatif</label>
                            <input type="file" class="form-control" id="justificatif" name="justificatif" 
                                   accept=".pdf,.jpg,.jpeg,.png" required>
                            <div class="form-text">
                                Formats acceptés: PDF, JPG, PNG. Taille maximum: 5MB.
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Soumettre le justificatif
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-list me-2"></i>Mes justificatifs soumis</h5>
                </div>
                <div class="card-body">
                    <?php if (count($justificatifs) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Module</th>
                                        <th>Date</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($justificatifs as $justif): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($justif['module_code']); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($justif['date_absence'])); ?></td>
                                            <td>
                                                <?php
                                                $badge_class = 'secondary';
                                                switch ($justif['statut']) {
                                                    case 'accepté':
                                                        $badge_class = 'success';
                                                        break;
                                                    case 'rejeté':
                                                        $badge_class = 'danger';
                                                        break;
                                                    case 'en attente':
                                                        $badge_class = 'warning';
                                                        break;
                                                }
                                                ?>
                                                <span class="badge bg-<?php echo $badge_class; ?>">
                                                    <?php echo ucfirst($justif['statut']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="<?php echo htmlspecialchars($justif['fichier_path']); ?>" 
                                                   target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center text-muted">
                            <i class="fas fa-inbox fa-3x mb-3"></i>
                            <p>Aucun justificatif soumis</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mt-3">
        <a href="dashboard_etudiant.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
        </a>
    </div>
</div>

<?php include 'includes/footer.php'; ?>