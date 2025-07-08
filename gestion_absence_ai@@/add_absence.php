<?php
session_start();
require_once '../config/db.php';
require_once '../includes/auth.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
require_admin();

// Vérifier si le formulaire a été soumis
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Récupérer les données du formulaire
    $filiere_id = isset($_POST['filiere_id']) ? intval($_POST['filiere_id']) : 0;
    $module_id = isset($_POST['module_id']) ? intval($_POST['module_id']) : 0;
    $etudiant_id = isset($_POST['etudiant_id']) ? intval($_POST['etudiant_id']) : 0;
    $date_absence = isset($_POST['date_absence']) ? $_POST['date_absence'] : '';

    // Validation des données
    $errors = [];
    if ($filiere_id <= 0) {
        $errors[] = "Veuillez sélectionner une filière.";
    }
    if ($module_id <= 0) {
        $errors[] = "Veuillez sélectionner un module.";
    }
    if ($etudiant_id <= 0) {
        $errors[] = "Veuillez sélectionner un étudiant.";
    }
    if (empty($date_absence)) {
        $errors[] = "Veuillez spécifier une date_seanced'absence.";
    }

    // Si pas d'erreurs, procéder à l'enregistrement
    if (empty($errors)) {
        try {
            // Vérifier si une séance existe déjà pour ce module à cette date
            $stmt = $pdo->prepare("SELECT id FROM seances WHERE module_id = ? AND date_seance = ?");
            $stmt->execute([$module_id, $date_absence]);
            $seance = $stmt->fetch();
            $seance_id = 0;
            
            // Si la séance n'existe pas, la créer
            if (!$seance) {
                $stmt = $pdo->prepare("INSERT INTO seances (module_id, date_seance, heure_debut, heure_fin) VALUES (?, ?, '08:00:00', '10:00:00')");
                $stmt->execute([$module_id, $date_absence]);
                $seance_id = $pdo->lastInsertId();
            } else {
                $seance_id = $seance['id'];
            }

            // Vérifier si l'absence existe déjà
            $stmt = $pdo->prepare("SELECT id FROM absences WHERE etudiant_id = ? AND seance_id = ?");
            $stmt->execute([$etudiant_id, $seance_id]);
            $absence_existante = $stmt->fetch();

            if (!$absence_existante) {
                // Ajouter l'absence
                $stmt = $pdo->prepare("INSERT INTO absences (etudiant_id, seance_id, date_creation) VALUES (?, ?, NOW())");
                $stmt->execute([$etudiant_id, $seance_id]);

                // Rediriger avec un message de succès
                $_SESSION['success_message'] = "L'absence a été enregistrée avec succès.";
            } else {
                // Rediriger avec un message d'information
                $_SESSION['info_message'] = "Cette absence a déjà été enregistrée.";
            }
        } catch (PDOException $e) {
            // Gérer l'erreur et rediriger avec un message d'erreur
            $_SESSION['error_message'] = "Erreur lors de l'enregistrement de l'absence: " . $e->getMessage();
        }
    } else {
        // Stockage des erreurs dans la session
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
    
    // Rediriger vers la page de gestion des absences avec les filtres actuels
    $redirect_url = "gestion_absences.php";
    if ($filiere_id > 0) {
        $redirect_url .= "?filiere_id=" . $filiere_id;
        if ($module_id > 0) {
            $redirect_url .= "&module_id=" . $module_id;
        }
    }
    
    header("Location: " . $redirect_url);
    exit;
} else {
    // Si accès direct sans soumission de formulaire, rediriger vers la page de gestion
    header("Location: gestion_absences.php");
    exit;
}