<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// SUPPRIMEZ ou COMMENTEZ cette ligne qui bloque l'accès
// require_admin();

// Récupérer l'ID de la filière
$filiere_id = isset($_GET['filiere_id']) ? intval($_GET['filiere_id']) : 0;

if ($filiere_id > 0) {
    try {
        // Préparer et exécuter la requête
        $stmt = $pdo->prepare("
            SELECT id, code, nom, semestre
            FROM modules
            WHERE filiere_id = ?
            ORDER BY semestre, code
        ");
        $stmt->execute([$filiere_id]);
        $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Renvoyer les données en JSON
        header('Content-Type: application/json');
        echo json_encode($modules);
        exit;
    } catch (PDOException $e) {
        // En cas d'erreur, renvoyer un message d'erreur JSON
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['error' => 'Erreur de base de données: ' . $e->getMessage()]);
        exit;
    }
} else {
    // Si pas d'ID de filière valide, renvoyer un tableau vide
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}