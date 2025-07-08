<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant que responsable
require_responsable();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $module_id = isset($input['module_id']) ? intval($input['module_id']) : 0;
    $date_seance = isset($input['date_seance']) ? $input['date_seance'] : '';
    $heure_debut = isset($input['heure_debut']) ? $input['heure_debut'] : '';
    $heure_fin = isset($input['heure_fin']) ? $input['heure_fin'] : '';
    $responsable_id = $_SESSION['user_id'];
    
    // Validation
    if ($module_id <= 0 || empty($date_seance) || empty($heure_debut) || empty($heure_fin)) {
        echo json_encode(['success' => false, 'message' => 'Tous les champs sont requis']);
        exit;
    }
    
    // Vérifier que le module appartient au responsable
    $stmt = $pdo->prepare("SELECT id FROM modules WHERE id = ? AND responsable_id = ?");
    $stmt->execute([$module_id, $responsable_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Module non autorisé']);
        exit;
    }
    
    try {
        // Créer ou récupérer la séance
        $stmt = $pdo->prepare("SELECT id FROM seances WHERE module_id = ? AND date_seance = ? AND heure_debut = ? AND heure_fin = ?");
        $stmt->execute([$module_id, $date_seance, $heure_debut, $heure_fin]);
        $seance = $stmt->fetch();
        
        if (!$seance) {
            // Créer la séance
            $stmt = $pdo->prepare("INSERT INTO seances (module_id, date_seance, heure_debut, heure_fin) VALUES (?, ?, ?, ?)");
            $stmt->execute([$module_id, $date_seance, $heure_debut, $heure_fin]);
            $seance_id = $pdo->lastInsertId();
        } else {
            $seance_id = $seance['id'];
        }
        
        // Générer un token unique
        $token = bin2hex(random_bytes(32));
        
        // Supprimer les anciens tokens pour cette séance
        $stmt = $pdo->prepare("DELETE FROM qr_tokens WHERE seance_id = ?");
        $stmt->execute([$seance_id]);
        
        // Insérer le nouveau token
        $stmt = $pdo->prepare("INSERT INTO qr_tokens (token, responsable_id, seance_id) VALUES (?, ?, ?)");
        $stmt->execute([$token, $responsable_id, $seance_id]);
        
        // Générer l'URL pour le QR Code
        $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
        $qr_url = $base_url . dirname($_SERVER['REQUEST_URI']) . "/scanner_presence.php?token=" . $token;
        
        // Utiliser l'API QR Server pour générer le QR Code
        $qr_api_url = "https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=" . urlencode($qr_url);
        
        echo json_encode([
            'success' => true, 
            'qr_code_url' => $qr_api_url,
            'token' => $token,
            'seance_id' => $seance_id
        ]);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>
