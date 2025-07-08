<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin();

// Variables pour la gestion des formulaires
$error = '';
$success = '';
$id_filiere = '';
$code_filiere = '';
$nom_filiere = '';

// Traitement des formulaires
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Vérifier le token CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Erreur de sécurité. Veuillez réessayer.';
    } else {
        // Action à effectuer (ajouter, modifier, supprimer)
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'ajouter':
                // Récupérer les données du formulaire
                $code = trim($_POST['code']);
                $nom = trim($_POST['nom']);
                
                // Valider les entrées
                if (empty($code) || empty($nom)) {
                    $error = 'Veuillez remplir tous les champs.';
                } else {
                    try {
                        // Vérifier si le code existe déjà
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM filieres WHERE code = :code");
                        $stmt->execute(['code' => $code]);
                        if ($stmt->fetchColumn() > 0) {
                            $error = 'Ce code de filière existe déjà.';
                        } else {
                            // Insérer la filière
                            $stmt = $pdo->prepare("INSERT INTO filieres (code, nom) VALUES (:code, :nom)");
                            $stmt->execute(['code' => $code, 'nom' => $nom]);
                            
                            $success = 'Filière ajoutée avec succès.';
                            
                            // Réinitialiser les champs du formulaire
                            $code_filiere = '';
                            $nom_filiere = '';
                        }
                    } catch (PDOException $e) {
                        $error = 'Erreur lors de l\'ajout de la filière: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'modifier':
                // Récupérer les données du formulaire
                $id = $_POST['id'];
                $code = trim($_POST['code']);
                $nom = trim($_POST['nom']);
                
                // Valider les entrées
                if (empty($id) || empty($code) || empty($nom)) {
                    $error = 'Veuillez remplir tous les champs.';
                } else {
                    try {
                        // Vérifier si le code existe déjà pour une autre filière
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM filieres WHERE code = :code AND id != :id");
                        $stmt->execute(['code' => $code, 'id' => $id]);
                        if ($stmt->fetchColumn() > 0) {
                            $error = 'Ce code de filière existe déjà.';
                        } else {
                            // Mettre à jour la filière
                            $stmt = $pdo->prepare("UPDATE filieres SET code = :code, nom = :nom WHERE id = :id");
                            $stmt->execute(['id' => $id, 'code' => $code, 'nom' => $nom]);
                            
                            $success = 'Filière modifiée avec succès.';
                            
                            // Réinitialiser les champs du formulaire
                            $id_filiere = '';
                            $code_filiere = '';
                            $nom_filiere = '';
                        }
                    } catch (PDOException $e) {
                        $error = 'Erreur lors de la modification de la filière: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'supprimer':
                // Récupérer l'ID
                $id = $_POST['id'];
                
                if (empty($id)) {
                    $error = 'ID invalide.';
                } else {
                    try {
                        // Vérifier si des modules sont liés à cette filière
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE filiere_id = :id");
                        $stmt->execute(['id' => $id]);
                        if ($stmt->fetchColumn() > 0) {
                            $error = 'Impossible de supprimer cette filière car elle est liée à des modules.';
                        } else {
                            // Vérifier si des étudiants sont inscrits à cette filière
                            $stmt = $pdo->prepare("SELECT COUNT(*) FROM etudiants WHERE filiere_id = :id");
                            $stmt->execute(['id' => $id]);
                            if ($stmt->fetchColumn() > 0) {
                                $error = 'Impossible de supprimer cette filière car des étudiants y sont inscrits.';
                            } else {
                                // Supprimer la filière
                                $stmt = $pdo->prepare("DELETE FROM filieres WHERE id = :id");
                                $stmt->execute(['id' => $id]);
                                
                                $success = 'Filière supprimée avec succès.';
                            }
                        }
                    } catch (PDOException $e) {
                        $error = 'Erreur lors de la suppression de la filière: ' . $e->getMessage();
                    }
                }
                break;
                
            default:
                $error = 'Action invalide.';
        }
    }
}

// Récupérer la filière à modifier (si demandé)
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM filieres WHERE id = :id");
        $stmt->execute(['id' => $_GET['edit']]);
        $filiere = $stmt->fetch();
        
        if ($filiere) {
            $id_filiere = $filiere['id'];
            $code_filiere = $filiere['code'];
            $nom_filiere = $filiere['nom'];
        }
    } catch (PDOException $e) {
        $error = 'Erreur lors de la récupération de la filière: ' . $e->getMessage();
    }
}

