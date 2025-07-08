<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin();

// Variables pour les filtres
$filiere_id = isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : 0;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$etudiant_id = isset($_GET['etudiant_id']) ? intval($_GET['etudiant_id']) : 0;
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Récupérer les filières pour le formulaire de filtre
$stmt = $pdo->query("SELECT id, code, nom FROM filieres ORDER BY nom");
$filieres = $stmt->fetchAll();

// Récupérer les modules pour le formulaire de filtre (dépend de la filière)
$modules = [];
if ($filiere_id > 0) {
    $stmt = $pdo->prepare("SELECT id, code, nom FROM modules WHERE filiere_id = ? ORDER BY semestre, nom");
    $stmt->execute([$filiere_id]);
    $modules = $stmt->fetchAll();
}

// Récupérer les étudiants pour le formulaire de filtre (dépend de la filière)
$etudiants = [];
if ($filiere_id > 0) {
    $stmt = $pdo->prepare("SELECT id, numero_apogee, nom, prenom FROM etudiants WHERE filiere_id = ? ORDER BY nom, prenom");
    $stmt->execute([$filiere_id]);
    $etudiants = $stmt->fetchAll();
}

// Construction de la requête pour les absences avec filtres
$query = "SELECT a.id, a.date_creation, s.date, e.numero_apogee, e.nom as etudiant_nom, e.prenom as etudiant_prenom, 
                 m.code as module_code, m.nom as module_nom, f.code as filiere_code,
                 CASE WHEN j.id IS NOT NULL THEN 1 ELSE 0 END as justifie,
                 j.statut as statut_justificatif
          FROM absences a
          JOIN seances s ON a.seance_id = s.id
          JOIN etudiants e ON a.etudiant_id = e.id
          JOIN modules m ON s.module_id = m.id
          JOIN filieres f ON m.filiere_id = f.id
          LEFT JOIN justificatifs j ON j.etudiant_id = e.id AND j.module_id = m.id AND j.date_absence = s.date
          WHERE 1=1";

$params = [];

if ($filiere_id > 0) {
    $query .= " AND f.id = ?";
    $params[] = $filiere_id;
}

if ($module_id > 0) {
    $query .= " AND m.id = ?";
    $params[] = $module_id;
}

if ($etudiant_id > 0) {
    $query .= " AND e.id = ?";
    $params[] = $etudiant_id;
}

if (!empty($date_debut)) {
    $query .= " AND s.date >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $query .= " AND s.date <= ?";
    $params[] = $date_fin;
}

$query .= " ORDER BY s.date DESC, e.nom, e.prenom";

// Exécuter la requête
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$absences = $stmt->fetchAll();

