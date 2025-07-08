<?php 
require_once 'fpdf/fpdf.php';
require_once 'config/db.php'; // ajoute cette ligne si tu ne l'as pas déjà

$id_filiere = intval($_GET['filiere_id']);
$stmt = $pdo->prepare("SELECT nom FROM filieres WHERE id = ?");
$stmt->execute([$id_filiere]);
$row = $stmt->fetch();

if (!$row) {
    die("Filière invalide.");
}

$filiere_choisie = $row['nom'];
if (!isset($_GET['filiere_id'])) {
    die('Filière non spécifiée.');
}
$filiere_choisie = trim($_GET['filiere_id']);

$fichier_txt = 'etudiants.txt';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Liste des Etudiants - Filière : ' . $filiere_choisie, 0, 1, 'C');
$pdf->Ln(10);

$page_width = 210;
$page_height = 297;
$image_width = ($page_width - 10) / 5;
$image_height = ($page_height - 20) / 8;

$space_x = 10;
$space_y = 10;

$max_images_per_row = 5;
$max_images_per_column = 8;

$x_start = 10;
$y_start = 20;

$x = $x_start;
$y = $y_start;
$image_count = 0;

if (file_exists($fichier_txt)) {
    $lignes = file($fichier_txt, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $pdf->SetFont('Arial', '', 12);

    foreach ($lignes as $ligne) {
        $parts = explode(':', $ligne);
        if (count($parts) < 3) continue;

        $nomPrenom = trim($parts[0]);
        $filiere = trim($parts[1]);
        $cheminImage = trim($parts[2]);

        if (strcasecmp($filiere, $filiere_choisie) !== 0) continue;

        if (file_exists($cheminImage)) {
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->Rect($x, $y, $image_width, $image_height);
            $pdf->Image($cheminImage, $x, $y, $image_width, $image_height);

            $pdf->SetDrawColor(0, 0, 255);
            $pdf->Rect($x, $y + $image_height, $image_width, 10);
            $pdf->SetXY($x, $y + $image_height + 1);
            $pdf->Cell($image_width, 10, $nomPrenom, 0, 1, 'C');

            $image_count++;

            if ($image_count % $max_images_per_row == 0) {
                $x = $x_start;
                $y += $image_height + 10 + $space_y;
            } else {
                $x += $image_width + $space_x;
            }

            if ($image_count % ($max_images_per_row * $max_images_per_column) == 0) {
                $pdf->AddPage();
                $x = $x_start;
                $y = $y_start;
            }
        }
    }

    if ($image_count === 0) {
        $pdf->Cell(0, 10, 'Aucun étudiant trouvé pour cette filière.', 0, 1, 'C');
    }

    $pdf->Output('D', 'listeEtudiants_' . $filiere_choisie . '.pdf');
} else {
    echo "Fichier etudiants.txt introuvable.";
}
