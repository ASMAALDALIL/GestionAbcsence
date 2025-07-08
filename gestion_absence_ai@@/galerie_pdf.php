<?php
require 'fpdf/fpdf.php';

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 10, 'Liste des Étudiants avec Photos', 0, 1, 'C');
$pdf->Ln(10);

$fichier_txt = 'etudiants.txt';

$page_width = 210;
$image_width = 35;
$image_height = 35;
$space_x = 10;
$space_y = 20;

$x = 10;
$y = 30;
$col_count = 0;
$row_height = 45;

if (file_exists($fichier_txt)) {
    $lignes = file($fichier_txt, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $pdf->SetFont('Arial', '', 10);

    foreach ($lignes as $ligne) {
        // On suppose : nom:prenom:filiere:chemin_photo
        $infos = explode(':', $ligne);

        if (count($infos) === 4) {
            $nomPrenom = $infos[0] . ' ' . $infos[1];
            $cheminImage = $infos[3];

            if (file_exists($cheminImage)) {
                $pdf->Image($cheminImage, $x, $y, $image_width, $image_height);
                $pdf->SetXY($x, $y + $image_height + 2);
                $pdf->MultiCell($image_width, 5, $nomPrenom, 0, 'C');
            }
        }

        $col_count++;
        $x += $image_width + $space_x;

        if ($col_count >= 4) {
            $x = 10;
            $y += $row_height;
            $col_count = 0;

            if ($y + $row_height > 280) {
                $pdf->AddPage();
                $y = 30;
            }
        }
    }
} else {
    $pdf->SetFont('Arial', '', 12);
    $pdf->Cell(0, 10, 'Aucun étudiant trouvé.', 0, 1, 'C');
}

$pdf->Output('I', 'liste_etudiants.pdf');