// Inclure l'en-tête
$title = "Gestion des absences";
include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1><i class="fas fa-calendar-times"></i> Gestion des absences</h1>
        <a href="../dashboard_admin.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour au tableau de bord
        </a>
    </div>

    <!-- Formulaire de filtres -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5><i class="fas fa-filter"></i> Filtres</h5>
        </div>
        <div class="card-body">
            <form action="" method="get" id="filterForm">
                <div class="row">
                    <div class="col-md-4 mb-3">
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
                    
                    <div class="col-md-4 mb-3">
                        <label for="module_id" class="form-label">Module</label>
                        <select class="form-select" id="module_id" name="module_id">
                            <option value="0">Tous les modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo $module['id']; ?>" <?php echo ($module_id == $module['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="etudiant_id" class="form-label">Étudiant</label>
                        <select class="form-select" id="etudiant_id" name="etudiant_id">
                            <option value="0">Tous les étudiants</option>
                            <?php foreach ($etudiants as $etudiant): ?>
                                <option value="<?php echo $etudiant['id']; ?>" <?php echo ($etudiant_id == $etudiant['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($etudiant['numero_apogee'] . ' - ' . $etudiant['nom'] . ' ' . $etudiant['prenom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="date_debut" class="form-label">Date début</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" value="<?php echo $date_debut; ?>">
                    </div>
                    
                    <div class="col-md-4 mb-3">
                        <label for="date_fin" class="form-label">Date fin</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" value="<?php echo $date_fin; ?>">
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="d-grid gap-2 w-100">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrer
                            </button>
                            <a href="gestion_absences.php" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i> Réinitialiser
                            </a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Actions rapides -->
    <div class="card mb-4">
        <div class="card-header bg-light">
            <h5><i class="fas fa-bolt"></i> Actions rapides</h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <a href="../generer_rapport.php<?php echo ($filiere_id > 0 || $module_id > 0) ? "?filiere_id=$filiere_id&module_id=$module_id" : ''; ?>" class="btn btn-danger w-100 mb-2">
                        <i class="fas fa-file-pdf"></i> Générer rapport PDF
                    </a>
                </div>
                <div class="col-md-6">
                    <button class="btn btn-success w-100 mb-2" data-bs-toggle="modal" data-bs-target="#addAbsenceModal">
                        <i class="fas fa-plus"></i> Ajouter une absence
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tableau des absences -->
    <div class="card">
        <div class="card-header bg-light">
            <div class="d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-table"></i> Liste des absences</h5>
                <span class="badge bg-primary"><?php echo count($absences); ?> absence(s)</span>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($absences) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Étudiant</th>
                                <th>Filière</th>
                                <th>Module</th>
                                <th>Justifié</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($absences as $index => $absence): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($absence['date'])); ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($absence['etudiant_nom'] . ' ' . $absence['etudiant_prenom']); ?>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars($absence['numero_apogee']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($absence['filiere_code']); ?></td>
                                    <td><?php echo htmlspecialchars($absence['module_code'] . ' - ' . $absence['module_nom']); ?></td>
                                    <td>
                                        <?php if ($absence['justifie']): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-check"></i> 
                                                <?php echo $absence['statut_justificatif'] == 'accepté' ? 'Accepté' : 
                                                     ($absence['statut_justificatif'] == 'rejeté' ? 'Rejeté' : 'En attente'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i> Non</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <button class="btn btn-sm btn-outline-secondary" title="Voir les détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-danger" title="Supprimer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle"></i> Aucune absence trouvée avec les filtres actuels.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal d'ajout d'absence -->
<div class="modal fade" id="addAbsenceModal" tabindex="-1" aria-labelledby="addAbsenceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addAbsenceModalLabel">Ajouter une absence</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addAbsenceForm" action="add_absence.php" method="post">
                    <div class="mb-3">
                        <label for="modal_filiere_id" class="form-label">Filière</label>
                        <select class="form-select" id="modal_filiere_id" name="filiere_id" required>
                            <option value="">Sélectionnez une filière</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo $filiere['id']; ?>">
                                    <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_module_id" class="form-label">Module</label>
                        <select class="form-select" id="modal_module_id" name="module_id" required disabled>
                            <option value="">Sélectionnez d'abord une filière</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal_etudiant_id" class="form-label">Étudiant</label>
                        <select class="form-select" id="modal_etudiant_id" name="etudiant_id" required disabled>
                            <option value="">Sélectionnez d'abord une filière</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="date_absence" class="form-label">Date de l'absence</label>
                        <input type="date" class="form-control" id="date_absence" name="date_absence" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="submit" form="addAbsenceForm" class="btn btn-primary">Enregistrer</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gestion des filtres dynamiques pour le formulaire principal
    const filiereSelect = document.getElementById('filiere_id');
    const moduleSelect = document.getElementById('module_id');
    const etudiantSelect = document.getElementById('etudiant_id');
    
    filiereSelect.addEventListener('change', function() {
        const filiereId = this.value;
        
        // Réinitialiser les sélecteurs dépendants
        moduleSelect.innerHTML = '<option value="0">Tous les modules</option>';
        etudiantSelect.innerHTML = '<option value="0">Tous les étudiants</option>';
        
        if (filiereId > 0) {
            // Charger les modules pour cette filière
            fetch(`../get_modules.php?filiere_id=${filiereId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(module => {
                        const option = document.createElement('option');
                        option.value = module.id;
                        option.textContent = `${module.code} - ${module.nom}`;
                        moduleSelect.appendChild(option);
                    });
                });
                
            // Charger les étudiants pour cette filière
            fetch(`get_etudiants.php?filiere_id=${filiereId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(etudiant => {
                        const option = document.createElement('option');
                        option.value = etudiant.id;
                        option.textContent = `${etudiant.numero_apogee} - ${etudiant.nom} ${etudiant.prenom}`;
                        etudiantSelect.appendChild(option);
                    });
                });
        }
    });
    
    // Gestion des filtres dynamiques pour le modal d'ajout
    const modalFiliereSelect = document.getElementById('modal_filiere_id');
    const modalModuleSelect = document.getElementById('modal_module_id');
    const modalEtudiantSelect = document.getElementById('modal_etudiant_id');
    
    modalFiliereSelect.addEventListener('change', function() {
        const filiereId = this.value;
        
        // Réinitialiser et désactiver les sélecteurs dépendants
        modalModuleSelect.innerHTML = '<option value="">Sélectionnez un module</option>';
        modalEtudiantSelect.innerHTML = '<option value="">Sélectionnez un étudiant</option>';
        
        if (filiereId) {
            // Activer les sélecteurs
            modalModuleSelect.disabled = false;
            modalEtudiantSelect.disabled = false;
            
            // Charger les modules pour cette filière
            fetch(`../get_modules.php?filiere_id=${filiereId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(module => {
                        const option = document.createElement('option');
                        option.value = module.id;
                        option.textContent = `${module.code} - ${module.nom}`;
                        modalModuleSelect.appendChild(option);
                    });
                });
                
            // Charger les étudiants pour cette filière
            fetch(`get_etudiants.php?filiere_id=${filiereId}`)
                .then(response => response.json())
                .then(data => {
                    data.forEach(etudiant => {
                        const option = document.createElement('option');
                        option.value = etudiant.id;
                        option.textContent = `${etudiant.numero_apogee} - ${etudiant.nom} ${etudiant.prenom}`;
                        modalEtudiantSelect.appendChild(option);
                    });
                });
        } else {
            // Désactiver les sélecteurs
            modalModuleSelect.disabled = true;
            modalEtudiantSelect.disabled = true;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>