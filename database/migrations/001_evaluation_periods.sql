-- =====================================================================
-- Migration 001 — Périodes d'évaluation
-- À exécuter UNE FOIS dans phpMyAdmin si votre base est déjà utilisée.
-- 100 % additif : crée une nouvelle table, ne modifie AUCUNE table existante
-- (élèves, horaires, évaluations, présences… restent intacts).
-- Sans danger si exécuté plusieurs fois (IF NOT EXISTS).
-- Remarque : le site crée aussi cette table tout seul à la première
-- ouverture de la page « Évaluations » ; ce fichier est donc facultatif.
-- =====================================================================
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS evaluation_periods (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_year_id INT UNSIGNED NOT NULL,
  school_id INT UNSIGNED NOT NULL,
  label VARCHAR(60) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  INDEX idx_evalperiods (school_year_id, school_id, start_date),
  CONSTRAINT fk_evalperiods_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE CASCADE,
  CONSTRAINT fk_evalperiods_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
