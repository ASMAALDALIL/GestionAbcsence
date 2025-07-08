<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin();

// Récupérer les filtres
$filiere_id = isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : 0;
$module_id = isset($_GET['module_id']) ? intval($_GET['module_id']) : 0;
$date_debut = isset($_GET['date_debut']) ? $_GET['date_debut'] : '';
$date_fin = isset($_GET['date_fin']) ? $_GET['date_fin'] : '';

// Récupérer les filières pour le filtre
$stmt = $pdo->query("SELECT id, nom, code FROM filieres ORDER BY nom");
$filieres = $stmt->fetchAll();

// Récupérer les modules selon la filière sélectionnée
$modules = [];
if ($filiere_id > 0) {
    $stmt = $pdo->prepare("SELECT id, nom, code FROM modules WHERE filiere_id = ? ORDER BY nom");
    $stmt->execute([$filiere_id]);
    $modules = $stmt->fetchAll();
}

// Construire la requête pour récupérer les présences/absences
$conditions = ["1=1"];
$params = [];

if ($filiere_id > 0) {
    $conditions[] = "f.id = ?";
    $params[] = $filiere_id;
}

if ($module_id > 0) {
    $conditions[] = "m.id = ?";
    $params[] = $module_id;
}

if (!empty($date_debut)) {
    $conditions[] = "s.date_seance >= ?";
    $params[] = $date_debut;
}

if (!empty($date_fin)) {
    $conditions[] = "s.date_seance <= ?";
    $params[] = $date_fin;
}

$where_clause = implode(" AND ", $conditions);

