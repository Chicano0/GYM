<?php
session_start();

// Validar que solo el admin tenga acceso
if (!isset($_SESSION['email']) || $_SESSION['email'] !== 'soporteverifiacion@gmail.com') {
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
$usuarios = [];
$instructores = [];
$sesiones_activas = [];
$modo_edicion = false;
$sesion_editando = null;

// Obtener usuarios de la tabla usuarios_gym
try {
    $stmt = $pdo->query("SELECT * FROM usuarios_gym ORDER BY nombre");
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar usuarios: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// Obtener instructores
try {
    $stmt = $pdo->query("SELECT * FROM Instructores ORDER BY nombre_instructor");
    $instructores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar instructores: " . $e->getMessage();
    $tipo_mensaje = "error";
}

// Manejar acciones (Agregar, Editar, Eliminar)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    if ($accion === 'agregar' || $accion === 'editar') {
        $usuario_email = trim($_POST['usuario_email'] ?? '');
        $usuario_nombre = trim($_POST['usuario_nombre'] ?? '');
        $instructor_id = $_POST['instructor_id'] ?? '';
        $fecha_sesion = trim($_POST['fecha_sesion'] ?? '');
        $hora_sesion = trim($_POST['hora_sesion'] ?? '');
        $minutos_asignados = (int)($_POST['minutos_asignados'] ?? 0);
        $fecha_vencimiento = trim($_POST['fecha_vencimiento'] ?? '');
        
        // Validaciones
        if (empty($usuario_email) || empty($instructor_id) || empty($fecha_sesion) || 
            empty($hora_sesion) || $minutos_asignados <= 0 || empty($fecha_vencimiento)) {
            $mensaje = "Todos los campos son obligatorios.";
            $tipo_mensaje = "error";
        } else {
            try {
                // Obtener información del instructor seleccionado
                $stmt = $pdo->prepare("SELECT * FROM Instructores WHERE Id = ?");
                $stmt->execute([$instructor_id]);
                $instructor_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($instructor_info) {
                    if ($accion === 'agregar') {
                        // Insertar nueva sesión
                        $stmt = $pdo->prepare("
                            INSERT INTO agendas_gym (
                                usuario_email, usuario_nombre, nombre_instructor, 
                                Area_Instructor, Tipo_Actividad, fecha_sesion, 
                                hora_sesion, minutos_asignados, fecha_vencimiento, 
                                fecha_creacion, estado, Minutos
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        
                        $stmt->execute([
                            $usuario_email,
                            $usuario_nombre,
                            $instructor_info['nombre_instructor'],
                            $instructor_info['Area_Instructor'],
                            $instructor_info['Tipo_Actividad'],
                            $fecha_sesion,
                            $hora_sesion,
                            $minutos_asignados,
                            $fecha_vencimiento,
                            date('Y-m-d H:i:s'),
                            'Activa',
                            $minutos_asignados
                        ]);
                        
                        $mensaje = "Sesión creada exitosamente.";
                        $tipo_mensaje = "success";
                    } else {
                        // Editar sesión existente
                        $sesion_id = $_POST['sesion_id'];
                        $stmt = $pdo->prepare("
                            UPDATE agendas_gym SET 
                                usuario_email = ?, usuario_nombre = ?, nombre_instructor = ?,
                                Area_Instructor = ?, Tipo_Actividad = ?, fecha_sesion = ?,
                                hora_sesion = ?, minutos_asignados = ?, fecha_vencimiento = ?,
                                Minutos = ?
                            WHERE id = ?
                        ");
                        
                        $stmt->execute([
                            $usuario_email,
                            $usuario_nombre,
                            $instructor_info['nombre_instructor'],
                            $instructor_info['Area_Instructor'],
                            $instructor_info['Tipo_Actividad'],
                            $fecha_sesion,
                            $hora_sesion,
                            $minutos_asignados,
                            $fecha_vencimiento,
                            $minutos_asignados,
                            $sesion_id
                        ]);
                        
                        $mensaje = "Sesión actualizada exitosamente.";
                        $tipo_mensaje = "success";
                        $modo_edicion = false;
                    }
                } else {
                    $mensaje = "Instructor no encontrado.";
                    $tipo_mensaje = "error";
                }
            } catch (PDOException $e) {
                $mensaje = "Error al procesar la sesión: " . $e->getMessage();
                $tipo_mensaje = "error";
            }
        }
    } elseif ($accion === 'eliminar') {
        $sesion_id = $_POST['sesion_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM agendas_gym WHERE id = ?");
            $stmt->execute([$sesion_id]);
            $mensaje = "Sesión eliminada exitosamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar la sesión: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    } elseif ($accion === 'editar_form') {
        $sesion_id = $_POST['sesion_id'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM agendas_gym WHERE id = ?");
            $stmt->execute([$sesion_id]);
            $sesion_editando = $stmt->fetch(PDO::FETCH_ASSOC);
            $modo_edicion = true;
        } catch (PDOException $e) {
            $mensaje = "Error al cargar la sesión: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Obtener sesiones activas con búsqueda
$busqueda = $_GET['buscar'] ?? '';
$where_clause = "";
$params = [];

if (!empty($busqueda)) {
    $where_clause = "WHERE usuario_email LIKE ? OR usuario_nombre LIKE ? OR nombre_instructor LIKE ?";
    $busqueda_param = "%$busqueda%";
    $params = [$busqueda_param, $busqueda_param, $busqueda_param];
}

try {
    $sql = "SELECT * FROM agendas_gym $where_clause ORDER BY fecha_sesion DESC, hora_sesion DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $sesiones_activas = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $mensaje = "Error al cargar sesiones: " . $e->getMessage();
    $tipo_mensaje = "error";
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Gestión de Sesiones</title>
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
            --card-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg-color);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
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

        .admin-info {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 0.9rem;
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

        .stats-grid {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            flex: 1;
            box-shadow: var(--card-shadow);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .form-card {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .form-title {
            color: var(--primary-color);
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
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

        .form-group input, .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(45deg, var(--success-color), #1e90ff);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(46, 213, 115, 0.3);
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: var(--primary-color);
            transform: translateY(-2px);
        }

        .btn-warning {
            background: var(--warning-color);
            color: white;
        }

        .btn-warning:hover {
            background: #ff6348;
            transform: translateY(-2px);
        }

        .btn-danger {
            background: var(--error-color);
            color: white;
        }

        .btn-danger:hover {
            background: #e63946;
            transform: translateY(-2px);
        }

        .search-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
        }

        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-input {
            flex: 1;
            min-width: 300px;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary-color);
        }

        .table-container {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .table-responsive {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        .table thead {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .table th, .table td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }

        .table th {
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .table td {
            font-size: 0.95rem;
        }

        .table tbody tr {
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        .table tbody tr:nth-child(even):hover {
            background-color: #f0f0f0;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .status-active {
            background: #d4edda;
            color: #155724;
        }

        .no-data {
            text-align: center;
            color: var(--secondary-color);
            font-style: italic;
            padding: 40px 20px;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .search-form {
                flex-direction: column;
                align-items: stretch;
            }

            .search-input {
                min-width: auto;
            }

            .table th, .table td {
                padding: 10px 8px;
                font-size: 0.85rem;
            }

            .stats-grid {
                flex-direction: column;
            }

            .btn-group {
                flex-direction: column;
            }
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

    </style>
</head>

<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🛠️ Gestión de Sesiones</h1>
             
             
             
                     
               <a href="admin.php" class="back-btn">← Volver al Dashboard</a>
        </div>
        

        <?php if ($mensaje): ?>
            <div class="message <?php echo $tipo_mensaje; ?>">
                <?php echo htmlspecialchars($mensaje); ?>
            </div>
        <?php endif; ?>

        <!-- Estadísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($usuarios); ?></div>
                <div class="stat-label">Usuarios Registrados</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($instructores); ?></div>
                <div class="stat-label">Instructores Disponibles</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($sesiones_activas); ?></div>
                <div class="stat-label">Sesiones Programadas</div>
            </div>
        </div>

        <!-- Formulario -->
        <div class="form-card">
            <h2 class="form-title">
                <?php if ($modo_edicion): ?>
                    ✏️ Editar Sesión
                <?php else: ?>
                    ➕ Crear Nueva Sesión
                <?php endif; ?>
            </h2>

            <form method="POST" action="">
                <input type="hidden" name="accion" value="<?php echo $modo_edicion ? 'editar' : 'agregar'; ?>">
                <?php if ($modo_edicion && $sesion_editando): ?>
                    <input type="hidden" name="sesion_id" value="<?php echo $sesion_editando['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="usuario_email">Usuario *</label>
                        <select name="usuario_email" id="usuario_email" required onchange="updateUserName()">
                            <option value="">Seleccionar usuario...</option>
                            <?php foreach ($usuarios as $usuario): ?>
                                <option value="<?php echo htmlspecialchars($usuario['email']); ?>" 
                                        data-nombre="<?php echo htmlspecialchars($usuario['nombre']); ?>"
                                        <?php echo ($modo_edicion && $sesion_editando && $sesion_editando['usuario_email'] === $usuario['email']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($usuario['nombre'] . ' (' . $usuario['email'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="usuario_nombre">Nombre del Usuario *</label>
                        <input type="text" name="usuario_nombre" id="usuario_nombre" readonly
                               value="<?php echo $modo_edicion && $sesion_editando ? htmlspecialchars($sesion_editando['usuario_nombre']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="instructor_id">Instructor *</label>
                        <select name="instructor_id" id="instructor_id" required>
                            <option value="">Seleccionar instructor...</option>
                            <?php foreach ($instructores as $instructor): ?>
                                <option value="<?php echo $instructor['Id']; ?>"
                                        <?php echo ($modo_edicion && $sesion_editando && $sesion_editando['nombre_instructor'] === $instructor['nombre_instructor']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($instructor['nombre_instructor'] . ' - ' . $instructor['Area_Instructor'] . ' (' . $instructor['Tipo_Actividad'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="fecha_sesion">Fecha de Sesión *</label>
                        <input type="date" name="fecha_sesion" id="fecha_sesion" required
                               value="<?php echo $modo_edicion && $sesion_editando ? htmlspecialchars($sesion_editando['fecha_sesion']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="hora_sesion">Hora de Sesión *</label>
                        <input type="time" name="hora_sesion" id="hora_sesion" required
                               value="<?php echo $modo_edicion && $sesion_editando ? htmlspecialchars($sesion_editando['hora_sesion']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="minutos_asignados">Minutos Asignados *</label>
                        <input type="number" name="minutos_asignados" id="minutos_asignados" min="15" max="300" step="15" required
                               value="<?php echo $modo_edicion && $sesion_editando ? htmlspecialchars($sesion_editando['minutos_asignados']) : ''; ?>">
                    </div>

                    <div class="form-group">
                        <label for="fecha_vencimiento">Fecha de Vencimiento *</label>
                        <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" required
                               value="<?php echo $modo_edicion && $sesion_editando ? htmlspecialchars($sesion_editando['fecha_vencimiento']) : ''; ?>">
                    </div>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <?php if ($modo_edicion): ?>
                            💾 Actualizar Sesión
                        <?php else: ?>
                            ➕ Crear Sesión
                        <?php endif; ?>
                    </button>
                    
                    <?php if ($modo_edicion): ?>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                            ❌ Cancelar Edición
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Búsqueda -->
        <div class="search-section">
            <form method="GET" action="" class="search-form">
                <input type="text" name="buscar" class="search-input" 
                       placeholder="Buscar por email, nombre de usuario o instructor..."
                       value="<?php echo htmlspecialchars($busqueda); ?>">
                <button type="submit" class="btn btn-primary">🔍 Buscar</button>
                <?php if (!empty($busqueda)): ?>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">🔄 Limpiar</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Tabla de Sesiones -->
        <div class="table-container">
            <h2 class="form-title">
                📋 Sesiones Programadas 
                <?php if (!empty($busqueda)): ?>
                    <small>(Buscando: "<?php echo htmlspecialchars($busqueda); ?>")</small>
                <?php endif; ?>
            </h2>

            <?php if (empty($sesiones_activas)): ?>
                <div class="no-data">
                    <?php if (!empty($busqueda)): ?>
                        No se encontraron sesiones que coincidan con la búsqueda.
                    <?php else: ?>
                        No hay sesiones programadas.
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Usuario</th>
                                <th>Email</th>
                                <th>Instructor</th>
                                <th>Área</th>
                                <th>Tipo Actividad</th>
                                <th>Fecha/Hora</th>
                                <th>Minutos</th>
                                <th>Vencimiento</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sesiones_activas as $sesion): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($sesion['id']); ?></td>
                                    <td><?php echo htmlspecialchars($sesion['usuario_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($sesion['usuario_email']); ?></td>
                                    <td><?php echo htmlspecialchars($sesion['nombre_instructor']); ?></td>
                                    <td><?php echo htmlspecialchars($sesion['Area_Instructor']); ?></td>
                                    <td><?php echo htmlspecialchars($sesion['Tipo_Actividad']); ?></td>
                                    <td>
                                        <?php echo date('d/m/Y', strtotime($sesion['fecha_sesion'])); ?><br>
                                        <small><?php echo date('H:i', strtotime($sesion['hora_sesion'])); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($sesion['minutos_asignados']); ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($sesion['fecha_vencimiento'])); ?></td>
                                    <td>
                                        <span class="status-badge status-active">
                                            <?php echo htmlspecialchars($sesion['estado']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="accion" value="editar_form">
                                                <input type="hidden" name="sesion_id" value="<?php echo $sesion['id']; ?>">
                                                <button type="submit" class="btn btn-warning" style="padding: 6px 12px; font-size: 12px;">
                                                    ✏️ Editar
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;" 
                                                  onsubmit="return confirm('¿Estás seguro de eliminar esta sesión?');">
                                                <input type="hidden" name="accion" value="eliminar">
                                                <input type="hidden" name="sesion_id" value="<?php echo $sesion['id']; ?>">
                                                <button type="submit" class="btn btn-danger" style="padding: 6px 12px; font-size: 12px;">
                                                    🗑️ Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

   <script>
        function updateUserName() {
            const selectElement = document.getElementById('usuario_email');
            const nombreInput = document.getElementById('usuario_nombre');
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            
            if (selectedOption.hasAttribute('data-nombre')) {
                nombreInput.value = selectedOption.getAttribute('data-nombre');
            } else {
                nombreInput.value = '';
            }
        }

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

        // Validación del formulario
        document.querySelector('form').addEventListener('submit', function(e) {
            const fechaSesion = document.getElementById('fecha_sesion').value;
            const fechaVencimiento = document.getElementById('fecha_vencimiento').value;
            
            if (fechaVencimiento < fechaSesion) {
                e.preventDefault();
                alert('La fecha de vencimiento no puede ser anterior a la fecha de la sesión.');
                return false;
            }
        });
    </script>
</body>
</html>