// Récupérer toutes les filières
try {
    $stmt = $pdo->query("SELECT f.*, 
                          (SELECT COUNT(*) FROM modules WHERE filiere_id = f.id) AS nb_modules,
                          (SELECT COUNT(*) FROM etudiants WHERE filiere_id = f.id) AS nb_etudiants
                         FROM filieres f ORDER BY code");
    $filieres = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = 'Erreur lors de la récupération des filières: ' . $e->getMessage();
    $filieres = [];
}

// Générer un nouveau token CSRF
$csrf_token = generate_csrf_token();

// Inclure l'en-tête
$title = "Gestion des filières";
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-university me-2"></i> Gestion des filières</h1>
        
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0">
                <li class="breadcrumb-item"><a href="../dashboard_admin.php">Tableau de bord</a></li>
                <li class="breadcrumb-item active" aria-current="page">Filières</li>
            </ol>
        </nav>
    </div>
    
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i><?php echo escape_html($error); ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo escape_html($success); ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-<?php echo empty($id_filiere) ? 'plus' : 'edit'; ?> me-2"></i>
                        <?php echo empty($id_filiere) ? 'Ajouter une filière' : 'Modifier la filière'; ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="post" action="gestion_filieres.php">
                        <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
                        <input type="hidden" name="action" value="<?php echo empty($id_filiere) ? 'ajouter' : 'modifier'; ?>">
                        <?php if (!empty($id_filiere)): ?>
                            <input type="hidden" name="id" value="<?php echo escape_html($id_filiere); ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="code" class="form-label">Code de la filière</label>
                            <input type="text" class="form-control" id="code" name="code" value="<?php echo escape_html($code_filiere); ?>" required>
                            <div class="form-text">Ex: GI, RSSP, GIL, etc.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom de la filière</label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?php echo escape_html($nom_filiere); ?>" required>
                            <div class="form-text">Ex: Génie Informatique, Réseaux et Systèmes, etc.</div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-<?php echo empty($id_filiere) ? 'plus' : 'save'; ?> me-2"></i>
                                <?php echo empty($id_filiere) ? 'Ajouter' : 'Enregistrer les modifications'; ?>
                            </button>
                            
                            <?php if (!empty($id_filiere)): ?>
                                <a href="gestion_filieres.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-2"></i>Annuler
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-list me-2"></i>Liste des filières</h5>
                </div>
                <div class="card-body">
                    <?php if (count($filieres) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Nom</th>
                                        <th>Modules</th>
                                        <th>Étudiants</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($filieres as $filiere): ?>
                                        <tr>
                                            <td><?php echo escape_html($filiere['code']); ?></td>
                                            <td><?php echo escape_html($filiere['nom']); ?></td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?php echo $filiere['nb_modules']; ?> module(s)
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success">
                                                    <?php echo $filiere['nb_etudiants']; ?> étudiant(s)
                                                </span>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="gestion_filieres.php?edit=<?php echo $filiere['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    
                                                    <button type="button" class="btn btn-sm btn-outline-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $filiere['id']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Modal de confirmation de suppression -->
                                                <div class="modal fade" id="deleteModal<?php echo $filiere['id']; ?>" tabindex="-1" 
                                                     aria-labelledby="deleteModalLabel<?php echo $filiere['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header bg-danger text-white">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $filiere['id']; ?>">
                                                                    Confirmation de suppression
                                                                </h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <p>Êtes-vous sûr de vouloir supprimer la filière "<?php echo escape_html($filiere['code'] . ' - ' . $filiere['nom']); ?>" ?</p>
                                                                
                                                                <?php if ($filiere['nb_modules'] > 0 || $filiere['nb_etudiants'] > 0): ?>
                                                                    <div class="alert alert-warning">
                                                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                                                        Attention: Cette filière est actuellement utilisée par:
                                                                        <ul class="mb-0 mt-2">
                                                                            <?php if ($filiere['nb_modules'] > 0): ?>
                                                                                <li><?php echo $filiere['nb_modules']; ?> module(s)</li>
                                                                            <?php endif; ?>
                                                                            
                                                                            <?php if ($filiere['nb_etudiants'] > 0): ?>
                                                                                <li><?php echo $filiere['nb_etudiants']; ?> étudiant(s)</li>
                                                                            <?php endif; ?>
                                                                        </ul>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                
                                                                <form method="post" action="gestion_filieres.php" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo escape_html($csrf_token); ?>">
                                                                    <input type="hidden" name="action" value="supprimer">
                                                                    <input type="hidden" name="id" value="<?php echo $filiere['id']; ?>">
                                                                    <button type="submit" class="btn btn-danger" 
                                                                            <?php echo ($filiere['nb_modules'] > 0 || $filiere['nb_etudiants'] > 0) ? 'disabled' : ''; ?>>
                                                                        <i class="fas fa-trash me-2"></i>Supprimer
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>Aucune filière n'a été ajoutée pour le moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>