<?php
// sicef-caderno-de-emendas/index.php
session_start();
if (isset($_SESSION['user'])) {
    header('Location: index.php');
} else {
    header('Location: /formulario_para_login.php');
}
exit;
?>