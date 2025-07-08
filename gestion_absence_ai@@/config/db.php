<?php
// Connexion à la base de données avec PDO
try {
    $pdo = new PDO('mysql:host=localhost;dbname=gestion_absences;charset=utf8', 'root', '');
    // Configurer PDO pour qu'il génère des exceptions en cas d'erreur SQL
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Configurer PDO pour qu'il récupère les résultats dans un tableau associatif par défaut
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    // Désactiver l'émulation des requêtes préparées pour plus de sécurité
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // En cas d'erreur, afficher un message et terminer le script
    die('Erreur de connexion à la base de données : ' . $e->getMessage());
}