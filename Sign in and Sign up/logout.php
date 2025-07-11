<?php
session_start();
session_unset();
session_destroy();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Cerrando sesión...</title>
</head>
<body>
  <script>
    // Redirige con parámetro logout=1
    window.location.href = "forms.html?logout=1";
  </script>
</body>
</html>
