<?php
require_once __DIR__ . "/includes/auth.php";

if (usuarioLogueado()) {
    redirect(panelBaseUrl('dashboard.php'));
}

redirect(panelBaseUrl('login.php'));
