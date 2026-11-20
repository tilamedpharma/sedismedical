<?php
/*
 * SEDIS MEDICAL : envoi des formulaires du site (contact, partenaire, candidature)
 * Reçoit les données envoyées par index.html et les transmet par e-mail.
 * Répond 200 si l'e-mail est parti, une erreur sinon (le site affiche alors un message d'échec).
 */

// --- Réglages -------------------------------------------------------------
$DESTINATAIRE = 'contact@sedismedical.com';   // adresse qui reçoit les messages
$EXPEDITEUR   = 'contact@sedismedical.com';   // doit être une adresse du domaine hébergé chez Hostinger
// ---------------------------------------------------------------------------

header('Content-Type: text/plain; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Méthode non autorisée');
}

// Champ piège : rempli uniquement par les robots. On fait semblant d'accepter.
if (!empty($_POST['fax_hp'])) {
    http_response_code(200);
    exit('OK');
}

function champ($nom, $max = 300) {
    $v = isset($_POST[$nom]) ? trim((string)$_POST[$nom]) : '';
    $v = str_replace("\0", '', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}
function ligne($v) { // une seule ligne : empêche l'injection d'en-têtes
    return trim(preg_replace('/[\r\n\t]+/', ' ', $v));
}

$types = [
    'contact'     => 'Demande de contact',
    'partenaire'  => 'Demande de partenariat',
    'candidature' => 'Candidature',
];
$type = champ('formulaire', 30);
if (!isset($types[$type])) {
    http_response_code(400);
    exit('Formulaire inconnu');
}

$nom     = ligne(champ('nom', 150));
$email   = ligne(champ('email', 200));
$message = champ('message', 8000);

if ($nom === '' || $message === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(422);
    exit('Champs obligatoires manquants ou e-mail invalide');
}

$libelles = [
    'nom'          => 'Nom',
    'organisation' => 'Organisation',
    'email'        => 'E-mail',
    'telephone'    => 'Téléphone',
    'pays'         => 'Pays',
    'poste'        => 'Poste visé',
    'produits'     => 'Produits',
    'site'         => 'Site web',
];

$corps = $types[$type] . " reçue depuis le site sedismedical.com\n";
$corps .= str_repeat('-', 50) . "\n\n";
foreach ($libelles as $cle => $libelle) {
    $v = ligne(champ($cle));
    if ($v !== '') {
        $corps .= $libelle . ' : ' . $v . "\n";
    }
}
$corps .= "\nMessage :\n" . $message . "\n\n";
$corps .= str_repeat('-', 50) . "\n";
$corps .= 'Envoyé le ' . date('d/m/Y à H:i') . ' (IP ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ")\n";
$corps .= "Répondez directement à cet e-mail pour écrire à l'expéditeur.\n";

$sujet = '=?UTF-8?B?' . base64_encode('[Site SEDIS] ' . $types[$type] . ' : ' . $nom) . '?=';
$nomCode = '=?UTF-8?B?' . base64_encode('Site SEDIS MEDICAL') . '?=';

$entetes  = 'From: ' . $nomCode . ' <' . $EXPEDITEUR . ">\r\n";
$entetes .= 'Reply-To: ' . $email . "\r\n";
$entetes .= "MIME-Version: 1.0\r\n";
$entetes .= "Content-Type: text/plain; charset=UTF-8\r\n";
$entetes .= "Content-Transfer-Encoding: 8bit\r\n";

$ok = mail($DESTINATAIRE, $sujet, $corps, $entetes, '-f' . $EXPEDITEUR);

if ($ok) {
    http_response_code(200);
    echo 'OK';
} else {
    http_response_code(500);
    echo "Échec de l'envoi";
}
