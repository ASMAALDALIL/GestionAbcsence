<?php
// Inclure la connexion à la base de données
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est autorisé
require_admin();

// Vérifier si le paramètre filiere_id est fourni
if (!isset($_GET['filiere_id']) || empty($_GET['filiere_id'])) {
    // Renvoyer un tableau vide si aucune filière n'est spécifiée
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$filiere_id = intval($_GET['filiere_id']);

try {
    // Récupérer les étudiants pour la filière spécifiée
    $stmt = $pdo->prepare("
        SELECT id, numero_apogee, nom, prenom 
        FROM etudiants 
        WHERE filiere_id = :filiere_id 
        ORDER BY nom, prenom
    ");
    $stmt->execute(['filiere_id' => $filiere_id]);
    $etudiants = $stmt->fetchAll();
    
    // Renvoyer les étudiants au format JSON
    header('Content-Type: application/json');
    echo json_encode($etudiants);
} catch (PDOException $e) {
    // En cas d'erreur, renvoyer un tableau vide avec un code d'erreur 500
    header('HTTP/1.1 500 Internal Server Error');
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}