SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


CREATE TABLE `documentos` (
  `id` int NOT NULL,
  `vehiculo_id` int NOT NULL,
  `tipo_documento` enum('permiso_circulacion_a','permiso_circulacion_b','certificado_homologacion','factura','certificado_inscripcion_a','certificado_inscripcion_b') NOT NULL,
  `nombre_archivo` varchar(255) NOT NULL,
  `ruta_archivo` varchar(255) NOT NULL,
  `fecha_subida` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `estados_permisos`
--

CREATE TABLE `estados_permisos` (
  `id` int NOT NULL,
  `permiso_id` int NOT NULL,
  `estado` enum('disponible','en_revision','completado') NOT NULL DEFAULT 'disponible',
  `funcionario_id` int DEFAULT NULL,
  `nombre_funcionario` varchar(100) DEFAULT NULL,
  `fecha_inicio_revision` timestamp NULL DEFAULT NULL,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `funcionarios`
--

CREATE TABLE `funcionarios` (
  `id` int NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `rol` varchar(20) NOT NULL DEFAULT 'funcionario',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `funcionarios`
--

INSERT INTO `funcionarios` (`id`, `nombre`, `apellido`, `email`, `username`, `password`, `rol`, `fecha_creacion`) VALUES
(1, 'Administrador', 'Sistema', 'admin@sistema.cl', 'admin', 'admin123', 'administrador', '2025-03-04 16:00:15'),
(2, 'Usuario', 'Prueba', 'usuario@sistema.cl', 'user', 'user123', 'funcionario', '2025-03-04 16:00:15'),
(3, 'Alejandra', 'Pereira', 'alejandra.pereira@munimelipilla.cl', 'apereira', 'ap2025', 'funcionario', '2025-03-12 18:52:13'),
(4, 'Rodrigo', 'Greau', 'rodrigo.greau@munimelipilla.cl', 'rgreau', 'rg2025', 'funcionario', '2025-03-12 18:52:13');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `notificaciones`
--

CREATE TABLE `notificaciones` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `vehiculo_id` int NOT NULL,
  `mensaje` text NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `leida` tinyint(1) DEFAULT '0',
  `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `solicitudes`
--

CREATE TABLE `solicitudes` (
  `id` int NOT NULL,
  `vehiculo_id` int NOT NULL,
  `estado` enum('pendiente','aprobada','rechazada') DEFAULT 'pendiente',
  `fecha_solicitud` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `comentarios` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `usuarios`
--

CREATE TABLE `usuarios` (
  `id` int NOT NULL,
  `rut` varchar(15) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `apellido_paterno` varchar(100) NOT NULL,
  `apellido_materno` varchar(100) NOT NULL,
  `comuna` varchar(100) NOT NULL,
  `calle` varchar(150) NOT NULL,
  `numero` varchar(20) NOT NULL,
  `aclaratoria` varchar(255) DEFAULT NULL,
  `telefono` varchar(20) NOT NULL,
  `email` varchar(150) NOT NULL,
  `fecha_registro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `vehiculos`
--

CREATE TABLE `vehiculos` (
  `id` int NOT NULL,
  `placa_patente` varchar(10) NOT NULL,
  `usuario_id` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `visualizaciones_activas`
--

CREATE TABLE `visualizaciones_activas` (
  `id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `permiso_id` int NOT NULL,
  `inicio` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `vehiculo_id` (`vehiculo_id`,`tipo_documento`);

--
-- Indices de la tabla `estados_permisos`
--
ALTER TABLE `estados_permisos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `permiso_id` (`permiso_id`),
  ADD KEY `fk_estados_permisos_funcionario` (`funcionario_id`);

--
-- Indices de la tabla `funcionarios`
--
ALTER TABLE `funcionarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indices de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD PRIMARY KEY (`id`),
  ADD KEY `usuario_id` (`usuario_id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`);

--
-- Indices de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `vehiculo_id` (`vehiculo_id`),
  ADD KEY `idx_solicitudes_estado` (`estado`);

--
-- Indices de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rut` (`rut`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indices de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `placa_patente` (`placa_patente`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `visualizaciones_activas`
--
ALTER TABLE `visualizaciones_activas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `usuario_permiso` (`usuario_id`,`permiso_id`),
  ADD KEY `permiso_id` (`permiso_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `documentos`
--
ALTER TABLE `documentos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `estados_permisos`
--
ALTER TABLE `estados_permisos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `funcionarios`
--
ALTER TABLE `funcionarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `usuarios`
--
ALTER TABLE `usuarios`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT de la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

--
-- AUTO_INCREMENT de la tabla `visualizaciones_activas`
--
ALTER TABLE `visualizaciones_activas`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=171;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `documentos`
--
ALTER TABLE `documentos`
  ADD CONSTRAINT `documentos_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`);

--
-- Filtros para la tabla `estados_permisos`
--
ALTER TABLE `estados_permisos`
  ADD CONSTRAINT `fk_estados_permisos_funcionario` FOREIGN KEY (`funcionario_id`) REFERENCES `funcionarios` (`id`);

--
-- Filtros para la tabla `notificaciones`
--
ALTER TABLE `notificaciones`
  ADD CONSTRAINT `notificaciones_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  ADD CONSTRAINT `notificaciones_ibfk_2` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`);

--
-- Filtros para la tabla `solicitudes`
--
ALTER TABLE `solicitudes`
  ADD CONSTRAINT `solicitudes_ibfk_1` FOREIGN KEY (`vehiculo_id`) REFERENCES `vehiculos` (`id`);

--
-- Filtros para la tabla `vehiculos`
--
ALTER TABLE `vehiculos`
  ADD CONSTRAINT `vehiculos_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`);

--
-- Filtros para la tabla `visualizaciones_activas`
--
ALTER TABLE `visualizaciones_activas`
  ADD CONSTRAINT `visualizaciones_activas_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `funcionarios` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visualizaciones_activas_ibfk_2` FOREIGN KEY (`permiso_id`) REFERENCES `vehiculos` (`id`) ON DELETE CASCADE;
COMMIT; 