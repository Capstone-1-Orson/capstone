-- =====================================================================
-- Migration: Create ingredient_waste_log table
-- Run this once on your MySQL database.
-- =====================================================================

CREATE TABLE IF NOT EXISTS `ingredient_waste_log` (
  `id`            int(11)        NOT NULL AUTO_INCREMENT,
  `ingredient_id` int(11)        NOT NULL,
  `qty_wasted`    decimal(10,3)  NOT NULL DEFAULT 0.000,
  `reason`        varchar(255)   DEFAULT NULL,
  `reported_by`   varchar(100)   DEFAULT NULL,
  `waste_date`    date           NOT NULL,
  `created_at`    datetime       DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_waste_ingredient` (`ingredient_id`),
  CONSTRAINT `fk_waste_ingredient`
    FOREIGN KEY (`ingredient_id`)
    REFERENCES `ingredients` (`id`)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
