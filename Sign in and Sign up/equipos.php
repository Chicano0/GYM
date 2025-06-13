<?php
session_start();

// Validar sesión activa y usuario admin
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'soporteverifiacion@gmail.com') {
    session_unset();
    session_destroy();
    header("Location: forms.html");
    exit;
}

// Conexión a la base de datos
try {
    $pdo = new PDO("odbc:Driver={SQL Server};Server=26.71.132.202;Database=gym;", "sa", "Uriel2004.");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

$mensaje = "";
$tipo_mensaje = "";
$editando = false;
$equipo_editar = null;

// Procesar acciones (eliminar)
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    if ($_GET['accion'] === 'eliminar') {
        try {
            $stmt = $pdo->prepare("DELETE FROM equipos_mantenimiento WHERE id = ?");
            $stmt->execute([$id]);
            $mensaje = "Equipo eliminado exitosamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar el equipo: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
        // Redireccionar para limpiar la URL
        header("Location: equipos.php");
        exit;
    }
    
    if ($_GET['accion'] === 'editar') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM equipos_mantenimiento WHERE id = ?");
            $stmt->execute([$id]);
            $equipo_editar = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($equipo_editar) {
                $editando = true;
            }
        } catch (PDOException $e) {
            $mensaje = "Error al cargar el equipo: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_equipo = trim($_POST['nombre_equipo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $en_servicio = isset($_POST['en_servicio']) ? 1 : 0;
    $id_equipo = isset($_POST['id_equipo']) ? (int)$_POST['id_equipo'] : null;
    
    if (empty($nombre_equipo)) {
        $mensaje = "El nombre del equipo es obligatorio.";
        $tipo_mensaje = "error";
    } else {
        try {
            if ($id_equipo) {
                // Actualizar equipo existente
                $stmt = $pdo->prepare("UPDATE equipos_mantenimiento SET nombre_equipo = ?, descripcion = ?, en_servicio = ? WHERE id = ?");
                $stmt->execute([$nombre_equipo, $descripcion, $en_servicio, $id_equipo]);
                $mensaje = "Equipo actualizado exitosamente.";
            } else {
                // Insertar nuevo equipo
                $stmt = $pdo->prepare("INSERT INTO equipos_mantenimiento (nombre_equipo, descripcion, en_servicio) VALUES (?, ?, ?)");
                $stmt->execute([$nombre_equipo, $descripcion, $en_servicio]);
                $mensaje = "Equipo agregado exitosamente.";
            }
            
            $tipo_mensaje = "success";
            // Limpiar formulario después del éxito
            $_POST = array();
            $editando = false;
            $equipo_editar = null;
            
        } catch (PDOException $e) {
            $mensaje = "Error al procesar el equipo: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Obtener lista de equipos para mostrar
try {
    $stmt = $pdo->query("SELECT * FROM equipos_mantenimiento ORDER BY id DESC");
    $equipos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $equipos = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Equipos - Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #141E30;
            --secondary-color: #243B55;
            --accent-color: #ff4757;
            --success-color: #2ed573;
            --warning-color: #ffa502;
            --error-color: #ff4757;
            --bg-color: #f6f5f7;
            --card-shadow: 25px 30px 55px #5557;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: var(--primary-color);
            font-size: 2.2rem;
            font-weight: 700;
        }

        .admin-badge {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .back-btn {
            background: var(--secondary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-left: 15px;
        }

        .back-btn:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        .main-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .form-section, .list-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-color);
            font-weight: 500;
        }

        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group textarea:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            transform: scale(1.2);
        }

        .submit-btn {
            background: linear-gradient(45deg, var(--success-color), #1e90ff);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 213, 115, 0.3);
        }

        .submit-btn.update {
            background: linear-gradient(45deg, var(--warning-color), #ff6348);
        }

        .cancel-btn {
            background: var(--secondary-color);
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 10px;
            text-align: center;
        }

        .cancel-btn:hover {
            background: var(--primary-color);
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .equipment-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .equipment-item:hover {
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .equipment-info {
            flex: 1;
        }

        .equipment-info h4 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 5px;
        }

        .equipment-info p {
            color: var(--secondary-color);
            font-size: 0.9rem;
            margin-bottom: 3px;
        }

        .equipment-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 10px;
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-badge.active {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.maintenance {
            background: #fff3cd;
            color: #856404;
        }

        .action-buttons {
            display: flex;
            gap: 5px;
        }

        .btn-edit, .btn-delete {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-edit {
            background: #17a2b8;
            color: white;
        }

        .btn-edit:hover {
            background: #138496;
            transform: translateY(-1px);
        }

        .btn-delete {
            background: var(--error-color);
            color: white;
        }

        .btn-delete:hover {
            background: #e63946;
            transform: translateY(-1px);
        }

        .no-equipment {
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            padding: 40px 20px;
        }

        .editing-notice {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid #ffeaa7;
        }

        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }

            .equipment-item {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }

            .equipment-actions {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🔧 Gestión de Equipos</h1>
            <div>
                <span class="admin-badge">👑 ADMINISTRADOR</span>
                <a href="admin.php" class="back-btn">← Volver al Dashboard</a>
            </div>
        </div>

        <?php if ($mensaje): ?>
            <div class="message <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Contenido Principal -->
        <div class="main-content">
            <!-- Formulario para Agregar/Editar Equipo -->
            <div class="form-section">
                <?php if ($editando): ?>
                    <div class="editing-notice">
                        ✏️ Editando equipo: <strong><?php echo htmlspecialchars($equipo_editar['nombre_equipo']); ?></strong>
                    </div>
                <?php endif; ?>

                <h2 class="section-title">
                    <?php if ($editando): ?>
                        ✏️ Editar Equipo
                    <?php else: ?>
                        ➕ Agregar Nuevo Equipo
                    <?php endif; ?>
                </h2>
                
                <form method="POST" action="">
                    <?php if ($editando): ?>
                        <input type="hidden" name="id_equipo" value="<?php echo $equipo_editar['id']; ?>">
                    <?php endif; ?>

                    <div class="form-group">
                        <label for="nombre_equipo">Nombre del Equipo *</label>
                        <input type="text" id="nombre_equipo" name="nombre_equipo" 
                               value="<?php echo htmlspecialchars($editando ? $equipo_editar['nombre_equipo'] : ($_POST['nombre_equipo'] ?? '')); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" 
                                  placeholder="Describe las características del equipo..."><?php echo htmlspecialchars($editando ? $equipo_editar['descripcion'] : ($_POST['descripcion'] ?? '')); ?></textarea>
                    </div>

                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" id="en_servicio" name="en_servicio" 
                                   <?php echo ($editando ? ($equipo_editar['en_servicio'] ? 'checked' : '') : (isset($_POST['en_servicio']) ? 'checked' : '')); ?>>
                            <label for="en_servicio">Equipo en servicio (disponible para uso)</label>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn <?php echo $editando ? 'update' : ''; ?>">
                        <?php if ($editando): ?>
                            ✏️ Actualizar Equipo
                        <?php else: ?>
                            💾 Agregar Equipo
                        <?php endif; ?>
                    </button>

                    <?php if ($editando): ?>
                        <a href="equipos.php" class="cancel-btn">❌ Cancelar Edición</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Lista de Equipos -->
            <div class="list-section">
                <h2 class="section-title">
                    📋 Equipos Registrados
                </h2>
                
                <?php if (empty($equipos)): ?>
                    <div class="no-equipment">
                        No hay equipos registrados aún.
                    </div>
                <?php else: ?>
                    <?php foreach ($equipos as $equipo): ?>
                        <div class="equipment-item">
                            <div class="equipment-info">
                                <h4><?php echo htmlspecialchars($equipo['nombre_equipo'] ?? 'Sin nombre'); ?></h4>
                                <p><strong>Descripción:</strong> <?php echo htmlspecialchars($equipo['descripcion'] ?? 'Sin descripción'); ?></p>
                                <p><strong>ID:</strong> #<?php echo htmlspecialchars($equipo['id'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="equipment-actions">
                                <?php if (isset($equipo['en_servicio']) && $equipo['en_servicio'] == 1): ?>
                                    <span class="status-badge active">✅ En Servicio</span>
                                <?php else: ?>
                                    <span class="status-badge maintenance">🔧 Fuera de Servicio</span>
                                <?php endif; ?>
                                
                                <div class="action-buttons">
                                    <a href="equipos.php?accion=editar&id=<?php echo $equipo['id']; ?>" 
                                       class="btn-edit">✏️ Editar</a>
                                    <a href="equipos.php?accion=eliminar&id=<?php echo $equipo['id']; ?>" 
                                       class="btn-delete" 
                                       onclick="return confirm('¿Estás seguro de que deseas eliminar este equipo?')">🗑️ Eliminar</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide mensajes después de 5 segundos
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>