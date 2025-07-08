<?php
// debug_responsable.php - Script de débogage temporaire
session_start();
require_once 'config/db.php';

echo "<h2>Debug Connexion Responsable</h2>";

// 1. Vérifier la connexion à la base de données
try {
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Connexion à la base de données : OK<br>";
} catch (Exception $e) {
    echo "❌ Erreur connexion DB : " . $e->getMessage() . "<br>";
    exit;
}

// 2. Vérifier les responsables en base
try {
    $stmt = $pdo->query("SELECT id, nom, prenom, email FROM responsables");
    $responsables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Responsables en base de données :</h3>";
    if (count($responsables) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Nom</th><th>Prénom</th><th>Email</th></tr>";
        foreach ($responsables as $resp) {
            echo "<tr>";
            echo "<td>" . $resp['id'] . "</td>";
            echo "<td>" . $resp['nom'] . "</td>";
            echo "<td>" . $resp['prenom'] . "</td>";
            echo "<td>" . $resp['email'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "❌ Aucun responsable trouvé en base<br>";
    }
} catch (Exception $e) {
    echo "❌ Erreur récupération responsables : " . $e->getMessage() . "<br>";
}

// 3. Test de connexion avec un email spécifique
$test_email = 'M.zrikem@example.com';
$test_password = 'Responsable123!';

echo "<h3>Test de connexion avec : $test_email</h3>";

try {
    $stmt = $pdo->prepare("SELECT id, email, password, nom, prenom FROM responsables WHERE email = :email");
    $stmt->execute(['email' => $test_email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "✅ Utilisateur trouvé<br>";
        echo "ID: " . $user['id'] . "<br>";
        echo "Nom: " . $user['nom'] . "<br>";
        echo "Prénom: " . $user['prenom'] . "<br>";
        echo "Email: " . $user['email'] . "<br>";
        echo "Hash en base: " . substr($user['password'], 0, 20) . "...<br>";
        
        // Test du mot de passe
        if (password_verify($test_password, $user['password'])) {
            echo "✅ Mot de passe correct<br>";
            
            // Simuler la création de session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_type'] = 'responsable';
            $_SESSION['username'] = $user['email'];
            $_SESSION['nom'] = $user['nom'];
            $_SESSION['prenom'] = $user['prenom'];
            
            echo "✅ Session créée<br>";
            echo "Session user_id: " . $_SESSION['user_id'] . "<br>";
            echo "Session user_type: " . $_SESSION['user_type'] . "<br>";
            
        } else {
            echo "❌ Mot de passe incorrect<br>";
            
            // Générer un nouveau hash pour comparaison
            $new_hash = password_hash($test_password, PASSWORD_DEFAULT);
            echo "Nouveau hash généré: " . substr($new_hash, 0, 20) . "...<br>";
            
            echo "<strong>Solution :</strong> Exécutez cette requête SQL :<br>";
            echo "<code>UPDATE responsables SET password = '$new_hash' WHERE email = '$test_email';</code><br>";
        }
    } else {
        echo "❌ Utilisateur non trouvé avec cet email<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Erreur test connexion : " . $e->getMessage() . "<br>";
}

// 4. Vérifier les modules assignés aux responsables
echo "<h3>Modules assignés aux responsables :</h3>";
try {
    $stmt = $pdo->query("
        SELECT r.nom, r.prenom, r.email, COUNT(m.id) as nb_modules
        FROM responsables r
        LEFT JOIN modules m ON r.id = m.responsable_id
        GROUP BY r.id
    ");
    $resp_modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'>";
    echo "<tr><th>Nom</th><th>Prénom</th><th>Email</th><th>Nb Modules</th></tr>";
    foreach ($resp_modules as $rm) {
        echo "<tr>";
        echo "<td>" . $rm['nom'] . "</td>";
        echo "<td>" . $rm['prenom'] . "</td>";
        echo "<td>" . $rm['email'] . "</td>";
        echo "<td>" . $rm['nb_modules'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Erreur modules : " . $e->getMessage() . "<br>";
}

// 5. Test des fonctions d'authentification
echo "<h3>Test des fonctions d'authentification :</h3>";
require_once 'includes/auth.php';

echo "isLoggedIn(): " . (isLoggedIn() ? 'true' : 'false') . "<br>";
echo "isresponsable(): " . (isresponsable() ? 'true' : 'false') . "<br>";

// Bouton pour nettoyer la session
if (isset($_GET['clear_session'])) {
    session_destroy();
    echo "<br>🔄 Session nettoyée. <a href='debug_responsable.php'>Actualiser</a>";
    exit;
}

echo "<br><br><a href='debug_responsable.php?clear_session=1'>Nettoyer la session</a>";
echo "<br><a href='index.php'>Retour à la connexion</a>";
?>