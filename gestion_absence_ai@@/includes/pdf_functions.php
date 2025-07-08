<?php
/**
 * Fichier de fonctions utilitaires pour la génération de PDF
 */

// Définir les constantes TCPDF si elles ne sont pas définies
if (!defined('PDF_PAGE_ORIENTATION')) {
    define('PDF_PAGE_ORIENTATION', 'P'); // Portrait
    define('PDF_UNIT', 'mm');
    define('PDF_PAGE_FORMAT', 'A4');
    define('PDF_FONT_NAME_MAIN', 'helvetica');
    define('PDF_FONT_SIZE_MAIN', 10);
    define('PDF_FONT_NAME_DATA', 'helvetica');
    define('PDF_FONT_SIZE_DATA', 8);
    define('PDF_MARGIN_LEFT', 15);
    define('PDF_MARGIN_TOP', 27);
    define('PDF_MARGIN_RIGHT', 15);
    define('PDF_MARGIN_HEADER', 5);
    define('PDF_MARGIN_FOOTER', 10);
    define('PDF_MARGIN_BOTTOM', 25);
}

/**
 * Vérifie et crée le dossier PDF s'il n'existe pas
 * @return bool True si le dossier existe ou a été créé
 */
function ensure_pdf_directory() {
    $pdf_dir = 'pdf/';
    if (!is_dir($pdf_dir)) {
        return mkdir($pdf_dir, 0755, true);
    }
    return true;
}

/**
 * Génère un nom de fichier unique pour le rapport PDF
 * @param array $filename_parts Parties qui composent le nom du fichier
 * @return string Le nom du fichier PDF
 */
function generate_pdf_filename($filename_parts) {
    // Nettoyer les parties du nom de fichier
    $clean_parts = array_map(function($part) {
        // Enlever les caractères spéciaux
        return preg_replace('/[^A-Za-z0-9]/', '', $part);
    }, $filename_parts);
    
    // Créer le nom de fichier avec la date
    return 'absences_' . implode('', $clean_parts) . '' . date('Y-m-d') . '.pdf';
}

/**
 * Configuration de base pour les documents PDF
 * @param TCPDF $pdf L'objet PDF à configurer
 * @param string $title Le titre du document
 */
function setup_pdf_document($pdf, $title) {
    // Informations du document
    $pdf->SetCreator('Système de Gestion des Absences');
    $pdf->SetAuthor('ENSA Marrakech');
    $pdf->SetTitle($title);
    
    // Paramètres de page
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
}

/**
 * Ajoute un graphique de pourcentage au PDF
 * @param TCPDF $pdf L'objet PDF
 * @param float $percentage Le pourcentage à représenter
 * @param string $label Le libellé du graphique
 */
function add_percentage_bar($pdf, $percentage, $label = "Taux:") {
    $pdf->Ln(5);
    $pdf->Cell(40, 8, $label, 0, 0);
    
    // Dessiner la barre de progression
    $bar_width = 100;
    $pdf->SetFillColor(200, 200, 200);
    $pdf->Cell($bar_width, 8, '', 1, 0, 'L', true);
    
    // Partie remplie de la barre
    $pdf->SetX($pdf->GetX() - $bar_width);
    $filled_width = $percentage * $bar_width / 100;
    $pdf->SetFillColor(46, 204, 113); // Vert
    $pdf->Cell($filled_width, 8, '', 1, 0, 'L', true);
    
    // Texte du pourcentage
    $pdf->SetX($pdf->GetX() - $filled_width + ($bar_width / 2) - 10);
    $pdf->SetTextColor(0);
    $pdf->Cell(20, 8, round($percentage, 1) . "%", 0, 1, 'C', false);
}