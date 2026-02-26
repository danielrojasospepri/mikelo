<?php
/**
 * Crear usuarios de prueba para cada rol
 * Ejecutar con: php crear_usuarios_prueba.php
 */

require 'api/comun.php';

echo "=== CREANDO USUARIOS DE PRUEBA ===\n\n";

$db = getDB();

// Password por defecto para todos: test123
$password = password_hash('test123', PASSWORD_DEFAULT);

// Obtener sucursales disponibles
$stmt = $db->query("SELECT id, nombre FROM ubicaciones WHERE id != 1 ORDER BY id");
$sucursales = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "Sucursales disponibles:\n";
foreach ($sucursales as $suc) {
    echo "  - ID {$suc['id']}: {$suc['nombre']}\n";
}
echo "\n";

// Definir usuarios a crear
$usuarios = [
    // ADMIN (rol 1, nivel 10) - ya existe 'admin'
    
    // PLANTA_JEFE (rol 2, nivel 8) - Jefe de planta, ve todo
    [
        'nombre' => 'Jefe',
        'apellido' => 'Planta',
        'us' => 'jefe_planta',
        'id_roles' => 2,
        'sucursales' => [] // Acceso a todas
    ],
    
    // PLANTA_OPERARIO (rol 3, nivel 5) - Operario de planta
    [
        'nombre' => 'Operario',
        'apellido' => 'Uno',
        'us' => 'operario1',
        'id_roles' => 3,
        'sucursales' => [] // Solo planta (depósito central)
    ],
    
    // FRANQUICIA_ADMIN (rol 4, nivel 6) - Admin de sucursal/franquicia
    [
        'nombre' => 'Admin',
        'apellido' => 'Elordi',
        'us' => 'admin_elordi',
        'id_roles' => 4,
        'sucursales' => [2] // Mikelo Elordi
    ],
    
    // FRANQUICIA_EMPLEADO (rol 5, nivel 3) - Empleado de sucursal
    [
        'nombre' => 'Empleado',
        'apellido' => 'Elordi',
        'us' => 'empleado_elordi',
        'id_roles' => 5,
        'sucursales' => [2] // Mikelo Elordi
    ],
];

// Si hay más sucursales, crear usuarios para ellas
if (count($sucursales) > 1) {
    $segundaSucursal = $sucursales[1];
    $usuarios[] = [
        'nombre' => 'Admin',
        'apellido' => $segundaSucursal['nombre'],
        'us' => 'admin_suc' . $segundaSucursal['id'],
        'id_roles' => 4,
        'sucursales' => [$segundaSucursal['id']]
    ];
    $usuarios[] = [
        'nombre' => 'Empleado',
        'apellido' => $segundaSucursal['nombre'],
        'us' => 'empleado_suc' . $segundaSucursal['id'],
        'id_roles' => 5,
        'sucursales' => [$segundaSucursal['id']]
    ];
}

// Crear usuarios
echo "Creando usuarios...\n\n";

foreach ($usuarios as $u) {
    // Verificar si ya existe
    $stmt = $db->prepare("SELECT id FROM usuarios WHERE us = ?");
    $stmt->execute([$u['us']]);
    
    if ($stmt->fetch()) {
        echo "  ⚠ Usuario '{$u['us']}' ya existe, saltando...\n";
        continue;
    }
    
    // Insertar usuario
    $stmt = $db->prepare("
        INSERT INTO usuarios (nombre, apellido, us, ps, activo, id_roles)
        VALUES (?, ?, ?, ?, 1, ?)
    ");
    $stmt->execute([
        $u['nombre'],
        $u['apellido'],
        $u['us'],
        $password,
        $u['id_roles']
    ]);
    $idUsuario = $db->lastInsertId();
    
    // Asignar sucursales
    if (!empty($u['sucursales'])) {
        foreach ($u['sucursales'] as $i => $idSucursal) {
            $stmt = $db->prepare("
                INSERT INTO usuario_sucursales (id_usuario, id_sucursal, es_sucursal_principal)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idUsuario, $idSucursal, $i === 0 ? 1 : 0]);
        }
    }
    
    echo "  ✓ Creado: {$u['us']} (Rol: {$u['id_roles']})\n";
}

// Mostrar resumen
echo "\n=== USUARIOS DE PRUEBA ===\n";
echo "Password para todos: test123\n\n";

$stmt = $db->query("
    SELECT u.us, u.nombre, u.apellido, r.nombre as rol, r.nivel,
           GROUP_CONCAT(ub.nombre SEPARATOR ', ') as sucursales
    FROM usuarios u
    JOIN roles r ON u.id_roles = r.id
    LEFT JOIN usuario_sucursales us ON u.id = us.id_usuario
    LEFT JOIN ubicaciones ub ON us.id_sucursal = ub.id
    WHERE u.activo = 1
    GROUP BY u.id
    ORDER BY r.nivel DESC, u.us
");

echo str_pad("USUARIO", 20) . str_pad("NOMBRE", 25) . str_pad("ROL", 20) . str_pad("NIVEL", 8) . "SUCURSALES\n";
echo str_repeat("-", 100) . "\n";

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo str_pad($row['us'], 20);
    echo str_pad($row['nombre'] . ' ' . $row['apellido'], 25);
    echo str_pad($row['rol'], 20);
    echo str_pad($row['nivel'], 8);
    echo ($row['sucursales'] ?: 'Todas (Planta)') . "\n";
}

echo "\n=== PERMISOS POR ROL ===\n";
echo "
┌─────────────────────┬─────────────────────────────────────────────────────────┐
│ ROL                 │ PERMISOS                                                │
├─────────────────────┼─────────────────────────────────────────────────────────┤
│ ADMIN (nivel 10)    │ Todo el sistema, gestión de usuarios                    │
├─────────────────────┼─────────────────────────────────────────────────────────┤
│ PLANTA_JEFE (8)     │ Ver todas las sucursales, gestionar envíos, ver pedidos │
├─────────────────────┼─────────────────────────────────────────────────────────┤
│ FRANQUICIA_ADMIN (6)│ Su sucursal: pedidos, recepciones, stock, empleados     │
├─────────────────────┼─────────────────────────────────────────────────────────┤
│ PLANTA_OPERARIO (5) │ Alta depósito, preparar envíos                          │
├─────────────────────┼─────────────────────────────────────────────────────────┤
│ FRANQUICIA_EMPL (3) │ Su sucursal: ver stock, crear pedidos, recepciones      │
└─────────────────────┴─────────────────────────────────────────────────────────┘
";

echo "\n=== PÁGINAS POR ROL ===\n";
echo "
• login.html           → Todos (sin auth)
• index.html           → ADMIN, PLANTA_JEFE, PLANTA_OPERARIO
• alta_deposito.html   → ADMIN, PLANTA_JEFE, PLANTA_OPERARIO  
• envios.html          → ADMIN, PLANTA_JEFE, PLANTA_OPERARIO
• pedidos_sucursal.html→ ADMIN, PLANTA_JEFE, FRANQUICIA_ADMIN, FRANQUICIA_EMPLEADO
• recepciones.html     → ADMIN, FRANQUICIA_ADMIN, FRANQUICIA_EMPLEADO
• stock_sucursal.html  → ADMIN, PLANTA_JEFE, FRANQUICIA_ADMIN, FRANQUICIA_EMPLEADO
";

echo "\n✓ Listo! Puedes probar con cualquier usuario usando password: test123\n";
