<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier que l'utilisateur est connecté et est un étudiant
checkStudentAuth();

$etudiant_id = $_SESSION['user_id'];

try {
    // Récupérer les informations de l'étudiant
    $stmt = $pdo->prepare("
        SELECT e.nom, e.prenom, e.numero_apogee, f.nom as filiere_nom 
        FROM etudiants e 
        JOIN filieres f ON e.filiere_id = f.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$etudiant_id]);
    $etudiant = $stmt->fetch();

    // Récupérer les absences de l'étudiant avec les détails des modules et séances
    $stmt = $pdo->prepare("
        SELECT 
            m.nom as module_nom,
            m.code as module_code,
            s.date_seance,
            s.heure_debut,
            s.heure_fin,
            a.justifie,
            a.justificatif,
            j.statut as justificatif_statut,
            j.date_soumission,
            j.commentaire
        FROM absences a
        JOIN seances s ON a.seance_id = s.id
        JOIN modules m ON s.module_id = m.id
        LEFT JOIN justificatifs j ON (j.etudiant_id = a.etudiant_id AND j.module_id = m.id AND j.date_absence = s.date_seance)
        WHERE a.etudiant_id = ?
        ORDER BY s.date_seance DESC, m.nom
    ");
    $stmt->execute([$etudiant_id]);
    $absences = $stmt->fetchAll();

    // Statistiques d'absences par module
    $stmt = $pdo->prepare("
        SELECT 
            m.nom as module_nom,
            COUNT(a.id) as nombre_absences,
            SUM(CASE WHEN a.justifie = 1 THEN 1 ELSE 0 END) as absences_justifiees
        FROM modules m
        JOIN inscriptions_modules im ON m.id = im.module_id
        LEFT JOIN seances s ON m.id = s.module_id
        LEFT JOIN absences a ON (s.id = a.seance_id AND a.etudiant_id = ?)
        WHERE im.etudiant_id = ?
        GROUP BY m.id, m.nom
        ORDER BY m.nom
    ");
    $stmt->execute([$etudiant_id, $etudiant_id]);
    $stats_modules = $stmt->fetchAll();

} catch(PDOException $e) {
    die("Erreur de base de données : " . $e->getMessage());
}

include '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">
                        <i class="fas fa-chart-bar"></i> 
                        Bilan des Absences - <?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?>
                    </h3>
                    <small>Apogée: <?php echo htmlspecialchars($etudiant['numero_apogee']); ?> | 
                           Filière: <?php echo htmlspecialchars($etudiant['filiere_nom']); ?></small>
                </div>
                <div class="card-body">
                    
                    <!-- Statistiques générales -->
                    <div class="row mb-4">
                        <div class="col-md-12">
                            <h4>Résumé par Module</h4>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Module</th>
                                            <th>Total Absences</th>
                                            <th>Absences Justifiées</th>
                                            <th>Absences Non Justifiées</th>
                                            <th>Pourcentage Justifié</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($stats_modules as $stat): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($stat['module_nom']); ?></td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <?php echo $stat['nombre_absences']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-success">
                                                    <?php echo $stat['absences_justifiees']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge badge-danger">
                                                    <?php echo $stat['nombre_absences'] - $stat['absences_justifiees']; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php 
                                                if($stat['nombre_absences'] > 0) {
                                                    $pourcentage = round(($stat['absences_justifiees'] / $stat['nombre_absences']) * 100);
                                                    echo $pourcentage . '%';
                                                } else {
                                                    echo 'N/A';
                                                }
                                                ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Détail des absences -->
                    <div class="row">
                        <div class="col-md-12">
                            <h4>Détail des Absences</h4>
                            <?php if(empty($absences)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i> 
                                    Aucune absence enregistrée pour le moment.
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="thead-dark">
                                            <tr>
                                                <th>Date</th>
                                                <th>Module</th>
                                                <th>Horaire</th>
                                                <th>Statut</th>
                                                <th>Justificatif</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($absences as $absence): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($absence['date_seance'])); ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($absence['module_nom']); ?></strong>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($absence['module_code']); ?></small>
                                                </td>
                                                <td>
                                                    <?php echo date('H:i', strtotime($absence['heure_debut'])); ?> - 
                                                    <?php echo date('H:i', strtotime($absence['heure_fin'])); ?>
                                                </td>
                                                <td>
                                                    <?php if($absence['justifie']): ?>
                                                        <span class="badge badge-success">
                                                            <i class="fas fa-check"></i> Justifiée
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge badge-danger">
                                                            <i class="fas fa-times"></i> Non justifiée
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if($absence['justificatif_statut']): ?>
                                                        <span class="badge badge-<?php 
                                                            echo $absence['justificatif_statut'] == 'accepté' ? 'success' : 
                                                                ($absence['justificatif_statut'] == 'rejeté' ? 'danger' : 'warning');
                                                        ?>">
                                                            <?php echo ucfirst($absence['justificatif_statut']); ?>
                                                        </span>
                                                        <?php if($absence['date_soumission']): ?>
                                                            <br><small class="text-muted">
                                                                Soumis le <?php echo date('d/m/Y', strtotime($absence['date_soumission'])); ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if($absence['commentaire']): ?>
                                                            <br><small class="text-info" title="<?php echo htmlspecialchars($absence['commentaire']); ?>">
                                                                <i class="fas fa-comment"></i> Commentaire admin
                                                            </small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Aucun</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if(!$absence['justifie'] && !$absence['justificatif_statut']): ?>
                                                        <a href="../dashboard_etudiant.php#justificatif" class="btn btn-sm btn-primary">
                                                            <i class="fas fa-upload"></i> Ajouter Justificatif
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <a href="../dashboard_etudiant.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Retour au Dashboard
                            </a>
                            <button onclick="window.print()" class="btn btn-info">
                                <i class="fas fa-print"></i> Imprimer le Bilan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Styles pour l'impression -->
<style>
@media print {
    .btn, .card-header, .navbar, .footer {
        display: none !important;
    }
    .container {
        max-width: 100% !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
}
</style>

<?php include '../includes/footer.php'; ?>