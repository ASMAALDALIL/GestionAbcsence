<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant que responsable
require_responsable();
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'responsable') {
    header('Location: login.php');
    exit;
}

$prenom = $_SESSION['prenom'];
$nom = $_SESSION['nom'];
$responsable_id = $_SESSION['user_id'];

// Récupérer les modules du responsable avec informations détaillées
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.code,
            m.nom as module_nom,
            m.semestre,
            f.nom as filiere_nom,
            f.code as filiere_code,
            COUNT(DISTINCT im.etudiant_id) as nb_etudiants
        FROM modules m
        INNER JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN inscriptions_modules im ON m.id = im.module_id
        WHERE m.responsable_id = :responsable_id
        GROUP BY m.id, m.code, m.nom, m.semestre, f.nom, f.code
        ORDER BY f.nom, m.semestre, m.nom
    ");
    $stmt->execute(['responsable_id' => $responsable_id]);
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Récupérer les séances récentes
    $stmt_seances = $pdo->prepare("
        SELECT 
            s.id,
            s.date_seance,
            s.heure_debut,
            s.heure_fin,
            m.nom as module_nom,
            m.code as module_code,
            f.nom as filiere_nom,
            COUNT(DISTINCT im.etudiant_id) as nb_etudiants_inscrits,
            COUNT(DISTINCT a.etudiant_id) as nb_absents,
            (COUNT(DISTINCT im.etudiant_id) - COUNT(DISTINCT a.etudiant_id)) as nb_presents
        FROM seances s
        INNER JOIN modules m ON s.module_id = m.id
        INNER JOIN filieres f ON m.filiere_id = f.id
        LEFT JOIN inscriptions_modules im ON m.id = im.module_id
        LEFT JOIN absences a ON s.id = a.seance_id
        WHERE m.responsable_id = :responsable_id
        AND s.date_seance >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        GROUP BY s.id, s.date_seance, s.heure_debut, s.heure_fin, m.nom, m.code, f.nom
        ORDER BY s.date_seance DESC, s.heure_debut DESC
        LIMIT 10
    ");
    $stmt_seances->execute(['responsable_id' => $responsable_id]);
    $seances_recentes = $stmt_seances->fetchAll(PDO::FETCH_ASSOC);

    // Statistiques globales
    $stmt_stats = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT m.id) as total_modules,
            COUNT(DISTINCT im.etudiant_id) as total_etudiants,
            COUNT(DISTINCT s.id) as total_seances_mois,
            COUNT(DISTINCT a.id) as total_absences_mois
        FROM modules m
        LEFT JOIN inscriptions_modules im ON m.id = im.module_id
        LEFT JOIN seances s ON m.id = s.module_id AND s.date_seance >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        LEFT JOIN absences a ON s.id = a.seance_id
        WHERE m.responsable_id = :responsable_id
    ");
    $stmt_stats->execute(['responsable_id' => $responsable_id]);
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des données: ' . $e->getMessage();
    $modules = [];
    $seances_recentes = [];
    $stats = ['total_modules' => 0, 'total_etudiants' => 0, 'total_seances_mois' => 0, 'total_absences_mois' => 0];
}

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Responsable - Gestion des Absences</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .card-stat {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .card-stat:hover {
            transform: translateY(-2px);
        }
        .card-stat.primary { border-left-color: #007bff; }
        .card-stat.success { border-left-color: #28a745; }
        .card-stat.warning { border-left-color: #ffc107; }
        .card-stat.danger { border-left-color: #dc3545; }
        .quick-action-btn {
            border-radius: 10px;
            padding: 1rem;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .module-card {
            border-radius: 10px;
            transition: transform 0.2s;
        }
        .module-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .badge-semestre {
            font-size: 0.7rem;
        }
        .seance-item {
            transition: background-color 0.2s;
        }
        .seance-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap me-2"></i>
                Gestion des Absences
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="fas fa-user me-1"></i>
                    <?php echo htmlspecialchars($prenom . ' ' . $nom); ?>
                </span>
                <a href="logout.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-sign-out-alt me-1"></i>Déconnexion
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card card-stat primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Modules</h6>
                                <h3 class="mb-0"><?php echo $stats['total_modules']; ?></h3>
                            </div>
                            <div class="text-primary">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Étudiants</h6>
                                <h3 class="mb-0"><?php echo $stats['total_etudiants']; ?></h3>
                            </div>
                            <div class="text-success">
                                <i class="fas fa-users fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Séances (30j)</h6>
                                <h3 class="mb-0"><?php echo $stats['total_seances_mois']; ?></h3>
                            </div>
                            <div class="text-warning">
                                <i class="fas fa-calendar fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card card-stat danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title text-muted">Absences (30j)</h6>
                                <h3 class="mb-0"><?php echo $stats['total_absences_mois']; ?></h3>
                            </div>
                            <div class="text-danger">
                                <i class="fas fa-user-times fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions rapides -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3"><i class="fas fa-bolt text-warning me-2"></i>Actions rapides</h5>
            </div>
            <div class="col-md-3 mb-3">
                <button class="btn btn-primary quick-action-btn w-100" data-bs-toggle="modal" data-bs-target="#createSeanceModal">
                    <i class="fas fa-plus-circle fa-2x mb-2 d-block"></i>
                    <strong>Créer une séance</strong>
                    <small class="d-block text-light mt-1">Planifier une nouvelle séance</small>
                </button>
            </div>
            <div class="col-md-3 mb-3">
                <button class="btn btn-success quick-action-btn w-100" data-bs-toggle="modal" data-bs-target="#qrCodeModal">
                    <i class="fas fa-qrcode fa-2x mb-2 d-block"></i>
                    <strong>Générer QR Code</strong>
                    <small class="d-block text-light mt-1">Pour marquer les présences</small>
                </button>
            </div>
            <div class="col-md-3 mb-3">
                <a href="liste_absences.php" class="btn btn-warning quick-action-btn w-100 text-decoration-none">
                    <i class="fas fa-list fa-2x mb-2 d-block"></i>
                    <strong>Voir les absences</strong>
                    <small class="d-block text-light mt-1">Consulter les absences</small>
                </a>
            </div>
            <div class="col-md-3 mb-3">
                <a href="generer_rapport.php" class="btn btn-info quick-action-btn w-100 text-decoration-none">
                    <i class="fas fa-chart-bar fa-2x mb-2 d-block"></i>
                    <strong>Rapports</strong>
                    <small class="d-block text-light mt-1">Générer des statistiques</small>
                </a>
            </div>
        </div>

        <div class="row">
            <!-- Modules -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-book me-2"></i>Vos modules</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($modules) > 0): ?>
                            <div class="row">
                                <?php foreach ($modules as $module): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card module-card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0"><?php echo htmlspecialchars($module['code']); ?></h6>
                                                    <span class="badge bg-secondary badge-semestre"><?php echo htmlspecialchars($module['semestre']); ?></span>
                                                </div>
                                                <p class="card-text small text-muted mb-2"><?php echo htmlspecialchars($module['module_nom']); ?></p>
                                                <p class="card-text small">
                                                    <i class="fas fa-graduation-cap me-1"></i>
                                                    <?php echo htmlspecialchars($module['filiere_nom']); ?>
                                                </p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-users me-1"></i>
                                                        <?php echo $module['nb_etudiants']; ?> étudiants
                                                    </small>
                                                    <div class="btn-group btn-group-sm">
                                                        <button class="btn btn-outline-primary btn-sm" onclick="generateQR(<?php echo $module['id']; ?>)" title="Générer QR">
                                                            <i class="fas fa-qrcode"></i>
                                                        </button>
                                                        <button class="btn btn-outline-info btn-sm" onclick="viewAbsences(<?php echo $module['id']; ?>)" title="Voir absences">
                                                            <i class="fas fa-list"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-book fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucun module assigné.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Séances récentes -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Séances récentes</h5>
                    </div>
                    <div class="card-body p-0">
                        <?php if (count($seances_recentes) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($seances_recentes as $seance): ?>
                                    <div class="list-group-item seance-item">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($seance['module_code']); ?></h6>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($seance['module_nom']); ?></p>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i>
                                                    <?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?>
                                                    <i class="fas fa-clock ms-2 me-1"></i>
                                                    <?php echo substr($seance['heure_debut'], 0, 5); ?>-<?php echo substr($seance['heure_fin'], 0, 5); ?>
                                                </small>
                                            </div>
                                            <div class="text-end">
                                                <small class="text-success d-block">
                                                    <i class="fas fa-check me-1"></i><?php echo $seance['nb_presents']; ?>
                                                </small>
                                                <small class="text-danger d-block">
                                                    <i class="fas fa-times me-1"></i><?php echo $seance['nb_absents']; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-footer">
                                <a href="liste_seances.php" class="btn btn-outline-info btn-sm w-100">
                                    Voir toutes les séances
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                <p class="text-muted">Aucune séance récente.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal pour créer une séance -->
    <div class="modal fade" id="createSeanceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Créer une séance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="createSeanceForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="module_id" class="form-label">Module</label>
                            <select class="form-select" id="module_id" name="module_id" required>
                                <option value="">Sélectionnez un module</option>
                                <?php foreach ($modules as $module): ?>
                                    <option value="<?php echo $module['id']; ?>">
                                        <?php echo htmlspecialchars($module['code'] . ' - ' . $module['module_nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="date_seance" class="form-label">Date</label>
                            <input type="date" class="form-control" id="date_seance" name="date_seance" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6">
                                <label for="heure_debut" class="form-label">Heure début</label>
                                <input type="time" class="form-control" id="heure_debut" name="heure_debut" required>
                            </div>
                            <div class="col-md-6">
                                <label for="heure_fin" class="form-label">Heure fin</label>
                                <input type="time" class="form-control" id="heure_fin" name="heure_fin" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">Créer la séance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal pour générer QR Code -->
    <div class="modal fade" id="qrCodeModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-qrcode me-2"></i>Générer un QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="qr_module_id" class="form-label">Module</label>
                                <select class="form-select" id="qr_module_id" required>
                                    <option value="">Sélectionnez un module</option>
                                    <?php foreach ($modules as $module): ?>
                                        <option value="<?php echo $module['id']; ?>">
                                            <?php echo htmlspecialchars($module['code'] . ' - ' . $module['module_nom']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="qr_date_seance" class="form-label">Date de la séance</label>
                                <input type="date" class="form-control" id="qr_date_seance" required>
                            </div>
                            <div class="row">
                                <div class="col-6">
                                    <label for="qr_heure_debut" class="form-label">Heure début</label>
                                    <input type="time" class="form-control" id="qr_heure_debut" required>
                                </div>
                                <div class="col-6">
                                    <label for="qr_heure_fin" class="form-label">Heure fin</label>
                                    <input type="time" class="form-control" id="qr_heure_fin" required>  
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" id="generateQRBtn" class="btn btn-success w-100">
                                    <i class="fas fa-qrcode me-2"></i>Générer le QR Code
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div id="qrCodeResult" class="text-center">
                                <div class="border rounded p-4">
                                    <i class="fas fa-qrcode fa-4x text-muted mb-3"></i>
                                    <p class="text-muted">Le QR Code apparaîtra ici</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Définir la date minimum à aujourd'hui
        document.getElementById('date_seance').min = new Date().toISOString().split('T')[0];
        document.getElementById('qr_date_seance').min = new Date().toISOString().split('T')[0];

        // Fonctions pour les actions rapides
        function generateQR(moduleId) {
            document.getElementById('qr_module_id').value = moduleId;
            new bootstrap.Modal(document.getElementById('qrCodeModal')).show();
        }

        function viewAbsences(moduleId) {
            window.location.href = 'liste_absences.php?module_id=' + moduleId;
        }

        // Créer une séance
        document.getElementById('createSeanceForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('create_seance.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Séance créée avec succès !');
                    bootstrap.Modal.getInstance(document.getElementById('createSeanceModal')).hide();
                    location.reload();
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la création de la séance');
            });
        });

        // Générer QR Code
        document.getElementById('generateQRBtn').addEventListener('click', function() {
            const moduleId = document.getElementById('qr_module_id').value;
            const dateSeance = document.getElementById('qr_date_seance').value;
            const heureDebut = document.getElementById('qr_heure_debut').value;
            const heureFin = document.getElementById('qr_heure_fin').value;

            if (!moduleId || !dateSeance || !heureDebut || !heureFin) {
                alert('Veuillez remplir tous les champs');
                return;
            }

            const qrData = {
                module_id: moduleId,
                date_seance: dateSeance,
                heure_debut: heureDebut,
                heure_fin: heureFin
            };

            fetch('generer_qr.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(qrData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('qrCodeResult').innerHTML = `
                        <div class="text-center">
                            <img src="${data.qr_code_url}" class="img-fluid mb-3" alt="QR Code">
                            <p class="small text-muted">QR Code généré avec succès</p>
                            <button class="btn btn-outline-primary btn-sm" onclick="window.print()">
                                <i class="fas fa-print me-1"></i>Imprimer
                            </button>
                        </div>
                    `;
                } else {
                    alert('Erreur: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erreur:', error);
                alert('Erreur lors de la génération du QR Code');
            });
        });
    </script>
</body>
</html>