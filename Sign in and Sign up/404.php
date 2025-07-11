<?php
http_response_code(404); // Establece el código de error 404
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="refresh" content="5;url=index.html">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Error 404 - Página no encontrada</title>
  <style>
    body {
      background-color: #f2f2f2;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      text-align: center;
      padding: 50px;
    }
    h1 {
      font-size: 48px;
      color: #e74c3c;
    }
    p {
      font-size: 20px;
      color: #555;
    }
    a {
      color: #3498db;
      text-decoration: none;
    }
  </style>
</head>
<body>
  <h1>Error 404</h1>
  <p>La página que estás buscando no existe.</p>
  <p>Serás redirigido al <a href="index.html">inicio</a> en 5 segundos...</p>
</body>
</html>
