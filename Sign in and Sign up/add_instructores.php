<?php
session_start();

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
$instructor_editar = null;
$buscar = isset($_GET['buscar']) ? trim($_GET['buscar']) : '';

// Procesar acciones (eliminar)
if (isset($_GET['accion']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    if ($_GET['accion'] === 'eliminar') {
        try {
            $stmt = $pdo->prepare("DELETE FROM Instructores WHERE Id = ?");
            $stmt->execute([$id]);
            $mensaje = "Instructor eliminado exitosamente.";
            $tipo_mensaje = "success";
        } catch (PDOException $e) {
            $mensaje = "Error al eliminar el instructor: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
        // Redireccionar para limpiar la URL
        header("Location: instructores.php");
        exit;
    }
    
    if ($_GET['accion'] === 'editar') {
        try {
            $stmt = $pdo->prepare("SELECT * FROM Instructores WHERE Id = ?");
            $stmt->execute([$id]);
            $instructor_editar = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($instructor_editar) {
                $editando = true;
            }
        } catch (PDOException $e) {
            $mensaje = "Error al cargar el instructor: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Procesar formulario cuando se envía
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre_instructor = trim($_POST['nombre_instructor'] ?? '');
    $area_instructor = trim($_POST['area_instructor'] ?? '');
    $tipo_actividad = trim($_POST['tipo_actividad'] ?? '');
    $id_instructor = isset($_POST['id_instructor']) ? (int)$_POST['id_instructor'] : null;
    
    // Validaciones
    if (empty($nombre_instructor)) {
        $mensaje = "El nombre del instructor es obligatorio.";
        $tipo_mensaje = "error";
    } elseif (empty($area_instructor)) {
        $mensaje = "El área del instructor es obligatoria.";
        $tipo_mensaje = "error";
    } elseif (empty($tipo_actividad)) {
        $mensaje = "El tipo de actividad es obligatorio.";
        $tipo_mensaje = "error";
    } else {
        try {
            if ($id_instructor) {
                // Actualizar instructor existente
                $stmt = $pdo->prepare("UPDATE Instructores SET nombre_instructor = ?, Area_Instructor = ?, Tipo_Actividad = ? WHERE Id = ?");
                $stmt->execute([$nombre_instructor, $area_instructor, $tipo_actividad, $id_instructor]);
                $mensaje = "Instructor actualizado exitosamente.";
            } else {
                // Insertar nuevo instructor
                $stmt = $pdo->prepare("INSERT INTO Instructores (nombre_instructor, Area_Instructor, Tipo_Actividad) VALUES (?, ?, ?)");
                $stmt->execute([$nombre_instructor, $area_instructor, $tipo_actividad]);
                $mensaje = "Instructor agregado exitosamente.";
            }
            
            $tipo_mensaje = "success";
            // Limpiar formulario después del éxito
            $_POST = array();
            $editando = false;
            $instructor_editar = null;
            
        } catch (PDOException $e) {
            $mensaje = "Error al procesar el instructor: " . $e->getMessage();
            $tipo_mensaje = "error";
        }
    }
}

// Obtener lista de instructores para mostrar con búsqueda
try {
    if (!empty($buscar)) {
        $stmt = $pdo->prepare("SELECT * FROM Instructores WHERE nombre_instructor LIKE ? OR Area_Instructor LIKE ? OR Tipo_Actividad LIKE ? ORDER BY Id DESC");
        $buscar_param = "%$buscar%";
        $stmt->execute([$buscar_param, $buscar_param, $buscar_param]);
    } else {
        $stmt = $pdo->query("SELECT * FROM Instructores ORDER BY Id DESC");
    }
    $instructores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $instructores = [];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Instructores - Admin</title>
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

        .form-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
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

        .submit-btn {
            background: linear-gradient(45deg, var(--success-color), #1e90ff);
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
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
            margin-left: 10px;
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

        .table-section {
            background: #fff;
            border-radius: 15px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .search-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            gap: 20px;
        }

        .search-box {
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
        }

        .search-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .search-btn:hover {
            background: var(--secondary-color);
        }

        .clear-search {
            background: var(--secondary-color);
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .clear-search:hover {
            background: var(--primary-color);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }

        th {
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 0.95rem;
        }

        tbody tr {
            transition: background-color 0.3s ease;
        }

        tbody tr:hover {
            background-color: #f8f9fa;
        }

        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        tbody tr:nth-child(even):hover {
            background-color: #f0f0f0;
        }

        .btn-edit, .btn-delete {
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 0.8rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-right: 5px;
            margin-bottom: 2px;
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

        .no-data {
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

        .stats-container {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            flex: 1;
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

            .search-container {
                flex-direction: column;
                align-items: stretch;
            }

            .table-container {
                font-size: 0.85rem;
            }

            th, td {
                padding: 10px 8px;
            }

            .stats-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>👨‍🏫 Gestión de Instructores</h1>
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

        <!-- Formulario para Agregar/Editar Instructor -->
        <div class="form-section">
            <?php if ($editando): ?>
                <div class="editing-notice">
                    ✏️ Editando Instructor: <strong><?php echo htmlspecialchars($instructor_editar['nombre_instructor']); ?></strong>
                </div>
            <?php endif; ?>

            <h2 class="section-title">
                <?php if ($editando): ?>
                    ✏️ Editar Instructor
                <?php else: ?>
                    ➕ Agregar Nuevo Instructor
                <?php endif; ?>
            </h2>
            
            <form method="POST" action="">
                <?php if ($editando): ?>
                    <input type="hidden" name="id_instructor" value="<?php echo $instructor_editar['Id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="nombre_instructor">Nombre del Instructor *</label>
                        <input type="text" id="nombre_instructor" name="nombre_instructor" 
                               value="<?php echo htmlspecialchars($editando ? $instructor_editar['nombre_instructor'] : ($_POST['nombre_instructor'] ?? '')); ?>" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="area_instructor">Área del Instructor *</label>
                        <select id="area_instructor" name="area_instructor" required>
                            <option value="">Seleccionar área...</option>
                            <option value="Cardio" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Cardio') || ($_POST['area_instructor'] ?? '') == 'Cardio' ? 'selected' : ''; ?>>Cardio</option>
                            <option value="Pesas" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Pesas') || ($_POST['area_instructor'] ?? '') == 'Pesas' ? 'selected' : ''; ?>>Pesas</option>
                            <option value="Funcional" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Funcional') || ($_POST['area_instructor'] ?? '') == 'Funcional' ? 'selected' : ''; ?>>Funcional</option>
                            <option value="Yoga" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Yoga') || ($_POST['area_instructor'] ?? '') == 'Yoga' ? 'selected' : ''; ?>>Yoga</option>
                            <option value="Pilates" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Pilates') || ($_POST['area_instructor'] ?? '') == 'Pilates' ? 'selected' : ''; ?>>Pilates</option>
                            <option value="Crossfit" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Crossfit') || ($_POST['area_instructor'] ?? '') == 'Crossfit' ? 'selected' : ''; ?>>Crossfit</option>
                            <option value="Natación" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Natación') || ($_POST['area_instructor'] ?? '') == 'Natación' ? 'selected' : ''; ?>>Natación</option>
                            <option value="Zumba" <?php echo ($editando && $instructor_editar['Area_Instructor'] == 'Zumba') || ($_POST['area_instructor'] ?? '') == 'Zumba' ? 'selected' : ''; ?>>Zumba</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="tipo_actividad">Tipo de Actividad *</label>
                        <select id="tipo_actividad" name="tipo_actividad" required>
                            <option value="">Seleccionar tipo...</option>
                            <option value="Grupal" <?php echo ($editando && $instructor_editar['Tipo_Actividad'] == 'Grupal') || ($_POST['tipo_actividad'] ?? '') == 'Grupal' ? 'selected' : ''; ?>>Grupal</option>
                            <option value="Individual" <?php echo ($editando && $instructor_editar['Tipo_Actividad'] == 'Individual') || ($_POST['tipo_actividad'] ?? '') == 'Individual' ? 'selected' : ''; ?>>Individual</option>
                            <option value="Mixta" <?php echo ($editando && $instructor_editar['Tipo_Actividad'] == 'Mixta') || ($_POST['tipo_actividad'] ?? '') == 'Mixta' ? 'selected' : ''; ?>>Mixta</option>
                        </select>
                    </div>
                </div>

                <div>
                    <button type="submit" class="submit-btn <?php echo $editando ? 'update' : ''; ?>">
                        <?php if ($editando): ?>
                            ✏️ Actualizar Instructor
                        <?php else: ?>
                            💾 Agregar Instructor
                        <?php endif; ?>
                    </button>

                    <?php if ($editando): ?>
                        <a href="instructores.php" class="cancel-btn">❌ Cancelar Edición</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Tabla de Instructores -->
        <div class="table-section">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-number"><?php echo count($instructores); ?></div>
                    <div class="stat-label">Instructores Registrados</div>
                </div>
                <?php
                $areas = array_count_values(array_column($instructores, 'Area_Instructor'));
                $area_principal = !empty($areas) ? array_keys($areas, max($areas))[0] : 'N/A';
                ?>
                <div class="stat-card">
                    <div class="stat-number"><?php echo count(array_unique(array_column($instructores, 'Area_Instructor'))); ?></div>
                    <div class="stat-label">Áreas Diferentes</div>
                </div>
            </div>

            <div class="search-container">
                <h2 class="section-title">📋 Lista de Instructores</h2>
                
                <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
                    <div class="search-box">
                        <input type="text" name="buscar" placeholder="Buscar por nombre, área o tipo de actividad..." 
                               value="<?php echo htmlspecialchars($buscar); ?>">
                    </div>
                    <button type="submit" class="search-btn">🔍 Buscar</button>
                    <?php if (!empty($buscar)): ?>
                        <a href="instructores.php" class="clear-search">❌ Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (!empty($buscar)): ?>
                <div style="margin-bottom: 15px; color: var(--secondary-color);">
                    Mostrando resultados para: "<strong><?php echo htmlspecialchars($buscar); ?></strong>" 
                    (<?php echo count($instructores); ?> instructor<?php echo count($instructores) != 1 ? 'es' : ''; ?> encontrado<?php echo count($instructores) != 1 ? 's' : ''; ?>)
                </div>
            <?php endif; ?>
            
            <div class="table-container">
                <?php if (empty($instructores)): ?>
                    <div class="no-data">
                        <?php if (!empty($buscar)): ?>
                            No se encontraron instructores que coincidan con la búsqueda.
                        <?php else: ?>
                            No hay instructores registrados aún.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre del Instructor</th>
                                <th>Área</th>
                                <th>Tipo de Actividad</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($instructores as $instructor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($instructor['Id']); ?></td>
                                    <td><?php echo htmlspecialchars($instructor['nombre_instructor']); ?></td>
                                    <td>
                                        <span style="background: #e3f2fd; color: #1976d2; padding: 4px 8px; border-radius: 12px; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($instructor['Area_Instructor']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span style="background: #e8f5e8; color: #2e7d32; padding: 4px 8px; border-radius: 12px; font-size: 0.85rem;">
                                            <?php echo htmlspecialchars($instructor['Tipo_Actividad']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button onclick="editarInstructor(<?php echo $instructor['Id']; ?>)" 
                                                class="btn-edit">✏️ Editar</button>
                                        <button onclick="eliminarInstructor('<?php echo htmlspecialchars($instructor['nombre_instructor']); ?>', <?php echo $instructor['Id']; ?>)" 
                                                class="btn-delete">🗑️ Eliminar</button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

        // Función para editar instructor inline
        function editarInstructor(id) {
            // Buscar la fila del instructor
            const filas = document.querySelectorAll('tbody tr');
            let filaInstructor;
            let instructor;
            
            filas.forEach(fila => {
                if (fila.cells[0].textContent == id) {
                    filaInstructor = fila;
                    instructor = {
                        id: fila.cells[0].textContent,
                        nombre: fila.cells[1].textContent,
                        area: fila.cells[2].querySelector('span').textContent,
                        tipo: fila.cells[3].querySelector('span').textContent
                    };
                }
            });

            if (!filaInstructor) return;

            // Crear formulario inline
            filaInstructor.innerHTML = `
                <td>${instructor.id}</td>
                <td><input type="text" id="edit_nombre_${id}" value="${instructor.nombre}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"></td>
                <td>
                    <select id="edit_area_${id}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="Cardio" ${instructor.area === 'Cardio' ? 'selected' : ''}>Cardio</option>
                        <option value="Pesas" ${instructor.area === 'Pesas' ? 'selected' : ''}>Pesas</option>
                        <option value="Funcional" ${instructor.area === 'Funcional' ? 'selected' : ''}>Funcional</option>
                        <option value="Yoga" ${instructor.area === 'Yoga' ? 'selected' : ''}>Yoga</option>
                        <option value="Pilates" ${instructor.area === 'Pilates' ? 'selected' : ''}>Pilates</option>
                        <option value="Crossfit" ${instructor.area === 'Crossfit' ? 'selected' : ''}>Crossfit</option>
                        <option value="Natación" ${instructor.area === 'Natación' ? 'selected' : ''}>Natación</option>
                        <option value="Zumba" ${instructor.area === 'Zumba' ? 'selected' : ''}>Zumba</option>
                    </select>
                </td>
                <td>
                    <select id="edit_tipo_${id}" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        <option value="Grupal" ${instructor.tipo === 'Grupal' ? 'selected' : ''}>Grupal</option>
                        <option value="Individual" ${instructor.tipo === 'Individual' ? 'selected' : ''}>Individual</option>
                        <option value="Mixta" ${instructor.tipo === 'Mixta' ? 'selected' : ''}>Mixta</option>
                    </select>
                </td>
                <td>
                    <button onclick="guardarEdicion(${id})" class="btn-edit" style="margin-right: 5px;">💾 Guardar</button>
                    <button onclick="cancelarEdicion()" class="btn-delete">❌ Cancelar</button>
                </td>
            `;
        }

        // Función para guardar la edición
        function guardarEdicion(id) {
            const nombre = document.getElementById(`edit_nombre_${id}`).value.trim();
            const area = document.getElementById(`edit_area_${id}`).value;
            const tipo = document.getElementById(`edit_tipo_${id}`).value;

            if (!nombre) {
                alert('El nombre del instructor es obligatorio');
                return;
            }

            // Crear formulario temporal para enviar datos
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            form.innerHTML = `
                <input name="id_instructor" value="${id}">
                <input name="nombre_instructor" value="${nombre}">
                <input name="area_instructor" value="${area}">
                <input name="tipo_actividad" value="${tipo}">
            `;

            document.body.appendChild(form);
            form.submit();
        }

        // Función para cancelar edición
        function cancelarEdicion() {
            location.reload();
        }

        // Función para eliminar instructor
        function eliminarInstructor(nombre, id) {
            if (confirm(`¿Estás seguro de que deseas eliminar al instructor "${nombre}"?\n\nEsta acción no se puede deshacer.`)) {
                window.location.href = `instructores.php?accion=eliminar&id=${id}`;
            }
        }
    </script>
</body>
</html>