<?php
// Ferramenta simples para gerar hash BCRYPT
header('Content-Type: text/plain; charset=utf-8');
$pwd = $_GET['p'] ?? 'password1';
echo password_hash($pwd, PASSWORD_BCRYPT);
