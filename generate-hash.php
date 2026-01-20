php<?php
// Dein gewünschtes neues Passwort hier eintragen:
$neues_passwort = 'MeinSicheresPasswort2026!';

// Hash generieren:
$hash = password_hash($neues_passwort, PASSWORD_DEFAULT);

echo "Dein neues Passwort: " . $neues_passwort . "<br>";
echo "Der Hash (kopiere das): <br>";
echo "<strong>" . $hash . "</strong>";
?>