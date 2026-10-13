-- Base de datos para el Sistema de Patrimonio Escolar
-- Conforme al Decreto Ley 3.300/72 - Dirección General de Cultura y Educación (PBA)

CREATE DATABASE IF NOT EXISTS `patrimonio_escolar` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `patrimonio_escolar`;

-- 1. Tabla de Usuarios con roles exclusivos (1 Director, 2 Ayudantes)
DROP TABLE IF EXISTS `movimientos`;
DROP TABLE IF EXISTS `bienes`;
DROP TABLE IF EXISTS `ubicaciones`;
DROP TABLE IF EXISTS `institucion_config`;
DROP TABLE IF EXISTS `usuarios`;

CREATE TABLE `usuarios` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `rol` ENUM('director', 'ayudante') NOT NULL,
  `cargo` VARCHAR(150) NOT NULL,
  `ultimo_acceso` DATETIME NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Tabla de Ubicaciones Escolares (Mapa Digital)
CREATE TABLE `ubicaciones` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `codigo` VARCHAR(20) NOT NULL UNIQUE,
  `nombre` VARCHAR(100) NOT NULL,
  `tipo` VARCHAR(50) NOT NULL,
  `responsable` VARCHAR(150) NOT NULL,
  `piso` VARCHAR(50) DEFAULT 'Planta Baja',
  `icono` VARCHAR(50) DEFAULT 'warehouse'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Tabla del Catálogo General de Bienes Patrimoniales
CREATE TABLE `bienes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nro_cuenta` VARCHAR(50) NOT NULL,
  `nro_orden` INT NOT NULL,
  `identificacion` VARCHAR(255) NOT NULL,
  `categoria` VARCHAR(100) NOT NULL,
  `ubicacion_id` INT NULL,
  `ubicacion_nombre` VARCHAR(150) NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 1,
  `valor_unitario` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `valor_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `estado` ENUM('Bueno', 'Regular', 'En Reparación', 'Baja') NOT NULL DEFAULT 'Bueno',
  `motivo_alta` VARCHAR(150) NOT NULL DEFAULT 'Adquisición Fondos Provinciales',
  `expediente` VARCHAR(100) NOT NULL DEFAULT 'EX-2024-034821-GDEBA-DGCYE',
  `fecha_alta` DATE NOT NULL,
  `auditado_anual` TINYINT(1) NOT NULL DEFAULT 0,
  `fecha_auditoria` DATETIME NULL,
  `auditor_nombre` VARCHAR(150) NULL,
  `observaciones` TEXT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`nro_cuenta`),
  INDEX (`nro_orden`),
  INDEX (`categoria`),
  INDEX (`estado`),
  INDEX (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Tabla de Movimientos Trimestrales (Altas y Bajas Oficiales)
CREATE TABLE `movimientos` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `bien_id` INT NULL,
  `tipo` ENUM('ALTA', 'BAJA') NOT NULL,
  `trimestre` INT NOT NULL,
  `anio` INT NOT NULL,
  `nro_cuenta` VARCHAR(50) NOT NULL,
  `nro_orden` INT NOT NULL,
  `identificacion` VARCHAR(255) NOT NULL,
  `cantidad` INT NOT NULL DEFAULT 1,
  `valor_unitario` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `valor_total` DECIMAL(14,2) NOT NULL DEFAULT 0.00,
  `motivo` VARCHAR(255) NOT NULL,
  `expediente` VARCHAR(100) NOT NULL,
  `fecha` DATE NOT NULL,
  `usuario_id` INT NULL,
  `usuario_nombre` VARCHAR(150) NOT NULL,
  `acta_disposicion` VARCHAR(100) NULL,
  `observaciones` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX (`tipo`),
  INDEX (`trimestre`),
  INDEX (`anio`),
  INDEX (`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabla de Configuración Institucional
