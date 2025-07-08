<?php
session_start();
require_once 'config/db.php';

// Récupération sécurisée des données du formulaire
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$code = isset($_POST['code']) ? trim($_POST['code']) : '';
$valider = isset($_POST['envoyer']) ? $_POST['envoyer'] : null;

if ($valider) {
    // Préparation et exécution de la requête pour récupérer le code de vérification et email
    $sql = "SELECT code_activation, email FROM etudiants WHERE email = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$email]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        // Vérifier que le code saisi correspond au code en base
        if ($code === $result['code_activation']) {
            // Mise à jour du statut is_verified
            $sql1 = "UPDATE etudiants SET is_verified = 1 WHERE email = ?";
            $stmt1 = $pdo->prepare($sql1);
            $stmt1->execute([$email]);

            // Redirection vers la page de connexion ou d'accueil
            header('Location: index.php');
            exit();
        } else {
            $erreur = "Le code de vérification est incorrect.";
        }
    } else {
        $erreur = "Aucun compte trouvé pour cet email.";
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Activation du compte étudiant</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- Inclure une police d’icônes (par exemple Font Awesome) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }

        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .card {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0px 0px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
            overflow: hidden;
        }

        .card-header {
            background-color: #0d6efd;
            color: white;
            padding: 20px;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
        }

        .card-header i {
            margin-right: 8px;
        }

        .card-body {
            padding: 25px;
        }

        label {
            display: block;
            margin-top: 15px;
            font-weight: bold;
        }

        input[type="email"],
        input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-top: 5px;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 14px;
        }

        input[type="submit"] {
            width: 100%;
            margin-top: 25px;
            padding: 12px;
            background-color: #0d6efd;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }

        input[type="submit"]:hover {
            background-color: #0b5ed7;
        }

        .erreur {
            color: red;
            text-align: center;
            margin-top: 15px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="card-header">
            <i class="fas fa-user-check"></i> Activation de compte
        </div>
        <div class="card-body">
            <form action="" method="post">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" required value="<?= htmlspecialchars($email ?? '') ?>">

                <label for="code">Code de vérification</label>
                <input type="text" name="code" id="code" required value="<?= htmlspecialchars($code ?? '') ?>">

                <input type="submit" name="envoyer" value="Activer le compte">

                <?php if (isset($erreur)) : ?>
                    <div class="erreur"><?= htmlspecialchars($erreur) ?></div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>
</body>
</html>
