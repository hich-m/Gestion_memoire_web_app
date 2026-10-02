<?php
require_once('libs/TCPDF/TCPDF-main/tcpdf.php');
require_once '../config/db_config.php';
date_default_timezone_set('Africa/Algiers');

// Nettoyage du buffer
if (ob_get_length()) {
    ob_end_clean();
}

// Vérifier la présence de l'identifiant de l'étudiant
if (!isset($_GET['id'])) {
    exit("ID étudiant manquant.");
}

$id_etudiant = $_GET['id'];

// Récupération des informations de l'étudiant
$stmt = $pdo->prepare("
    SELECT s.matricule, u.last_name, u.first_name, g.id AS groupe, s.specialite, s.date_de_naissance, s.lieu_de_naissance 
    FROM student s 
    JOIN user u ON u.id = s.user_id 
    JOIN groupe g ON g.id = s.id_groupe 
    WHERE s.id = ?
");
$stmt->execute([$id_etudiant]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    exit("Étudiant introuvable.");
}

$annee_actuelle = date('Y');
$annee_suivante = $annee_actuelle + 1;
$annee_scolaire = $annee_actuelle . '/' . $annee_suivante;


// Création du PDF
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Université UMBB de Boumerdes');
$pdf->SetTitle('Fiche Quitus');
$pdf->SetSubject('PDF Quitus Étudiant');

// En-tête désactivé par défaut
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Ajouter une page
$pdf->AddPage();

// Texte principal
$pdf->SetFont('dejavusans', '', 12);
$pdf->Cell(0, 10, 'REBUPLIQUE ALGERIENNE DEMOCRATIQUE ET POPULAIRE', 0, 1, 'C');
// Ligne en arabe
$pdf->setRTL(true);
$pdf->SetFont('dejavusans', '', 14);
$pdf->Cell(0, 10, 'الجمهورية الجزائرية الديمقراطية الشعبية', 0, 1, 'C');

$pdf->Cell(0, 10, "Ministère de l'Enseignement Supérieur et de la Recherche Scientifique", 0, 1, 'C');
$pdf->Cell(0, 10, 'وزارة التعليم العالي والبحث العلمي', 0, 1, 'C');

// Logo
$pdf->Image('../assets/img/logoUMBB.jpg', 120, 55, 40); // Centré en haut
$pdf->Ln(40); // Espace après le logo

$pdf->Cell(0, 10, "Université M'Hamed Bougara de Boumerdes", 0, 1, 'C');
$pdf->Cell(0, 10, 'جامعة أمحمد بوقرة بومرداس', 0, 1, 'C');

$pdf->Cell(0, 10, 'Faculté des Sciences', 0, 1, 'C');


$pdf->setRTL(false);

$pdf->Ln(10);

// Titre
$pdf->SetFont('dejavusans', 'B', 16);
$pdf->Cell(0, 10, "Quitus des Bibliothéque de l'UMBB", 0, 1, 'C');
$pdf->Ln(10);

// Informations étudiant
$pdf->SetFont('dejavusans', '', 12);

$pdf->Cell(40, 10, 'N° lecture :', 0, 1);

$pdf->Cell(40, 10, 'Matricule :', 0, 0);
$pdf->Cell(100, 10, $data['matricule'], 0, 1);

$pdf->Cell(40, 10, 'Nom :', 0, 0);
$pdf->Cell(100, 10, $data['last_name'], 0, 1);

$pdf->Cell(40, 10, 'Prénom :', 0, 0);
$pdf->Cell(100, 10, $data['first_name'], 0, 1);

$pdf->Cell(50, 10, 'Date de Naissance :', 0, 0);
$pdf->Cell(50, 10, $data['date_de_naissance'], 0, 0);
$pdf->Cell(10, 10, 'A :', 0, 0);
$pdf->Cell(0, 10, $data['lieu_de_naissance'], 0, 1);

$pdf->Cell(40, 10, 'Spécialité :', 0, 0);
$pdf->Cell(100, 10, $data['specialite'], 0, 1);

$pdf->Ln(10);

$pdf->SetFont('helvetica', '', 12);
$texte = "Nous soussigné(e)s, Responsable de la Bibliothèque Universitaire et le Responsable de la Bibliothèque de la Faculté des Sciences, attestons que l'étudiant(e) cité ci-dessus est en situation régulière vis-à-vis de la Bibliothèque pour l'année " . $annee_scolaire . ".";

$pdf->MultiCell(0, 10, ($texte), 0, 'L');


// Date d'édition
$pdf->Ln(10);
$pdf->Cell(40, 10, 'Boumerdes le :', 0, 0);
$pdf->Cell(100, 10, date('d/m/Y'), 0, 1);

// Signature fictive
$pdf->Ln(10);
$pdf->Cell(0, 10, 'Signature / Cachet', 0, 1, 'R');

// Génération du fichier PDF (affichage ou téléchargement)
$pdf->Output('quitus_etudiant.pdf', 'I'); // Pour le téléchargement automatique, remplacer 'I' par 'D'
exit;
