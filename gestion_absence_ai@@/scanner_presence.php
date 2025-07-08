<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

$token = isset($_GET['token']) ? $_GET['token'] : '';
$message = '';
$message_type = '';

if (empty($token)) {
    $message = 'Token invalide';
    $message_type = 'danger';
} else {
    // Vérifier le token et récupérer les informations de la séance
    try {
        $stmt = $pdo->prepare("
            SELECT qt.*, s.*, m.nom as module_nom, m.code as module_code, 
                   f.nom as filiere_nom, r.nom as responsable_nom, r.prenom as responsable_prenom
            FROM qr_tokens qt
            JOIN seances s ON qt.seance_id = s.id
            JOIN modules m ON s.module_id = m.id
            JOIN filieres f ON m.filiere_id = f.id
            JOIN responsables r ON m.responsable_id = r.id
            WHERE qt.token = ? AND qt.created_at >= DATE_SUB(NOW(), INTERVAL 2 HOUR)
        ");
        $stmt->execute([$token]);
        $session_info = $stmt->fetch();
        
        if (!$session_info) {
            $message = 'QR Code expiré ou invalide';
            $message_type = 'danger';
        } else {
            // Vérifier si l'étudiant est connecté
            if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant') {
                // Rediriger vers la page de connexion avec le token
                header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
                exit;
            }
            
            $etudiant_id = $_SESSION['user_id'];
            
            // Vérifier si l'étudiant est inscrit au module
            $stmt = $pdo->prepare("SELECT id FROM inscriptions_modules WHERE etudiant_id = ? AND module_id = ?");
            $stmt->execute([$etudiant_id, $session_info['module_id']]);
            
            if (!$stmt->fetch()) {
                $message = 'Vous n\'êtes pas inscrit à ce module';
                $message_type = 'warning';
            } else {
                // Traitement de la présence si le formulaire est soumis
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    // Vérifier si la présence n'a pas déjà été marquée
                    $stmt = $pdo->prepare("SELECT id FROM absences WHERE etudiant_id = ? AND seance_id = ?");
                    $stmt->execute([$etudiant_id, $session_info['seance_id']]);
                    
                    if ($stmt->fetch()) {
                        $message = 'Votre présence a déjà été enregistrée pour cette séance';
                        $message_type = 'info';
                    } else {
                        // Marquer comme présent (ne pas insérer dans la table absences)
                        // Ou insérer avec absent = false
                        $stmt = $pdo->prepare("INSERT INTO absences (etudiant_id, seance_id, absent, created_at) VALUES (?, ?, 0, NOW())");
                        $stmt->execute([$etudiant_id, $session_info['seance_id']]);
                        
                        $message = 'Présence enregistrée avec succès !';
                        $message_type = 'success';
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $message = 'Erreur de base de données';
        $message_type = 'danger';
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scanner Présence - Gestion des Absences</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .scanner-container {
            max-width: 500px;
            margin: 0 auto;
            padding: 20px;
        }
        .session-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .btn-scan {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            border-radius: 25px;
            padding: 15px 30px;
            font-size: 18px;
            font-weight: bold;
            color: white;
            transition: transform 0.3s;
        }
        .btn-scan:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-4">
        <div class="scanner-container">
            <div class="text-center mb-4">
                <i class="fas fa-qrcode fa-4x text-primary mb-3"></i>
                <h2>Scanner Présence</h2>
            </div>

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>" role="alert">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : ($message_type === 'danger' ? 'exclamation-triangle' : 'info-circle'); ?> me-2"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($session_info) && $session_info): ?>
                <div class="session-info">
                    <div class="text-center mb-3">
                        <i class="fas fa-chalkboard-teacher fa-2x mb-2"></i>
                        <h4>Informations de la séance</h4>
                    </div>
                    <div class="row">
                        <div class="col-12 mb-2">
                            <strong><i class="fas fa-book me-2"></i>Module:</strong> 
                            <?php echo htmlspecialchars($session_info['module_code'] . ' - ' . $session_info['module_nom']); ?>
                        </div>
                        <div class="col-12 mb-2">
                            <strong><i class="fas fa-graduation-cap me-2"></i>Filière:</strong> 
                            <?php echo htmlspecialchars($session_info['filiere_nom']); ?>
                        </div>
                        <div class="col-12 mb-2">
                            <strong><i class="fas fa-user me-2"></i>Responsable:</strong> 
                            <?php echo htmlspecialchars($session_info['responsable_prenom'] . ' ' . $session_info['responsable_nom']); ?>
                        </div>
                        <div class="col-6 mb-2">
                            <strong><i class="fas fa-calendar me-2"></i>Date:</strong> 
                            <?php echo date('d/m/Y', strtotime($session_info['date_seance'])); ?>
                        </div>
                        <div class="col-6 mb-2">
                            <strong><i class="fas fa-clock me-2"></i>Horaire:</strong> 
                            <?php echo substr($session_info['heure_debut'], 0, 5) . ' - ' . substr($session_info['heure_fin'], 0, 5); ?>
                        </div>
                    </div>
                </div>

                <?php if (isset($_SESSION['user_id']) && $_SESSION['user_type'] === 'etudiant' && $message_type !== 'success' && $message_type !== 'info'): ?>
                    <form method="POST" class="text-center">
                        <button type="submit" class="btn btn-scan btn-lg">
                            <i class="fas fa-check-circle me-2"></i>
                            Marquer ma présence
                        </button>
                    </form>
                <?php endif; ?>

                <?php if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'etudiant'): ?>
                    <div class="text-center">
                        <p class="text-muted mb-3">Vous devez être connecté en tant qu'étudiant pour marquer votre présence</p>
                        <a href="login.php?redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Se connecter
                        </a>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <div class="text-center mt-4">
                <a href="dashboard_etudiant.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour au tableau de bord
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>