<?php
require_once "../config/database.php";
require_once "../config/auth.php";

requireAdmin();

require_once "../fpdf/fpdf.php";

$user_id = $_GET['user_id'];
$course_id = $_GET['course_id'];

$code = strtoupper(bin2hex(random_bytes(5)));

// save record
$stmt = $pdo->prepare("
INSERT INTO certificates
(user_id, course_id, certificate_code)
VALUES (?,?,?)
");

$stmt->execute([$user_id, $course_id, $code]);

$pdf = new FPDF();
$pdf->AddPage();

$pdf->SetFont("Arial","B",20);
$pdf->Cell(190,20,"CERTIFICATE OF COMPLETION",0,1,"C");

$pdf->SetFont("Arial","",14);
$pdf->Cell(190,10,"This certifies that",0,1,"C");

$pdf->SetFont("Arial","B",16);
$pdf->Cell(190,10,"STUDENT ID: $user_id",0,1,"C");

$pdf->Cell(190,10,"has completed the course",0,1,"C");

$pdf->Cell(190,10,"Certificate Code: $code",0,1,"C");

$file = "../uploads/certificates/$code.pdf";

$pdf->Output("F",$file);

echo "Certificate generated";
