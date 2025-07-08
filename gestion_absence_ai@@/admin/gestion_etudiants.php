<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin();

// Variables pour filtrer les étudiants
$filiere_id = isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Récupérer toutes les filières pour le filtre
$stmt = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY nom");
$filieres = $stmt->fetchAll();

// Construction de la requête SQL pour les étudiants avec filtres
$sql = "SELECT e.id, e.numero_apogee, e.nom, e.prenom, e.email, e.created_at, 
               f.code as filiere_code, f.nom as filiere_nom
        FROM etudiants e
        LEFT JOIN filieres f ON e.filiere_id = f.id
        WHERE 1=1";
$params = [];

if ($filiere_id > 0) {
    $sql .= " AND e.filiere_id = ?";
    $params[] = $filiere_id;
}

if (!empty($search)) {
    $sql .= " AND (e.nom LIKE ? OR e.prenom LIKE ? OR e.numero_apogee LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

$sql .= " ORDER BY e.nom, e.prenom";

// Exécuter la requête
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$etudiants = $stmt->fetchAll();

// Inclure l'en-tête
$title = "Gestion des étudiants";
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-user-graduate"></i> Gestion des étudiants</h1>
        <a href="../dashboard_admin.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>
    
    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5 class="mb-0"><i class="fas fa-filter"></i> Filtres</h5>
        </div>
        <div class="card-body">
            <form method="get" action="" class="row g-3">
                <div class="col-md-5">
                    <label for="filiere_id" class="form-label">Filière</label>
                    <select class="form-select" id="filiere_id" name="filiere_id">
                        <option value="0">Toutes les filières</option>
                        <?php foreach ($filieres as $filiere): ?>
                            <option value="<?php echo $filiere['id']; ?>" <?php echo ($filiere_id == $filiere['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label for="search" class="form-label">Recherche</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Nom, prénom ou n° Apogée" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filtrer
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Résultats -->
    <div class="card">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="fas fa-list"></i> Liste des étudiants</h5>
            <span class="badge bg-primary"><?php echo count($etudiants); ?> étudiant(s)</span>
        </div>
        <div class="card-body">
            <?php if (count($etudiants) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>N° Apogée</th>
                                <th>Nom complet</th>
                                <th>Email</th>
                                <th>Filière</th>
                                <th>Date d'inscription</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($etudiants as $etudiant): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($etudiant['numero_apogee']); ?></td>
                                    <td><?php echo htmlspecialchars($etudiant['nom'] . ' ' . $etudiant['prenom']); ?></td>
                                    <td><?php echo htmlspecialchars($etudiant['email']); ?></td>
                                    <td>
                                        <span class="badge bg-info text-dark">
                                            <?php echo htmlspecialchars($etudiant['filiere_code']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d/m/Y', strtotime($etudiant['created_at'])); ?></td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info view-modules" 
                                                data-bs-toggle="modal" data-bs-target="#modulesModal" 
                                                data-etudiant-id="<?php echo $etudiant['id']; ?>"
                                                data-etudiant-name="<?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?>">
                                            <i class="fas fa-book"></i> Modules
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Aucun étudiant trouvé.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal pour afficher les modules d'un étudiant -->
<div class="modal fade" id="modulesModal" tabindex="-1" aria-labelledby="modulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modulesModalLabel">Modules de l'étudiant</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modulesList">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2">Chargement des modules...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gérer l'affichage des modules d'un étudiant
    const viewModulesButtons = document.querySelectorAll('.view-modules');
    
    viewModulesButtons.forEach(button => {
        button.addEventListener('click', function() {
            const etudiantId = this.getAttribute('data-etudiant-id');
            const etudiantName = this.getAttribute('data-etudiant-name');
            
            // Mettre à jour le titre de la modal
            document.getElementById('modulesModalLabel').textContent = `Modules de ${etudiantName}`;
            
            // Afficher l'indicateur de chargement
            document.getElementById('modulesList').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2">Chargement des modules...</p>
                </div>
            `;
            
            // Récupérer les modules de l'étudiant via AJAX
            fetch(`get_student_modules.php?etudiant_id=${etudiantId}`)
                .then(response => response.json())
                .then(data => {
                    let html = '';
                    
                    if (data.length > 0) {
                        html = '<div class="table-responsive"><table class="table table-striped">';
                        html += '<thead class="table-dark"><tr><th>Code</th><th>Module</th><th>Semestre</th><th>Responsable</th></tr></thead>';
                        html += '<tbody>';
                        
                        data.forEach(module => {
                            html += `<tr>
                                <td>${module.code}</td>
                                <td>${module.nom}</td>
                                <td><span class="badge ${module.semestre === 'S1' ? 'bg-primary' : 'bg-success'}">${module.semestre}</span></td>
                                <td>${module.responsable || 'Non assigné'}</td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                    } else {
                        html = '<div class="alert alert-info"><i class="fas fa-info-circle"></i> Cet étudiant n\'est inscrit à aucun module.</div>';
                    }
                    
                    document.getElementById('modulesList').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('modulesList').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Une erreur est survenue lors du chargement des modules.
                        </div>
                    `;
                    console.error('Erreur:', error);
                });
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>