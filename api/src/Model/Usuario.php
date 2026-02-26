<?php
namespace App\Model;

/**
 * Modelo para gestión de Usuarios
 * Compatible con la estructura existente de la tabla usuarios
 */
class Usuario {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Autenticar usuario (login)
     * @param string $username - Campo 'us' en la BD
     * @param string $password - Password en texto plano
     * @return array|false - Datos del usuario o false si falla
     */
    public function autenticar($username, $password) {
        $stmt = $this->db->prepare("
            SELECT u.*, r.nombre as rol_nombre, r.nivel as rol_nivel
            FROM usuarios u
            LEFT JOIN roles r ON u.id_roles = r.id
            WHERE u.us = ? AND u.activo = 1
        ");
        $stmt->execute([$username]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            return false;
        }

        // Verificar password
        // La BD existente usa 'ps' para password
        // Verificamos si es hash o texto plano (para migración gradual)
        $passwordValido = false;

        if (password_verify($password, $usuario['ps'])) {
            // Password hasheado (nuevo formato)
            $passwordValido = true;
        } elseif ($usuario['ps'] === $password) {
            // Password en texto plano (formato legacy) - actualizar a hash
            $this->actualizarPassword($usuario['id'], $password);
            $passwordValido = true;
        }

        if (!$passwordValido) {
            return false;
        }

        // Actualizar último login
        $stmt = $this->db->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?");
        $stmt->execute([$usuario['id']]);

        // Obtener sucursales asignadas
        $usuario['sucursales'] = $this->obtenerSucursales($usuario['id']);

        // Obtener roles adicionales (si usa tabla N:N)
        $usuario['roles'] = $this->obtenerRoles($usuario['id']);

        // No devolver password
        unset($usuario['ps']);

        return $usuario;
    }

    /**
     * Actualizar password a formato hash
     */
    private function actualizarPassword($idUsuario, $password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuarios SET ps = ? WHERE id = ?");
        $stmt->execute([$hash, $idUsuario]);
    }

    /**
     * Obtener sucursales asignadas a un usuario
     */
    public function obtenerSucursales($idUsuario) {
        $stmt = $this->db->prepare("
            SELECT us.id_sucursal, u.nombre as sucursal, us.es_sucursal_principal,
                   u.tipo_ubicacion
            FROM usuario_sucursales us
            INNER JOIN ubicaciones u ON us.id_sucursal = u.id
            WHERE us.id_usuario = ?
            ORDER BY us.es_sucursal_principal DESC, u.nombre
        ");
        $stmt->execute([$idUsuario]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener roles de un usuario
     */
    public function obtenerRoles($idUsuario) {
        $stmt = $this->db->prepare("
            SELECT r.id, r.nombre, r.nivel
            FROM usuario_roles ur
            INNER JOIN roles r ON ur.id_rol = r.id
            WHERE ur.id_usuario = ?
            ORDER BY r.nivel
        ");
        $stmt->execute([$idUsuario]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Obtener usuario por ID
     */
    public function obtenerPorId($idUsuario) {
        $stmt = $this->db->prepare("
            SELECT u.id, u.nombre, u.apellido, u.email, u.us as usuario, 
                   u.activo, u.id_roles, u.ultimo_login, u.fecha_creacion,
                   r.nombre as rol_nombre, r.nivel as rol_nivel
            FROM usuarios u
            LEFT JOIN roles r ON u.id_roles = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$idUsuario]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($usuario) {
            $usuario['sucursales'] = $this->obtenerSucursales($idUsuario);
            $usuario['roles'] = $this->obtenerRoles($idUsuario);
        }

        return $usuario;
    }

    /**
     * Crear nuevo usuario
     */
    public function crear($datos) {
        try {
            $this->db->beginTransaction();

            // Validar que el username no exista
            $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE us = ?");
            $stmt->execute([$datos['usuario']]);
            if ($stmt->fetch()) {
                throw new \Exception("El nombre de usuario ya existe");
            }

            // Validar email si se proporciona
            if (!empty($datos['email'])) {
                $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$datos['email']]);
                if ($stmt->fetch()) {
                    throw new \Exception("El email ya está registrado");
                }
            }

            // Hashear password
            $passwordHash = password_hash($datos['password'], PASSWORD_DEFAULT);

            // Insertar usuario
            $stmt = $this->db->prepare("
                INSERT INTO usuarios (nombre, apellido, email, us, ps, activo, id_roles, creado_por)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $datos['nombre'],
                $datos['apellido'] ?? null,
                $datos['email'] ?? null,
                $datos['usuario'],
                $passwordHash,
                $datos['id_rol'],
                $datos['creado_por'] ?? null
            ]);
            $idUsuario = $this->db->lastInsertId();

            // Agregar a usuario_roles
            $stmt = $this->db->prepare("
                INSERT INTO usuario_roles (id_usuario, id_rol, asignado_por)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$idUsuario, $datos['id_rol'], $datos['creado_por'] ?? null]);

            // Asignar sucursales si se proporcionan
            if (!empty($datos['sucursales'])) {
                foreach ($datos['sucursales'] as $idSucursal) {
                    $this->asignarSucursal($idUsuario, $idSucursal, $datos['creado_por'] ?? null);
                }
            }

            $this->db->commit();
            return $idUsuario;

        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Asignar sucursal a usuario
     */
    public function asignarSucursal($idUsuario, $idSucursal, $asignadoPor = null, $esPrincipal = false) {
        $stmt = $this->db->prepare("
            INSERT INTO usuario_sucursales (id_usuario, id_sucursal, es_sucursal_principal, asignado_por)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE es_sucursal_principal = VALUES(es_sucursal_principal)
        ");
        $stmt->execute([$idUsuario, $idSucursal, $esPrincipal ? 1 : 0, $asignadoPor]);
    }

    /**
     * Quitar sucursal de usuario
     */
    public function quitarSucursal($idUsuario, $idSucursal) {
        $stmt = $this->db->prepare("
            DELETE FROM usuario_sucursales 
            WHERE id_usuario = ? AND id_sucursal = ?
        ");
        $stmt->execute([$idUsuario, $idSucursal]);
    }

    /**
     * Actualizar usuario
     */
    public function actualizar($idUsuario, $datos) {
        $campos = [];
        $valores = [];

        if (isset($datos['nombre'])) {
            $campos[] = "nombre = ?";
            $valores[] = $datos['nombre'];
        }
        if (isset($datos['apellido'])) {
            $campos[] = "apellido = ?";
            $valores[] = $datos['apellido'];
        }
        if (isset($datos['email'])) {
            $campos[] = "email = ?";
            $valores[] = $datos['email'];
        }
        if (isset($datos['activo'])) {
            $campos[] = "activo = ?";
            $valores[] = $datos['activo'] ? 1 : 0;
        }
        if (isset($datos['id_rol'])) {
            $campos[] = "id_roles = ?";
            $valores[] = $datos['id_rol'];
        }

        if (empty($campos)) {
            return false;
        }

        $valores[] = $idUsuario;
        $sql = "UPDATE usuarios SET " . implode(", ", $campos) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($valores);

        // Actualizar rol en tabla N:N si cambió
        if (isset($datos['id_rol'])) {
            // Eliminar roles anteriores
            $stmt = $this->db->prepare("DELETE FROM usuario_roles WHERE id_usuario = ?");
            $stmt->execute([$idUsuario]);
            // Agregar nuevo rol
            $stmt = $this->db->prepare("INSERT INTO usuario_roles (id_usuario, id_rol) VALUES (?, ?)");
            $stmt->execute([$idUsuario, $datos['id_rol']]);
        }

        return true;
    }

    /**
     * Cambiar password
     */
    public function cambiarPassword($idUsuario, $passwordActual, $passwordNuevo) {
        // Verificar password actual
        $stmt = $this->db->prepare("SELECT ps FROM usuarios WHERE id = ?");
        $stmt->execute([$idUsuario]);
        $usuario = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$usuario) {
            throw new \Exception("Usuario no encontrado");
        }

        if (!password_verify($passwordActual, $usuario['ps']) && $usuario['ps'] !== $passwordActual) {
            throw new \Exception("Password actual incorrecto");
        }

        // Actualizar password
        $hash = password_hash($passwordNuevo, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("UPDATE usuarios SET ps = ? WHERE id = ?");
        $stmt->execute([$hash, $idUsuario]);

        return true;
    }

    /**
     * Listar usuarios
     */
    public function listar($filtros = []) {
        $sql = "
            SELECT u.id, u.nombre, u.apellido, u.email, u.us as usuario,
                   u.activo, u.ultimo_login, u.fecha_creacion,
                   r.nombre as rol, r.nivel as rol_nivel
            FROM usuarios u
            LEFT JOIN roles r ON u.id_roles = r.id
            WHERE 1=1
        ";
        $params = [];

        if (isset($filtros['activo'])) {
            $sql .= " AND u.activo = ?";
            $params[] = $filtros['activo'] ? 1 : 0;
        }

        if (isset($filtros['id_rol'])) {
            $sql .= " AND u.id_roles = ?";
            $params[] = $filtros['id_rol'];
        }

        if (isset($filtros['busqueda'])) {
            $sql .= " AND (u.nombre LIKE ? OR u.us LIKE ? OR u.email LIKE ?)";
            $busqueda = "%{$filtros['busqueda']}%";
            $params[] = $busqueda;
            $params[] = $busqueda;
            $params[] = $busqueda;
        }

        $sql .= " ORDER BY u.nombre";

        if (isset($filtros['limite'])) {
            $sql .= " LIMIT ?";
            $params[] = $filtros['limite'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si un usuario tiene acceso a una sucursal
     */
    public function tieneAccesoSucursal($idUsuario, $idSucursal) {
        // Obtener rol del usuario
        $stmt = $this->db->prepare("
            SELECT r.nivel FROM usuarios u
            INNER JOIN roles r ON u.id_roles = r.id
            WHERE u.id = ?
        ");
        $stmt->execute([$idUsuario]);
        $rol = $stmt->fetch(\PDO::FETCH_ASSOC);

        // ADMIN y roles de planta (nivel <= 20) tienen acceso a todo
        if ($rol && $rol['nivel'] <= 20) {
            return true;
        }

        // Verificar asignación específica
        $stmt = $this->db->prepare("
            SELECT id FROM usuario_sucursales 
            WHERE id_usuario = ? AND id_sucursal = ?
        ");
        $stmt->execute([$idUsuario, $idSucursal]);
        return $stmt->fetch() !== false;
    }

    /**
     * Obtener la sucursal principal de un usuario
     */
    public function obtenerSucursalPrincipal($idUsuario) {
        $stmt = $this->db->prepare("
            SELECT us.id_sucursal, u.nombre as sucursal
            FROM usuario_sucursales us
            INNER JOIN ubicaciones u ON us.id_sucursal = u.id
            WHERE us.id_usuario = ?
            ORDER BY us.es_sucursal_principal DESC
            LIMIT 1
        ");
        $stmt->execute([$idUsuario]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Desactivar usuario (soft delete)
     */
    public function desactivar($idUsuario) {
        $stmt = $this->db->prepare("UPDATE usuarios SET activo = 0 WHERE id = ?");
        $stmt->execute([$idUsuario]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Activar usuario
     */
    public function activar($idUsuario) {
        $stmt = $this->db->prepare("UPDATE usuarios SET activo = 1 WHERE id = ?");
        $stmt->execute([$idUsuario]);
        return $stmt->rowCount() > 0;
    }
}
