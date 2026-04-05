-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 24-03-2026 a las 23:10:50
-- Versión del servidor: 10.4.25-MariaDB
-- Versión de PHP: 7.4.30

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `u237583611_mikelo`
--

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`, `nivel`, `descripcion`, `fecha_creacion`) VALUES
(1, 'ADMIN', 10, 'Administrador del sistema - Acceso total', '2026-01-23 08:10:38'),
(2, 'PLANTA_JEFE', 20, 'Jefe de planta - Gestión completa del depósito', '2026-01-23 08:10:38'),
(3, 'PLANTA_OPERARIO', 25, 'Operario de planta - Operaciones de depósito', '2026-01-23 08:10:38'),
(4, 'FRANQUICIA_ADMIN', 30, 'Administrador de franquicia', '2026-01-23 08:10:38'),
(5, 'FRANQUICIA_EMPLEADO', 40, 'Empleado de franquicia', '2026-01-23 08:10:38');

--
-- Volcado de datos para la tabla `usuarios`
--

INSERT INTO `usuarios` (`id`, `nombre`, `apellido`, `email`, `us`, `ps`, `activo`, `id_roles`, `ultimo_login`, `creado_por`, `fecha_creacion`) VALUES
(1, 'Administrador', 'Sistema', '', 'admin', '$2y$10$/ycqRE/3QWdrA7RorZPZUeozL.WcwEsNkvmJnr4TqtOZ.ZORnvTHG', 1, 1, '2026-03-24 19:03:18', NULL, '2026-01-23 08:10:38'),
(2, 'Usuario', 'Franquicia Test', NULL, 'franquicia_test', '$2y$10$/ycqRE/3QWdrA7RorZPZUeozL.WcwEsNkvmJnr4TqtOZ.ZORnvTHG', 1, 4, NULL, NULL, '2026-01-23 08:12:59'),
(3, 'Jefe', 'Planta', NULL, 'jefe_planta', '$2y$10$JDnRy34ZNxhd2wzpKfr87uE.SMUXSrFn8nWTWsP4WAzRk9RY8vMeq', 1, 2, '2026-02-26 08:10:02', NULL, '2026-01-23 09:06:22'),
(4, 'Operario', 'Uno', NULL, 'operario1', '$2y$10$JDnRy34ZNxhd2wzpKfr87uE.SMUXSrFn8nWTWsP4WAzRk9RY8vMeq', 1, 3, '2026-02-26 03:31:47', NULL, '2026-01-23 09:06:22'),
(5, 'Admin', 'Elordi', NULL, 'admin_elordi', '$2y$10$JDnRy34ZNxhd2wzpKfr87uE.SMUXSrFn8nWTWsP4WAzRk9RY8vMeq', 1, 4, '2026-03-20 07:15:42', NULL, '2026-01-23 09:06:22'),
(6, 'Empleado', 'Elordi', NULL, 'empleado_elordi', '$2y$10$JDnRy34ZNxhd2wzpKfr87uE.SMUXSrFn8nWTWsP4WAzRk9RY8vMeq', 1, 5, NULL, NULL, '2026-01-23 09:06:22'),
(7, 'Admin', 'Mikelo Oeste', NULL, 'admin_suc3', '$2y$10$JDnRy34ZNxhd2wzpKfr87uE.SMUXSrFn8nWTWsP4WAzRk9RY8vMeq', 1, 4, NULL, NULL, '2026-01-23 09:06:22'),
(8, 'Empleado', 'Mikelo Oeste', NULL, 'empleado_suc3', '$2y$10$JDnRy34ZNxhd2wzpKfr87uE.SMUXSrFn8nWTWsP4WAzRk9RY8vMeq', 1, 5, NULL, NULL, '2026-01-23 09:06:22'),
(9, 'Test User', NULL, NULL, 'test_user', '$2y$10$o9xnqUWVLf4EXHb4OO2o/.Xs5xwX7NbMGoi.fsoklldO.QEnlRzpi', 1, 5, NULL, 1, '2026-01-23 09:42:35'),
(10, 'prueba', 'test', 'ddpp@as.com', 'prueba', '$2y$10$bdzxNTDU71lMJYqhkDzjKeb7Ze4bIGejSRdvWijnZVSUmenVLIsTS', 1, 5, '2026-02-26 03:22:59', 1, '2026-02-26 06:20:46');

--
-- Volcado de datos para la tabla `usuario_roles`
--

INSERT INTO `usuario_roles` (`id`, `id_usuario`, `id_rol`, `asignado_por`, `fecha_asignacion`) VALUES
(2, 9, 5, 1, '2026-01-23 09:42:35'),
(3, 1, 1, NULL, '2026-02-26 06:18:25'),
(5, 10, 5, NULL, '2026-02-26 06:29:00');

--
-- Volcado de datos para la tabla `usuario_sucursales`
--

INSERT INTO `usuario_sucursales` (`id`, `id_usuario`, `id_sucursal`, `es_sucursal_principal`, `asignado_por`, `fecha_asignacion`) VALUES
(1, 2, 2, 1, NULL, '2026-01-23 08:12:59'),
(2, 5, 2, 1, NULL, '2026-01-23 09:06:22'),
(3, 6, 2, 1, NULL, '2026-01-23 09:06:22'),
(4, 7, 3, 1, NULL, '2026-01-23 09:06:22'),
(5, 8, 3, 1, NULL, '2026-01-23 09:06:22'),
(6, 1, 2, 0, NULL, '2026-02-26 06:18:25'),
(7, 1, 6, 0, NULL, '2026-02-26 06:18:25'),
(8, 1, 3, 0, NULL, '2026-02-26 06:18:25'),
(9, 1, 4, 0, NULL, '2026-02-26 06:18:25'),
(10, 1, 5, 0, NULL, '2026-02-26 06:18:25'),
(12, 10, 2, 0, NULL, '2026-02-26 06:29:00');
SET FOREIGN_KEY_CHECKS=1;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
