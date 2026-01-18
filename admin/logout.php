<?php
require_once '../config.php';

// Session beenden
session_destroy();

// Zurück zur Startseite
header('Location: ../index.php');
exit;