// Requête principale pour récupérer les données
$sql = "
    SELECT 
        s.id as seance_id,
        s.date_seance,
        s.heure_debut,
        s.heure_fin,
        m.nom as module_nom,
        m.code as module_code,
        f.nom as filiere_nom,
        f.code as filiere_code,
        COUNT(DISTINCT im.etudiant_id) as total_inscrits,
        COUNT(DISTINCT CASE WHEN a.absent = 1 OR a.id IS NULL THEN im.etudiant_id END) as total_absents,
        COUNT(DISTINCT CASE WHEN a.absent = 0 THEN a.etudiant_id END) as total_presents
    FROM seances s
    INNER JOIN modules m ON s.module_id = m.id
    INNER JOIN filieres f ON m.filiere_id = f.id
    LEFT JOIN inscriptions_modules im ON m.id = im.module_id
    LEFT JOIN absences a ON s.id = a.seance_id AND a.etudiant_id = im.etudiant_id
    WHERE {$where_clause}
    GROUP BY s.id, s.date_seance, s.heure_debut, s.heure_fin, m.nom, m.code, f.nom, f.code
    ORDER BY s.date_seance DESC, s.heure_debut DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $seances = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Erreur lors de la récupération des données: " . $e->getMessage();
    $seances = [];
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Liste des Présences/Absences - Admin</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .badge-present { background-color: #28a745; }
        .badge-absent { background-color: #dc3545; }
        .badge-total { background-color: #6c757d; }
        .card-stat {
            border-left: 4px solid;
            transition: transform 0.2s;
        }
        .card-stat:hover { transform: translateY(-2px); }
        .card-stat.success { border-left-color: #28a745; }
        .card-stat.danger { border-left-color: #dc3545; }
        .card-stat.info { border-left-color: #17a2b8; }
        .table-hover tbody tr:hover {
            background-color: rgba(0,123,255,.075);
        }
        .progress-sm {
            height: 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="../dashboard_admin.php">
                <i class="fas fa-graduation-cap me-2"></i>
                Admin - Présences/Absences
            </a>
            <div class="navbar-nav ms-auto">
                <a href="../dashboard_admin.php" class="btn btn-outline-light btn-sm">
                    <i class="fas fa-home me-1"></i>Tableau de bord
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Filtres</h5>
            </div>
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="filiere_id" class="form-label">Filière</label>
                        <select class="form-select" id="filiere_id" name="filiere_id" onchange="loadModules()">
                            <option value="">Toutes les filières</option>
                            <?php foreach ($filieres as $filiere): ?>
                                <option value="<?php echo $filiere['id']; ?>" 
                                        <?php echo $filiere_id == $filiere['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($filiere['code'] . ' - ' . $filiere['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="module_id" class="form-label">Module</label>
                        <select class="form-select" id="module_id" name="module_id">
                            <option value="">Tous les modules</option>
                            <?php foreach ($modules as $module): ?>
                                <option value="<?php echo $module['id']; ?>" 
                                        <?php echo $module_id == $module['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($module['code'] . ' - ' . $module['nom']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="date_debut" class="form-label">Date début</label>
                        <input type="date" class="form-control" id="date_debut" name="date_debut" 
                               value="<?php echo htmlspecialchars($date_debut); ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_fin" class="form-label">Date fin</label>
                        <input type="date" class="form-control" id="date_fin" name="date_fin" 
                               value="<?php echo htmlspecialchars($date_fin); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filtrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Statistiques générales -->
        <?php if (count($seances) > 0):
            $total_seances = count($seances);
            $total_inscrits = array_sum(array_column($seances, 'total_inscrits'));
            $total_presents = array_sum(array_column($seances, 'total_presents'));
            $total_absents = $total_inscrits - $total_presents;
            $taux_presence = $total_inscrits > 0 ? round(($total_presents / $total_inscrits) * 100, 1) : 0;
        ?>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card card-stat info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted">Séances</h6>
                                <h3><?php echo $total_seances; ?></h3>
                            </div>
                            <i class="fas fa-calendar fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted">Présents</h6>
                                <h3><?php echo $total_presents; ?></h3>
                            </div>
                            <i class="fas fa-user-check fa-2x text-success"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat danger">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted">Absents</h6>
                                <h3><?php echo $total_absents; ?></h3>
                            </div>
                            <i class="fas fa-user-times fa-2x text-danger"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card card-stat info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="text-muted">Taux présence</h6>
                                <h3><?php echo $taux_presence; ?>%</h3>
                            </div>
                            <i class="fas fa-chart-pie fa-2x text-info"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Tableau des séances -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Liste des séances (<?php echo count($seances); ?> résultat<?php echo count($seances) > 1 ? 's' : ''; ?>)
                </h5>
                <?php if (count($seances) > 0): ?>
                    <div class="btn-group">
                        <button type="button" class="btn btn-outline-success btn-sm" onclick="exportData('excel')">
                            <i class="fas fa-file-excel me-1"></i>Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="exportData('pdf')">
                            <i class="fas fa-file-pdf me-1"></i>PDF
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (count($seances) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-striped">
                            <thead class="table-dark">
                                <tr>
                                    <th>Date</th>
                                    <th>Horaire</th>
                                    <th>Module</th>
                                    <th>Filière</th>
                                    <th class="text-center">Inscrits</th>
                                    <th class="text-center">Présents</th>
                                    <th class="text-center">Absents</th>
                                    <th class="text-center">Taux présence</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($seances as $seance): 
                                    $taux_seance = $seance['total_inscrits'] > 0 ? 
                                        round(($seance['total_presents'] / $seance['total_inscrits']) * 100, 1) : 0;
                                    $progress_class = $taux_seance >= 80 ? 'bg-success' : 
                                                    ($taux_seance >= 60 ? 'bg-warning' : 'bg-danger');
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('l', strtotime($seance['date_seance'])); ?></small>
                                        </td>
                                        <td>
                                            <?php echo date('H:i', strtotime($seance['heure_debut'])); ?> - 
                                            <?php echo date('H:i', strtotime($seance['heure_fin'])); ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($seance['module_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($seance['module_nom']); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($seance['filiere_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($seance['filiere_nom']); ?></small>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-total fs-6"><?php echo $seance['total_inscrits']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-present fs-6"><?php echo $seance['total_presents']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge badge-absent fs-6"><?php echo $seance['total_absents']; ?></span>
                                        </td>
                                        <td class="text-center">
                                            <div class="d-flex flex-column align-items-center">
                                                <strong><?php echo $taux_seance; ?>%</strong>
                                                <div class="progress progress-sm w-100 mt-1">
                                                    <div class="progress-bar <?php echo $progress_class; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo $taux_seance; ?>%">
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm">
                                                <a href="detail_seance.php?id=<?php echo $seance['seance_id']; ?>" 
                                                   class="btn btn-outline-info btn-sm" 
                                                   title="Voir détails">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <a href="modifier_presences.php?seance_id=<?php echo $seance['seance_id']; ?>" 
                                                   class="btn btn-outline-primary btn-sm" 
                                                   title="Modifier présences">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Aucune séance trouvée</h5>
                        <p class="text-muted">Modifiez vos critères de recherche pour voir plus de résultats.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Fonction pour charger les modules selon la filière sélectionnée
        function loadModules() {
            const filiereId = document.getElementById('filiere_id').value;
            const moduleSelect = document.getElementById('module_id');
            
            // Vider les options existantes sauf la première
            moduleSelect.innerHTML = '<option value="">Tous les modules</option>';
            
            if (filiereId) {
                // Faire une requête AJAX pour récupérer les modules
                fetch(`../ajax/get_modules.php?filiere_id=${filiereId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            data.modules.forEach(module => {
                                const option = document.createElement('option');
                                option.value = module.id;
                                option.textContent = `${module.code} - ${module.nom}`;
                                moduleSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Erreur:', error));
            }
        }

        // Fonction pour exporter les données
        function exportData(format) {
            const params = new URLSearchParams(window.location.search);
            params.append('export', format);
            window.location.href = `export_presences.php?${params.toString()}`;
        }

        // Initialiser les tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>