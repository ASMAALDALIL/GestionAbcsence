<?php
session_start();
require_once 'config/db.php';
require_once 'includes/auth.php';

// Récupérer les filières
try {
    $stmt = $pdo->query("SELECT id, nom FROM filieres");
    $filieres = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Erreur lors de la récupération des filières : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Choisir une filière</title>
</head>
<body>
    <h2>Choisir une filière à générer</h2>
    <form action="generer_liste_pdf.php" method="get">
    <label for="filiere">Filière :</label>
    <select name="filiere_id" id="filiere" required>
        <option value="">-- Sélectionner --</option>
        <?php foreach ($filieres as $filiere): ?>
            <option value="<?= htmlspecialchars($filiere['id']) ?>">
                <?= htmlspecialchars($filiere['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>
    <br><br>
    <button type="submit">Générer PDF</button>
</form>

</body>
</html>
