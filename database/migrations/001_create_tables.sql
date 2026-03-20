-- ─────────────────────────────────────────────
--  database/migrations/001_create_tables.sql
--  Migración de PostgreSQL (Neon) → MySQL
--  Compatible con PHPMyAdmin / MySQL 8+
-- ─────────────────────────────────────────────

SET FOREIGN_KEY_CHECKS = 0;

-- ── users ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`                  CHAR(36)     NOT NULL,
    `email`               VARCHAR(255) NOT NULL,
    `password_hash`       VARCHAR(255) NOT NULL,
    `role`                VARCHAR(50)  NOT NULL DEFAULT 'user',
    `is_active`           TINYINT(1)   NOT NULL DEFAULT 1,
    `is_verified`         TINYINT(1)   NOT NULL DEFAULT 0,
    `reset_token`         TEXT         NULL,
    `reset_token_expire`  DATETIME     NULL,
    `last_login`          DATETIME     NULL,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── clientes ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `clientes` (
    `id`               CHAR(36)     NOT NULL,
    `nombre`           VARCHAR(100) NOT NULL,
    `apellido`         VARCHAR(100) NOT NULL,
    `email`            VARCHAR(255) NULL,
    `telefono`         VARCHAR(50)  NULL,
    `domicilio`        VARCHAR(255) NULL,
    `numero_domicilio` INT          NULL,
    `numero_cliente`   VARCHAR(50)  NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_clientes_email`          (`email`),
    UNIQUE KEY `uq_clientes_numero_cliente` (`numero_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── tecnicos ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `tecnicos` (
    `id`                  CHAR(36)     NOT NULL,
    `nombre`              VARCHAR(100) NOT NULL,
    `apellido`            VARCHAR(100) NOT NULL,
    `email`               VARCHAR(255) NULL,
    `telefono`            VARCHAR(50)  NULL,
    `imagen_url`          TEXT         NULL,
    `activo`              TINYINT(1)   NOT NULL DEFAULT 1,
    `duracion_turno_min`  INT          NOT NULL DEFAULT 30,
    `created_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_tecnicos_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── tecnico_disponibilidad ────────────────────
CREATE TABLE IF NOT EXISTS `tecnico_disponibilidad` (
    `id`          CHAR(36)  NOT NULL,
    `tecnico_id`  CHAR(36)  NOT NULL,
    `dia_semana`  SMALLINT  NOT NULL COMMENT '0=Lun, 1=Mar, ..., 6=Dom',
    `hora_inicio` TIME      NOT NULL,
    `hora_fin`    TIME      NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_disp_tecnico_dia` (`tecnico_id`, `dia_semana`),
    CONSTRAINT `fk_disp_tecnico`
        FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── turnos ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `turnos` (
    `id`             CHAR(36)     NOT NULL,
    `cliente_id`     CHAR(36)     NOT NULL,
    `tecnico_id`     CHAR(36)     NOT NULL,
    `fecha`          DATE         NOT NULL,
    `hora_inicio`    TIME         NOT NULL,
    `hora_fin`       TIME         NOT NULL,
    `estado`         VARCHAR(50)  NOT NULL DEFAULT 'pendiente'
                         COMMENT 'pendiente | confirmado | completado | cancelado',
    `tipo_turno`     INT          NOT NULL DEFAULT 1,
    `rango_horario`  VARCHAR(50)  NULL,
    `numero_ticket`  VARCHAR(50)  NULL,
    `cancelado_en`   DATETIME     NULL,
    `cancelado_por`  CHAR(36)     NULL,
    `created_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_turnos_ticket` (`numero_ticket`),
    KEY `idx_turnos_tecnico_fecha` (`tecnico_id`, `fecha`),
    KEY `idx_turnos_cliente`       (`cliente_id`),
    KEY `idx_turnos_fecha`         (`fecha`),
    CONSTRAINT `fk_turnos_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_turnos_tecnico`
        FOREIGN KEY (`tecnico_id`) REFERENCES `tecnicos` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
