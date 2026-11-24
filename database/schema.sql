-- =====================================================================
-- Journal de classe — structure de la base MySQL
-- Exécuté automatiquement par public/install.php
-- (ou à importer à la main dans phpMyAdmin chez OVH).
-- =====================================================================
SET NAMES utf8mb4;

-- Compte enseignant
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  display_name VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL,
  last_login_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Codes de double authentification (stockés hachés)
CREATE TABLE IF NOT EXISTS auth_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_codes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tentatives de connexion (anti force brute)
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(190) NOT NULL,
  kind VARCHAR(20) NOT NULL,
  success TINYINT(1) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_attempts_ip (ip, created_at),
  INDEX idx_attempts_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Journal des connexions et actions sensibles
CREATE TABLE IF NOT EXISTS audit_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(50) NOT NULL,
  details VARCHAR(255) NOT NULL DEFAULT '',
  ip VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  INDEX idx_audit_date (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Années scolaires (archivage)
CREATE TABLE IF NOT EXISTS school_years (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(20) NOT NULL UNIQUE,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  is_archived TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Congés (calendrier FWB + vos journées pédagogiques)
CREATE TABLE IF NOT EXISTS holidays (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  INDEX idx_holidays_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- École > Classe > Élève
CREATE TABLE IF NOT EXISTS schools (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  city VARCHAR(100) NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  school_year_id INT UNSIGNED NOT NULL,
  school_id INT UNSIGNED NOT NULL,
  name VARCHAR(60) NOT NULL,
  subject VARCHAR(100) NULL,
  color TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT fk_classes_year FOREIGN KEY (school_year_id) REFERENCES school_years(id) ON DELETE CASCADE,
  CONSTRAINT fk_classes_school FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  last_name VARCHAR(100) NOT NULL,
  first_name VARCHAR(100) NOT NULL DEFAULT '',
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  notes TEXT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_students_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Horaire hebdomadaire : un créneau = jour + période de début + durée (1 à 4 périodes)
CREATE TABLE IF NOT EXISTS schedule_slots (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  weekday TINYINT UNSIGNED NOT NULL,
  start_period TINYINT UNSIGNED NOT NULL,
  duration TINYINT UNSIGNED NOT NULL DEFAULT 1,
  room VARCHAR(30) NULL,
  CONSTRAINT fk_slots_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tranche de cours réellement donnée (journal de classe)
CREATE TABLE IF NOT EXISTS lessons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  lesson_date DATE NOT NULL,
  start_period TINYINT UNSIGNED NOT NULL,
  duration TINYINT UNSIGNED NOT NULL DEFAULT 1,
  title VARCHAR(200) NULL,
  document_url VARCHAR(500) NULL,
  notes TEXT NULL,
  cancelled TINYINT(1) NOT NULL DEFAULT 0,
  attendance_saved_at DATETIME NULL,
  created_at DATETIME NOT NULL,
  UNIQUE KEY uq_lesson (class_id, lesson_date, start_period),
  INDEX idx_lesson_date (lesson_date),
  CONSTRAINT fk_lessons_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Présence d'un élève pour UNE période d'une tranche
CREATE TABLE IF NOT EXISTS attendance (
  lesson_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  period TINYINT UNSIGNED NOT NULL,
  status ENUM('P','A','AT','R') NOT NULL,
  PRIMARY KEY (lesson_id, student_id, period),
  CONSTRAINT fk_att_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_att_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Appréciation + commentaire d'un élève pour une tranche
CREATE TABLE IF NOT EXISTS lesson_notes (
  lesson_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  appreciation ENUM('vert','orange','rouge','noir') NULL,
  appreciation_text TEXT NULL,
  comment TEXT NULL,
  PRIMARY KEY (lesson_id, student_id),
  CONSTRAINT fk_notes_lesson FOREIGN KEY (lesson_id) REFERENCES lessons(id) ON DELETE CASCADE,
  CONSTRAINT fk_notes_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Évaluations
CREATE TABLE IF NOT EXISTS evaluations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  eval_date DATE NOT NULL,
  title VARCHAR(200) NOT NULL,
  max_points DECIMAL(6,2) NOT NULL,
  description TEXT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_evals_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS evaluation_scores (
  evaluation_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  score DECIMAL(6,2) NULL,
  is_absent TINYINT(1) NOT NULL DEFAULT 0,
  comment TEXT NULL,
  photo VARCHAR(80) NULL,
  PRIMARY KEY (evaluation_id, student_id),
  CONSTRAINT fk_scores_eval FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
  CONSTRAINT fk_scores_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conseils de classe
CREATE TABLE IF NOT EXISTS councils (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  class_id INT UNSIGNED NOT NULL,
  council_date DATE NOT NULL,
  title VARCHAR(200) NOT NULL,
  period_start DATE NOT NULL,
  period_end DATE NOT NULL,
  created_at DATETIME NOT NULL,
  CONSTRAINT fk_councils_class FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS council_entries (
  council_id INT UNSIGNED NOT NULL,
  student_id INT UNSIGNED NOT NULL,
  appreciation ENUM('vert','orange','rouge','noir') NULL,
  comment TEXT NULL,
  decision VARCHAR(255) NULL,
  photo VARCHAR(80) NULL,
  PRIMARY KEY (council_id, student_id),
  CONSTRAINT fk_entries_council FOREIGN KEY (council_id) REFERENCES councils(id) ON DELETE CASCADE,
  CONSTRAINT fk_entries_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
