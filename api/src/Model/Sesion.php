<?php
namespace App\Model;

/**
 * Modelo para gestión de Sesiones PHP
 * Implementa sesiones seguras con almacenamiento en BD
 */
class Sesion {
    private $db;
    private $duracionSesion = 28800; // 8 horas en segundos

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Crear nueva sesión
     * @param int $idUsuario
     * @param string $ip - IP del cliente
     * @param string $userAgent - User agent del navegador
     * @return string - Token de sesión
     */
    public function crear($idUsuario, $ip = null, $userAgent = null) {
        // Generar token único
        $token = bin2hex(random_bytes(32));
        
        // Calcular expiración
        $expira = date('Y-m-d H:i:s', time() + $this->duracionSesion);

        // Insertar sesión
        $stmt = $this->db->prepare("
            INSERT INTO sesiones (id_usuario, token, ip_address, user_agent, expira_en)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$idUsuario, $token, $ip, $userAgent, $expira]);

        return $token;
    }

    /**
     * Validar sesión por token
     * @param string $token
     * @return array|false - Datos del usuario o false si inválida
     */
    public function validar($token) {
        $stmt = $this->db->prepare("
            SELECT s.*, u.id as usuario_id, u.nombre, u.apellido, u.us as usuario, 
                   u.id_roles, r.nombre as rol, r.nivel as rol_nivel
            FROM sesiones s
            INNER JOIN usuarios u ON s.id_usuario = u.id
            LEFT JOIN roles r ON u.id_roles = r.id
            WHERE s.token = ? 
              AND s.expira_en > NOW()
              AND u.activo = 1
        ");
        $stmt->execute([$token]);
        $sesion = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$sesion) {
            return false;
        }

        // Actualizar última actividad
        $this->actualizarActividad($token);

        return [
            'id_sesion' => $sesion['id'],
            'id_usuario' => $sesion['usuario_id'],
            'nombre' => $sesion['nombre'],
            'apellido' => $sesion['apellido'],
            'usuario' => $sesion['usuario'],
            'id_rol' => $sesion['id_roles'],
            'rol' => $sesion['rol'],
            'rol_nivel' => $sesion['rol_nivel'],
            'expira_en' => $sesion['expira_en']
        ];
    }

    /**
     * Actualizar última actividad y extender sesión
     */
    private function actualizarActividad($token) {
        $nuevaExpiracion = date('Y-m-d H:i:s', time() + $this->duracionSesion);
        $stmt = $this->db->prepare("
            UPDATE sesiones 
            SET ultima_actividad = NOW(), expira_en = ?
            WHERE token = ?
        ");
        $stmt->execute([$nuevaExpiracion, $token]);
    }

    /**
     * Cerrar sesión (logout)
     */
    public function cerrar($token) {
        $stmt = $this->db->prepare("DELETE FROM sesiones WHERE token = ?");
        $stmt->execute([$token]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Cerrar todas las sesiones de un usuario
     */
    public function cerrarTodasDelUsuario($idUsuario) {
        $stmt = $this->db->prepare("DELETE FROM sesiones WHERE id_usuario = ?");
        $stmt->execute([$idUsuario]);
        return $stmt->rowCount();
    }

    /**
     * Limpiar sesiones expiradas (para cron o mantenimiento)
     */
    public function limpiarExpiradas() {
        $stmt = $this->db->prepare("DELETE FROM sesiones WHERE expira_en < NOW()");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Obtener sesiones activas de un usuario
     */
    public function obtenerSesionesActivas($idUsuario) {
        $stmt = $this->db->prepare("
            SELECT id, ip_address, user_agent, creada_en, ultima_actividad, expira_en
            FROM sesiones 
            WHERE id_usuario = ? AND expira_en > NOW()
            ORDER BY ultima_actividad DESC
        ");
        $stmt->execute([$idUsuario]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Verificar si una sesión pertenece a un usuario específico
     */
    public function perteneceAUsuario($token, $idUsuario) {
        $stmt = $this->db->prepare("
            SELECT id FROM sesiones 
            WHERE token = ? AND id_usuario = ?
        ");
        $stmt->execute([$token, $idUsuario]);
        return $stmt->fetch() !== false;
    }
}