CREATE TABLE `institucion_config` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `nombre_escuela` VARCHAR(255) NOT NULL DEFAULT 'luciano reyes',
  `cue` VARCHAR(50) NOT NULL DEFAULT '0601245-00',
  `distrito` VARCHAR(100) NOT NULL DEFAULT 'Distrito Escolar Quilmes - Región IV',
  `director_nombre` VARCHAR(150) NOT NULL DEFAULT 'Prof. Lucas Ariel Santucho',
  `delegado_patrimonial` VARCHAR(150) NOT NULL DEFAULT 'Mauro Ezequiel Gonzalez Ojeda',
  `secretario_nombre` VARCHAR(150) NOT NULL DEFAULT 'Ezequiel Alonso',
  `decreto_referencia` VARCHAR(100) NOT NULL DEFAULT 'Decreto 3.300/72 y modif. DGCyE PBA',
  `trimestre_activo` INT NOT NULL DEFAULT 3,
  `anio_activo` INT NOT NULL DEFAULT 2024
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Inserción de Configuración Inicial
INSERT INTO `institucion_config` (`id`, `nombre_escuela`, `cue`, `distrito`, `director_nombre`, `delegado_patrimonial`, `secretario_nombre`, `decreto_referencia`, `trimestre_activo`, `anio_activo`)
VALUES (1, 'luciano reyes', '0601429-00', 'Dirección General de Cultura y Educación - Pcia. Bs. As.', 'Lucas Ariel Santucho', 'Mauro Ezequiel Gonzalez Ojeda', 'Ezequiel Alonso', 'Decreto Ley 3.300/72 DGCyE', 3, 2024);

-- Inserción de Usuario Único Oficial (Administrador General SIGPE)
-- Password: 'admin123' (hash bcrypt)
INSERT INTO `usuarios` (`id`, `nombre`, `email`, `password_hash`, `rol`, `cargo`) VALUES
(1, 'Administrador SIGPE', 'admin@patrimonio.edu.ar', '$2y$10$ciXNh0/q5eqrZjZeFVGjp.334/N907GpTCoeU11sP0YyOqoshA8Ji', 'director', 'Administrador de Patrimonio');

-- Inserción de Ubicaciones Escolares
INSERT INTO `ubicaciones` (`id`, `codigo`, `nombre`, `tipo`, `responsable`, `piso`, `icono`) VALUES
(1, 'TAL-MEC', 'Taller de Mecánica y Ajuste', 'Taller', 'Mauro Gonzalez Ojeda', 'Planta Baja', 'wrench'),
(2, 'TAL-SOL', 'Taller de Soldadura y Herrería', 'Taller', 'Mauro Gonzalez Ojeda', 'Planta Baja', 'flame'),
(3, 'TAL-CAR', 'Taller de Carpintería y Modelistería', 'Taller', 'Patricio Filosi', 'Planta Baja', 'hammer'),
(4, 'PAN-CEN', 'Pañol Central de Herramientas', 'Pañol', 'Mauro Gonzalez Ojeda', 'Planta Baja', 'archive'),
(5, 'LAB-INF', 'Laboratorio de Informática y Redes', 'Laboratorio', 'Ezequiel Alonso', '1° Piso', 'laptop'),
(6, 'LAB-ELE', 'Laboratorio de Electrónica y Automatización', 'Laboratorio', 'Ivo Comezaña', '1° Piso', 'cpu'),
(7, 'AUL-GEN', 'Aulas de Ciclo Básico y Superior (1 a 8)', 'Aulas', 'Ezequiel Alonso', '1° y 2° Piso', 'school'),
(8, 'DIR-SEC', 'Dirección, Secretaría y Sala de Profesores', 'Administración', 'Lucas Ariel Santucho', 'Planta Baja', 'landmark'),
(9, 'DEP-GEN', 'Depósito General y Materiales de Consumo', 'Depósito', 'Mauro Gonzalez Ojeda', 'Subsuelo', 'package');

-- Tablas de bienes y movimientos listas en 0 para carga manual oficial
-- (Los productos se cargarán manualmente desde la interfaz de SIGPE)
