<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'étudiant
require_etudiant();

// Obtenir les informations de l'étudiant connecté
$etudiant_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT e.*, f.nom as filiere_nom 
                       FROM etudiants e 
                       JOIN filieres f ON e.filiere_id = f.id 
                       WHERE e.id = ?");
$stmt->execute([$etudiant_id]);
$etudiant = $stmt->fetch();

// Récupérer les modules de l'étudiant avec statistiques détaillées
$stmt = $pdo->prepare("
    SELECT m.id, m.code, m.nom, m.semestre,
           COUNT(DISTINCT s.id) as total_seances,
           COUNT(DISTINCT CASE WHEN a.absent = 1 THEN a.id END) as absences,
           COUNT(DISTINCT CASE WHEN a.absent = 0 THEN a.id END) as presences
    FROM modules m 
    JOIN inscriptions_modules im ON m.id = im.module_id 
    LEFT JOIN seances s ON m.id = s.module_id
    LEFT JOIN absences a ON s.id = a.seance_id AND a.etudiant_id = ?
    WHERE im.etudiant_id = ?
    GROUP BY m.id, m.code, m.nom, m.semestre
    ORDER BY m.semestre, m.nom
");
$stmt->execute([$etudiant_id, $etudiant_id]);
$modules = $stmt->fetchAll();

// Récupérer les séances du jour pour l'étudiant
$stmt = $pdo->prepare("
    SELECT s.*, m.code as module_code, m.nom as module_nom,
           a.absent as presence_status,
           r.nom as responsable_nom, r.prenom as responsable_prenom
    FROM seances s
    JOIN modules m ON s.module_id = m.id
    JOIN inscriptions_modules im ON m.id = im.module_id
    JOIN responsables r ON m.responsable_id = r.id
    LEFT JOIN absences a ON s.id = a.seance_id AND a.etudiant_id = ?
    WHERE im.etudiant_id = ? 
    AND DATE(s.date_seance) = CURDATE()
    ORDER BY s.heure_debut
");
$stmt->execute([$etudiant_id, $etudiant_id]);
$seances_aujourd_hui = $stmt->fetchAll();

// Récupérer les prochaines séances (7 prochains jours)
$stmt = $pdo->prepare("
    SELECT s.*, m.code as module_code, m.nom as module_nom,
           r.nom as responsable_nom, r.prenom as responsable_prenom
    FROM seances s
    JOIN modules m ON s.module_id = m.id
    JOIN inscriptions_modules im ON m.id = im.module_id
    JOIN responsables r ON m.responsable_id = r.id
    WHERE im.etudiant_id = ? 
    AND s.date_seance BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 7 DAY
    ORDER BY s.date_seance, s.heure_debut
    LIMIT 5
");
$stmt->execute([$etudiant_id]);
$prochaines_seances = $stmt->fetchAll();

// Inclure l'en-tête
$title = "Tableau de bord étudiant";
include 'includes/header.php';
?>

<style>
.qr-scanner-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.stats-card {
    border-radius: 15px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}

.stats-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.seance-card {
    border-left: 4px solid;
    margin-bottom: 15px;
    transition: all 0.3s;
}

.seance-card:hover {
    transform: translateX(5px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.seance-en-cours {
    border-left-color: #28a745;
    background: linear-gradient(90deg, rgba(40,167,69,0.1) 0%, rgba(255,255,255,0) 100%);
}

.seance-a-venir {
    border-left-color: #ffc107;
    background: linear-gradient(90deg, rgba(255,193,7,0.1) 0%, rgba(255,255,255,0) 100%);
}

.seance-terminee {
    border-left-color: #dc3545;
    background: linear-gradient(90deg, rgba(220,53,69,0.1) 0%, rgba(255,255,255,0) 100%);
}

.presence-badge {
    font-size: 0.8em;
    padding: 0.3em 0.6em;
    border-radius: 50px;
}

.btn-scan-qr {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    border: none;
    border-radius: 50px;
    padding: 15px 30px;
    color: white;
    font-weight: bold;
    text-decoration: none;
    display: inline-block;
    transition: all 0.3s;
    box-shadow: 0 4px 15px rgba(0,0,0,0.2);
}

.btn-scan-qr:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.3);
    color: white;
    text-decoration: none;
}

.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}

#qr-reader {
    width: 100%;
    max-width: 400px;
    margin: 0 auto;
}

.modal-qr {
    backdrop-filter: blur(10px);
}
</style>

<div class="container mt-4">
    <!-- En-tête avec informations de l'étudiant -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <?php if (!empty($etudiant['photo'])): ?>
                                <img src="<?php echo htmlspecialchars($etudiant['photo']); ?>" 
                                     class="img-fluid rounded-circle" alt="Photo de profil" style="width: 80px; height: 80px; object-fit: cover;">
                            <?php else: ?>
                                <div class="bg-primary text-white d-flex align-items-center justify-content-center rounded-circle" 
                                     style="width: 80px; height: 80px;">
                                    <i class="fas fa-user fa-2x"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-10">
                            <h1 class="mb-0">Bienvenue, <?php echo htmlspecialchars($etudiant['prenom'] . ' ' . $etudiant['nom']); ?></h1>
                            <p class="text-muted mb-1">
                                <i class="fas fa-id-card me-2"></i><?php echo htmlspecialchars($etudiant['numero_apogee']); ?> | 
                                <i class="fas fa-graduation-cap me-2"></i><?php echo htmlspecialchars($etudiant['filiere_nom']); ?>
                            </p>
                            <p class="text-muted mb-0">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($etudiant['email']); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scanner QR Code -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="qr-scanner-card text-center">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h3><i class="fas fa-qrcode me-3"></i>Scanner QR Code</h3>
                        <p class="mb-0">Scannez le QR code affiché par votre professeur pour marquer votre présence</p>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-scan-qr pulse-animation" data-bs-toggle="modal" data-bs-target="#qrScannerModal">
                            <i class="fas fa-camera me-2"></i>Ouvrir le Scanner
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Séances d'aujourd'hui -->
    <?php if (count($seances_aujourd_hui) > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h3><i class="fas fa-calendar-day me-2 text-primary"></i>Séances d'aujourd'hui</h3>
            <?php foreach ($seances_aujourd_hui as $seance): ?>
                <?php
                $now = new DateTime();
                $debut = new DateTime($seance['date_seance'] . ' ' . $seance['heure_debut']);
                $fin = new DateTime($seance['date_seance'] . ' ' . $seance['heure_fin']);
                
                $status_class = 'seance-a-venir';
                $status_text = 'À venir';
                $status_icon = 'clock';
                
                if ($now >= $debut && $now <= $fin) {
                    $status_class = 'seance-en-cours';
                    $status_text = 'En cours';
                    $status_icon = 'play-circle';
                } elseif ($now > $fin) {
                    $status_class = 'seance-terminee';
                    $status_text = 'Terminée';
                    $status_icon = 'check-circle';
                }
                
                $presence_badge = '';
                $presence_icon = '';
                if (isset($seance['presence_status'])) {
                    if ($seance['presence_status'] == 0) {
                        $presence_badge = '<span class="presence-badge badge bg-success ms-2"><i class="fas fa-check me-1"></i>Présent</span>';
                    } else {
                        $presence_badge = '<span class="presence-badge badge bg-danger ms-2"><i class="fas fa-times me-1"></i>Absent</span>';
                    }
                } else {
                    $presence_badge = '<span class="presence-badge badge bg-secondary ms-2"><i class="fas fa-question me-1"></i>Non marqué</span>';
                }
                ?>
                <div class="card seance-card <?php echo $status_class; ?>">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-1">
                                    <?php echo htmlspecialchars($seance['module_code'] . ' - ' . $seance['module_nom']); ?>
                                    <?php echo $presence_badge; ?>
                                </h5>
                                <p class="card-text text-muted mb-2">
                                    <i class="fas fa-user me-2"></i><?php echo htmlspecialchars($seance['responsable_prenom'] . ' ' . $seance['responsable_nom']); ?>
                                </p>
                                <p class="card-text">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo substr($seance['heure_debut'], 0, 5) . ' - ' . substr($seance['heure_fin'], 0, 5); ?>
                                    <span class="badge bg-<?php echo $status_class === 'seance-en-cours' ? 'success' : ($status_class === 'seance-a-venir' ? 'warning' : 'secondary'); ?> ms-2">
                                        <i class="fas fa-<?php echo $status_icon; ?> me-1"></i><?php echo $status_text; ?>
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-4 text-end">
                                <?php if ($status_class === 'seance-en-cours' || ($status_class === 'seance-terminee' && !isset($seance['presence_status']))): ?>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="openQRScanner()">
                                        <i class="fas fa-qrcode me-2"></i>Scanner QR
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Statistiques des modules -->
    <div class="row mb-4">
        <div class="col-12">
            <h3><i class="fas fa-chart-bar me-2 text-primary"></i>Mes modules</h3>
        </div>
        <?php if (count($modules) > 0): ?>
            <?php foreach ($modules as $module): ?>
                <div class="col-lg-4 col-md-6 mb-3">
                    <div class="card stats-card h-100">
                        <div class="card-header <?php echo $module['semestre'] == 'S1' ? 'bg-primary' : 'bg-success'; ?> text-white">
                            <h6 class="mb-0"><?php echo htmlspecialchars($module['code']); ?> - <?php echo htmlspecialchars($module['semestre']); ?></h6>
                        </div>
                        <div class="card-body">
                            <h6 class="card-title"><?php echo htmlspecialchars($module['nom']); ?></h6>
                            
                            <?php
                            $total_seances = $module['total_seances'] ?: 0;
                            $absences = $module['absences'] ?: 0;
                            $presences = $module['presences'] ?: 0;
                            $taux_presence = $total_seances > 0 ? round(($presences / $total_seances) * 100, 1) : 0;
                            
                            $badge_class = 'bg-success';
                            if ($taux_presence < 75) {
                                $badge_class = 'bg-danger';
                            } elseif ($taux_presence < 85) {
                                $badge_class = 'bg-warning text-dark';
                            }
                            ?>
                            
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="fw-bold text-primary"><?php echo $total_seances; ?></div>
                                    <small class="text-muted">Séances</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-success"><?php echo $presences; ?></div>
                                    <small class="text-muted">Présences</small>
                                </div>
                                <div class="col-4">
                                    <div class="fw-bold text-danger"><?php echo $absences; ?></div>
                                    <small class="text-muted">Absences</small>
                                </div>
                            </div>
                            
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar <?php echo str_replace('bg-', 'bg-', $badge_class); ?>" 
                                     style="width: <?php echo $taux_presence; ?>%"></div>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="badge <?php echo $badge_class; ?>">
                                    <?php echo $taux_presence; ?>% présence
                                </span>
                                <a href="etudiant/bilan_absences.php?module_id=<?php echo $module['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-eye me-1"></i>Détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12">
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i>
                    Aucun module n'est associé à votre compte. Veuillez contacter l'administration.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Prochaines séances -->
    <?php if (count($prochaines_seances) > 0): ?>
    <div class="row mb-4">
        <div class="col-12">
            <h3><i class="fas fa-calendar-alt me-2 text-primary"></i>Prochaines séances</h3>
            <div class="card">
                <div class="card-body">
                    <?php foreach ($prochaines_seances as $seance): ?>
                        <div class="d-flex align-items-center justify-content-between border-bottom py-2">
                            <div>
                                <h6 class="mb-1"><?php echo htmlspecialchars($seance['module_code'] . ' - ' . $seance['module_nom']); ?></h6>
                                <small class="text-muted">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($seance['responsable_prenom'] . ' ' . $seance['responsable_nom']); ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <div class="fw-bold"><?php echo date('d/m/Y', strtotime($seance['date_seance'])); ?></div>
                                <small class="text-muted"><?php echo substr($seance['heure_debut'], 0, 5) . ' - ' . substr($seance['heure_fin'], 0, 5); ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal Scanner QR -->
<div class="modal fade modal-qr" id="qrScannerModal" tabindex="-1" aria-labelledby="qrScannerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="qrScannerModalLabel">
                    <i class="fas fa-qrcode me-2"></i>Scanner QR Code
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="qr-reader" style="display: none;"></div>
                <div id="qr-instructions">
                    <i class="fas fa-camera fa-3x text-primary mb-3"></i>
                    <h5>Prêt à scanner</h5>
                    <p class="text-muted">Cliquez sur "Démarrer la caméra" pour commencer à scanner le QR code</p>
                    <button type="button" class="btn btn-primary" onclick="startScanner()">
                        <i class="fas fa-play me-2"></i>Démarrer la caméra
                    </button>
                </div>
                <div id="qr-result" style="display: none;">
                    <i class="fas fa-check-circle fa-3x text-success mb-3"></i>
                    <h5>QR Code détecté!</h5>
                    <p id="qr-result-text"></p>
                    <button type="button" class="btn btn-success" onclick="processQRCode()">
                        <i class="fas fa-check me-2"></i>Marquer la présence
                    </button>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <button type="button" class="btn btn-danger" onclick="stopScanner()" id="stopScannerBtn" style="display: none;">
                    <i class="fas fa-stop me-2"></i>Arrêter
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
<script>
let html5QrCode;
let detectedQRCode = '';

function openQRScanner() {
    const modal = new bootstrap.Modal(document.getElementById('qrScannerModal'));
    modal.show();
}

function startScanner() {
    const qrReader = document.getElementById('qr-reader');
    const instructions = document.getElementById('qr-instructions');
    const stopBtn = document.getElementById('stopScannerBtn');
    
    qrReader.style.display = 'block';
    instructions.style.display = 'none';
    stopBtn.style.display = 'inline-block';
    
    html5QrCode = new Html5Qrcode("qr-reader");
    
    const config = {
        fps: 10,
        qrbox: { width: 250, height: 250 },
        aspectRatio: 1.0
    };
    
    html5QrCode.start(
        { facingMode: "environment" },
        config,
        (decodedText, decodedResult) => {
            detectedQRCode = decodedText;
            stopScanner();
            showQRResult(decodedText);
        },
        (errorMessage) => {
            // Ignorer les erreurs de scan (normal quand aucun QR n'est détecté)
        }
    ).catch(err => {
        console.error('Erreur lors du démarrage du scanner:', err);
        alert('Erreur lors de l\'accès à la caméra. Vérifiez les permissions.');
        resetScanner();
    });
}

function stopScanner() {
    if (html5QrCode) {
        html5QrCode.stop().then(() => {
            html5QrCode.clear();
            resetScanner();
        }).catch(err => {
            console.error('Erreur lors de l\'arrêt du scanner:', err);
            resetScanner();
        });
    }
}

function resetScanner() {
    const qrReader = document.getElementById('qr-reader');
    const instructions = document.getElementById('qr-instructions');
    const result = document.getElementById('qr-result');
    const stopBtn = document.getElementById('stopScannerBtn');
    
    qrReader.style.display = 'none';
    instructions.style.display = 'block';
    result.style.display = 'none';
    stopBtn.style.display = 'none';
}

function showQRResult(qrText) {
    const instructions = document.getElementById('qr-instructions');
    const result = document.getElementById('qr-result');
    const resultText = document.getElementById('qr-result-text');
    
    instructions.style.display = 'none';
    result.style.display = 'block';
    resultText.textContent = 'Token détecté: ' + qrText.substring(0, 20) + '...';
}

function processQRCode() {
    if (detectedQRCode) {
        // Rediriger vers scanner_presence.php avec le token
        window.location.href = 'scanner_presence.php?token=' + encodeURIComponent(detectedQRCode);
    }
}

// Nettoyer quand la modal se ferme
document.getElementById('qrScannerModal').addEventListener('hidden.bs.modal', function () {
    stopScanner();
    detectedQRCode = '';
});
</script>

<?php include 'includes/footer.php'; ?>