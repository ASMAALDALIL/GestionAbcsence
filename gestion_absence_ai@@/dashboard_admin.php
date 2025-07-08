<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin(); // Utiliser la fonction require_admin() pour vérifier l'autorisation

// Obtenir les informations de l'admin connecté
$admin_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM administrateurs WHERE id = ?");
$stmt->execute([$admin_id]);
$admin = $stmt->fetch();

// Récupérer les statistiques
// Nombre de filières
$stmt = $pdo->query("SELECT COUNT(*) as total FROM filieres");
$nbFilieres = $stmt->fetch()['total'];

// Nombre de modules
$stmt = $pdo->query("SELECT COUNT(*) as total FROM modules");
$nbModules = $stmt->fetch()['total'];

// Nombre d'étudiants
$stmt = $pdo->query("SELECT COUNT(*) as total FROM etudiants");
$nbEtudiants = $stmt->fetch()['total'];

// Nombre d'absences
$stmt = $pdo->query("SELECT COUNT(*) as total FROM absences");
$nbAbsences = $stmt->fetch()['total'];

// Inclure l'en-tête
$title = "Tableau de bord administrateur";
include 'includes/header.php';
?>

<div class="container mt-4">
    <h1>Tableau de bord administrateur</h1>
    <p>Bienvenue, <?php echo htmlspecialchars($admin['prenom'] . ' ' . $admin['nom']); ?></p>
    
    <div class="row mt-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5 class="card-title">Filières</h5>
                    <p class="card-text display-4"><?php echo $nbFilieres; ?></p>
                    <a href="admin/gestion_filieres.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5 class="card-title">Modules</h5>
                    <p class="card-text display-4"><?php echo $nbModules; ?></p>
                    <a href="admin/gestion_modules.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5 class="card-title">Étudiants</h5>
                    <p class="card-text display-4"><?php echo $nbEtudiants; ?></p>
                    <a href="admin/gestion_etudiants.php" class="btn btn-light">Gérer</a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-dark">
                <div class="card-body">
                    <h5 class="card-title">Absences</h5>
                    <p class="card-text display-4"><?php echo $nbAbsences; ?></p>
                    <a href="admin/gestion_absences.php" class="btn btn-dark">Gérer</a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Actions rapides
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="admin/gestion_filieres.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-university"></i> Gestion des filières
                        </a>
                        <a href="admin/gestion_modules.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-book"></i> Gestion des modules
                        </a>
                        <a href="admin/gestion_etudiants.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-user-graduate"></i> Gestion des étudiants
                        </a>
                        <a href="admin/gestion_absences.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-calendar-times"></i> Gestion des absences
                        </a>
                        <a href="generer_rapport.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-file-pdf"></i> Générer rapport PDF
                        </a>
                        <a href="choisir_filiere.php" class="btn-pdf">📄 Télécharger la liste des étudiants</a>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    Dernières activités
                </div>
                <div class="card-body">
                    <ul class="list-group">
                        <?php
                        // Récupérer les 5 derniers étudiants inscrits
                        $stmt = $pdo->query("SELECT nom, prenom, created_at FROM etudiants ORDER BY created_at DESC LIMIT 5");
                        $students = $stmt->fetchAll();
                        
                        if (count($students) > 0) {
                            foreach ($students as $student) {
                                $date = date("d/m/Y H:i", strtotime($student['created_at']));
                                echo '<li class="list-group-item">
                                        <i class="fas fa-user-plus"></i> ' . 
                                        htmlspecialchars($student['nom'] . ' ' . $student['prenom']) . 
                                        ' s\'est inscrit le ' . $date . '
                                      </li>';
                            }
                        } else {
                            echo '<li class="list-group-item">Aucune activité récente</li>';
                        }
                        ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>