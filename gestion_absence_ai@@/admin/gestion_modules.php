<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../index.php');
    exit();
}

// Initialiser les variables
$message = '';
$messageType = '';
$module = [
    'id' => '', 
    'code' => '', 
    'nom' => '', 
    'filiere_id' => '',
    'responsable_id' => '',
    'semestre' => ''
];
$isEditing = false;

// Traitement de la suppression
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Vérifier si le module existe
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() > 0) {
        try {
            // Supprimer le module
            $stmt = $pdo->prepare("DELETE FROM modules WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Le module a été supprimé avec succès.";
            $messageType = "success";
        } catch (PDOException $e) {
            $message = "Erreur lors de la suppression du module.";
            $messageType = "danger";
        }
    } else {
        $message = "Le module spécifié n'existe pas.";
        $messageType = "danger";
    }
}

// Traitement de l'édition - Chargement des données
if (isset($_GET['edit']) && !empty($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE id = ?");
    $stmt->execute([$id]);
    $module = $stmt->fetch();
    
    if ($module) {
        $isEditing = true;
    } else {
        $message = "Le module spécifié n'existe pas.";
        $messageType = "danger";
    }
}

// Traitement du formulaire d'ajout/modification
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = $_POST['code'] ?? '';
    $nom = $_POST['nom'] ?? '';
    $filiere_id = $_POST['filiere_id'] ?? '';
    $responsable_id = $_POST['responsable_id'] ?? '';
    $semestre = $_POST['semestre'] ?? '';
    
    // Validation
    if (empty($code) || empty($nom) || empty($filiere_id) || empty($responsable_id) || empty($semestre)) {
        $message = "Tous les champs sont obligatoires.";
        $messageType = "danger";
    } else {
        if (isset($_POST['id']) && !empty($_POST['id'])) { // Modification
            $id = $_POST['id'];
            
            try {
                $stmt = $pdo->prepare("UPDATE modules SET code = ?, nom = ?, filiere_id = ?, responsable_id = ?, semestre = ? WHERE id = ?");
                $stmt->execute([$code, $nom, $filiere_id, $responsable_id, $semestre, $id]);
                $message = "Le module a été mis à jour avec succès.";
                $messageType = "success";
                $isEditing = false;
                $module = ['id' => '', 'code' => '', 'nom' => '', 'filiere_id' => '', 'responsable_id' => '', 'semestre' => ''];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Erreur de contrainte unique
                    $message = "Le code de module est déjà utilisé.";
                } else {
                    $message = "Erreur lors de la mise à jour du module: " . $e->getMessage();
                }
                $messageType = "danger";
            }
        } else { // Ajout
            try {
                $stmt = $pdo->prepare("INSERT INTO modules (code, nom, filiere_id, responsable_id, semestre) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$code, $nom, $filiere_id, $responsable_id, $semestre]);
                $message = "Le module a été ajouté avec succès.";
                $messageType = "success";
                $module = ['id' => '', 'code' => '', 'nom' => '', 'filiere_id' => '', 'responsable_id' => '', 'semestre' => ''];
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Erreur de contrainte unique
                    $message = "Le code de module est déjà utilisé.";
                } else {
                    $message = "Erreur lors de l'ajout du module: " . $e->getMessage();
                }
                $messageType = "danger";
            }
        }
    }
}

// Récupérer toutes les filières
$stmt = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY code");
$filieres = $stmt->fetchAll();

// Récupérer tous les responsables
$stmt = $pdo->query("SELECT id, nom, prenom FROM responsables ORDER BY nom, prenom");
$responsables = $stmt->fetchAll();

