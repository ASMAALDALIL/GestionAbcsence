<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant que responsable
require_responsable();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $date_seance = isset($_POST['date_seance']) ? $_POST['date_seance'] : '';
    $heure_debut = isset($_POST['heure_debut']) ? $_POST['heure_debut'] : '';
    $heure_fin = isset($_POST['heure_fin']) ? $_POST['heure_fin'] : '';
    $responsable_id = $_SESSION['user_id'];
    
    // Validation
    if ($module_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Veuillez sélectionner un module']);
        exit;
    }
    
    if (empty($date_seance)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez spécifier une date']);
        exit;
    }
    
    if (empty($heure_debut) || empty($heure_fin)) {
        echo json_encode(['success' => false, 'message' => 'Veuillez spécifier les heures']);
        exit;
    }
    
    // Vérifier que l'heure de fin est après l'heure de début
    if ($heure_debut >= $heure_fin) {
        echo json_encode(['success' => false, 'message' => 'L\'heure de fin doit être après l\'heure de début']);
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
        // Vérifier s'il existe déjà une séance pour ce module à cette date et heure
        $stmt = $pdo->prepare("
            SELECT id FROM seances 
            WHERE module_id = ? AND date_seance = ? 
            AND ((heure_debut <= ? AND heure_fin > ?) OR (heure_debut < ? AND heure_fin >= ?))
        ");
        $stmt->execute([$module_id, $date_seance, $heure_debut, $heure_debut, $heure_fin, $heure_fin]);
        
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Une séance existe déjà pour ce créneau']);
            exit;
        }
        
        // Créer la séance
        $stmt = $pdo->prepare("INSERT INTO seances (module_id, date_seance, heure_debut, heure_fin) VALUES (?, ?, ?, ?)");
        $stmt->execute([$module_id, $date_seance, $heure_debut, $heure_fin]);
        
        echo json_encode(['success' => true, 'message' => 'Séance créée avec succès']);
        
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Erreur base de données: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
}
?>