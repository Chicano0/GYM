<?php
$dsn = "odbc:Driver={SQL Server};Server=192.168.1.18;Database=universidad;";
$user = "sa";
$password = "Uriel2004.";

try {
    $pdo = new PDO($dsn, $user, $password);
    echo '<img src="https://cdn-icons-png.flaticon.com/512/190/190411.png" alt="Conexión exitosa">';
    echo '<div class="message success-text">✅ Conexión exitosa a la base de datos Universidad.</div>';
    echo '
    <script>
        window.addEventListener("load", function() {
            Swal.fire({
                icon: "success",
                title: "¡Conexión exitosa!",
                text: "La conexión a la base de datos Universidad se realizó correctamente.",
                confirmButtonText: "Aceptar"
            });
        });
    </script>';
} catch (PDOException $e) {
    $mensajeTecnico = $e->getMessage();
    $mensajeUsuario = "No se pudo establecer conexión con la base de datos. Verifica el servidor, usuario o red.";
    echo '<img src="https://cdn-icons-png.flaticon.com/512/463/463612.png" alt="Error de conexión">';
    echo '<div class="message error-text">❌ ' . $mensajeUsuario . '</div>';
    echo '<div class="error-detail">Detalle técnico: ' . htmlspecialchars($mensajeTecnico) . '</div>';
    echo '
    <script>
        window.addEventListener("load", function() {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                html: "<b>' . $mensajeUsuario . '</b><br><small>Detalle: ' . htmlspecialchars($mensajeTecnico) . '</small>",
                confirmButtonText: "Reintentar"
            });
        });
    </script>';
}
?>