// Récupérer tous les modules avec infos des filières et responsables
$stmt = $pdo->query("SELECT m.*, f.code as filiere_code, f.nom as filiere_nom, 
                    r.nom as responsable_nom, r.prenom as responsable_prenom
                    FROM modules m
                    JOIN filieres f ON m.filiere_id = f.id
                    JOIN responsables r ON m.responsable_id = r.id
                    ORDER BY m.semestre, m.code");
$modules = $stmt->fetchAll();

// Inclure l'en-tête
$title = "Gestion des modules";
include '../includes/header.php';
?>

<div class="container mt-4">
    <h1>Gestion des modules</h1>
    
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="../dashboard_admin.php">Tableau de bord</a></li>
            <li class="breadcrumb-item active" aria-current="page">Gestion des modules</li>
        </ol>
    </nav>
    
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" role="alert">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <?php echo $isEditing ? 'Modifier un module' : 'Ajouter un module'; ?>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if ($isEditing): ?>
                            <input type="hidden" name="id" value="<?php echo htmlspecialchars($module['id']); ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="code" class="form-label">Code du module</label>
                            <input type="text" class="form-control" id="code" name="code" value="<?php echo htmlspecialchars($module['code']); ?>" required>
                            <small class="form-text text-muted">Ex: GI-S1-M1</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="nom" class="form-label">Nom du module</label>
                            <input type="text" class="form-control" id="nom" name="nom" value="<?php echo htmlspecialchars($module['nom']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="filiere_id" class="form-label">Filière</label>
                            <select class="form-select" id="filiere_id" name="filiere_id" required>
                                <option value="">Sélectionnez une filière</option>
                                <?php foreach ($filieres as $filiere): ?>
                                    <option value="<?php echo $filiere['id']; ?>" <?php echo ($module['filiere_id'] == $filiere['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="responsable_id" class="form-label">Responsable</label>
                            <select class="form-select" id="responsable_id" name="responsable_id" required>
                                <option value="">Sélectionnez un responsable</option>
                                <?php foreach ($responsables as $responsable): ?>
                                    <option value="<?php echo $responsable['id']; ?>" <?php echo ($module['responsable_id'] == $responsable['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($responsable['prenom'] . ' ' . $responsable['nom']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="semestre" class="form-label">Semestre</label>
                            <select class="form-select" id="semestre" name="semestre" required>
                                <option value="">Sélectionnez un semestre</option>
                                <option value="S1" <?php echo ($module['semestre'] == 'S1') ? 'selected' : ''; ?>>S1</option>
                                <option value="S2" <?php echo ($module['semestre'] == 'S2') ? 'selected' : ''; ?>>S2</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <?php echo $isEditing ? 'Mettre à jour' : 'Ajouter'; ?>
                        </button>
                        
                        <?php if ($isEditing): ?>
                            <a href="gestion_modules.php" class="btn btn-secondary">Annuler</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    Gestion des responsables
                </div>
                <div class="card-body">
                    <a href="#" class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#responsableModal">
                        <i class="fas fa-user-plus"></i> Ajouter un responsable
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    Liste des modules
                </div>
                <div class="card-body">
                    <?php if (count($modules) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Nom</th>
                                        <th>Filière</th>
                                        <th>Responsable</th>
                                        <th>Semestre</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($modules as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['code']); ?></td>
                                            <td><?php echo htmlspecialchars($item['nom']); ?></td>
                                            <td><?php echo htmlspecialchars($item['filiere_code']); ?></td>
                                            <td><?php echo htmlspecialchars($item['responsable_prenom'] . ' ' . $item['responsable_nom']); ?></td>
                                            <td>
                                                <span class="badge <?php echo ($item['semestre'] == 'S1') ? 'bg-primary' : 'bg-success'; ?>">
                                                    <?php echo $item['semestre']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="gestion_modules.php?edit=<?php echo $item['id']; ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i> Modifier
                                                </a>
                                                <a href="#" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $item['id']; ?>">
                                                    <i class="fas fa-trash"></i> Supprimer
                                                </a>
                                                
                                                <!-- Modal de confirmation de suppression -->
                                                <div class="modal fade" id="deleteModal<?php echo $item['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $item['id']; ?>" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="deleteModalLabel<?php echo $item['id']; ?>">Confirmation de suppression</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Êtes-vous sûr de vouloir supprimer le module <strong><?php echo htmlspecialchars($item['nom']); ?></strong> ?
                                                                <br>
                                                                <div class="alert alert-warning mt-2" role="alert">
                                                                    Attention : Cette action supprimera également toutes les séances et absences associées à ce module.
                                                                </div>
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                                                                <a href="gestion_modules.php?delete=<?php echo $item['id']; ?>" class="btn btn-danger">Supprimer</a>
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
                        <div class="alert alert-info" role="alert">
                            Aucun module n'a été ajouté pour le moment.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal d'ajout de responsable -->
<div class="modal fade" id="responsableModal" tabindex="-1" aria-labelledby="responsableModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="responsableModalLabel">Ajouter un responsable</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="responsableForm" action="ajouter_responsable.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="resp_nom" class="form-label">Nom</label>
                        <input type="text" class="form-control" id="resp_nom" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label for="resp_prenom" class="form-label">Prénom</label>
                        <input type="text" class="form-control" id="resp_prenom" name="prenom" required>
                    </div>
                    <div class="mb-3">
                        <label for="resp_email" class="form-label">Email</label>
                        <input type="email" class="form-control" id="resp_email" name="email" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Ajouter</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Script pour gestion ajax des responsables
    document.getElementById('responsableForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        
        fetch('ajouter_responsable.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Responsable ajouté avec succès !');
                location.reload();
            } else {
                alert('Erreur: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Une erreur est survenue lors de l\'ajout du responsable.');
        });
    });
</script>

<?php include '../includes/footer.php'; ?>