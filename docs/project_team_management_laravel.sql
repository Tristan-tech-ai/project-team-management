-- ============================================================================
-- PROJECT & TEAM MANAGEMENT | SCHEMA MYSQL UNTUK LARAVEL
-- Versi 1.0 | MySQL 8.0+ | utf8mb4 | InnoDB
-- ============================================================================
-- PENTING:
-- 1. Buat proyek Laravel dan atur .env ke database MySQL kosong.
-- 2. Jalankan: php artisan migrate
--    Ini membuat tabel users, password_reset_tokens, sessions, dll sesuai
--    starter Laravel. File ini SENGAJA TIDAK membuat tabel users.
-- 3. Pilih database yang sama di phpMyAdmin, lalu import file SQL ini SATU KALI.
-- 4. Jangan membuat migrations Laravel dengan tabel yang sama lalu migrate ulang.
--    Untuk deployment jangka panjang, pindahkan DDL ini ke Laravel migrations.
-- 5. Tidak ada DROP TABLE, TRUNCATE, atau user/password default. Aman dari
--    operasi penghapusan data yang tidak disengaja.
--
-- KONVENSI:
-- * Semua FK ke users.id bertipe BIGINT UNSIGNED (Laravel $table->id()).
-- * created_at/updated_at cocok dengan Eloquent timestamps().
-- * deleted_at mendukung Eloquent SoftDeletes.
-- * Kode status disimpan dalam Bahasa Inggris; label UI dalam Bahasa Indonesia.
-- * project_type = one_time / recurring / ongoing
-- * task.status = backlog / in_progress / review / done
-- * PIC utama disimpan pada projects.pic_user_id dan tasks.pic_user_id.
-- * Banyak pelaksana: project_members dan task_assignees.
-- * Perubahan deadline harus melalui schedule_change_requests. Baseline tidak
--   ditimpa; pengajuan disetujui secara atomik dalam Laravel transaction.
-- * Validasi lintas-tabel seperti 'assignee wajib anggota project' dilakukan
--   pada Laravel FormRequest/Policy/Service (tidak dicukupkan oleh FK biasa).
-- ============================================================================

SET NAMES utf8mb4;

-- ============================================================================
-- 01. STRUKTUR TABEL (31 TABEL MODUL; users DARI LARAVEL)
-- ============================================================================

CREATE TABLE `user_profiles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `phone` VARCHAR(30) NULL,
  `avatar_path` VARCHAR(500) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_profiles_user` (`user_id`),
  CONSTRAINT `fk_user_profiles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(180) NULL,
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `logo_path` VARCHAR(500) NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_companies_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `clients` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(40) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `email` VARCHAR(180) NULL,
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `website` VARCHAR(500) NULL,
  `notes` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_clients_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(80) NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` VARCHAR(500) NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(120) NOT NULL,
  `name` VARCHAR(160) NOT NULL,
  `module` VARCHAR(80) NOT NULL,
  `description` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_permissions` (`role_id`, `permission_id`),
  CONSTRAINT `fk_role_permissions_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission_id` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `user_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_roles` (`user_id`, `role_id`),
  CONSTRAINT `fk_user_roles_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_role_id` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `parent_id` BIGINT UNSIGNED NULL,
  `slug` VARCHAR(120) NOT NULL,
  `title` VARCHAR(120) NOT NULL,
  `icon` VARCHAR(100) NULL,
  `route_name` VARCHAR(180) NULL,
  `permission_id` BIGINT UNSIGNED NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_menu_items_slug` (`slug`),
  KEY `idx_menu_parent_order` (`parent_id`, `sort_order`),
  CONSTRAINT `fk_menu_items_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `menu_items` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menu_items_permission_id` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `positions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_positions_company_name` (`company_id`, `name`),
  CONSTRAINT `fk_positions_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `divisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(120) NOT NULL,
  `lead_user_id` BIGINT UNSIGNED NULL,
  `description` TEXT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_divisions_company_name` (`company_id`, `name`),
  CONSTRAINT `fk_divisions_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_divisions_lead_user_id` FOREIGN KEY (`lead_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `company_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `joined_on` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_members` (`company_id`, `user_id`),
  KEY `idx_company_members_user` (`user_id`),
  CONSTRAINT `fk_company_members_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_company_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `division_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `division_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `position_id` BIGINT UNSIGNED NULL,
  `joined_on` DATE NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_division_members` (`division_id`, `user_id`),
  KEY `idx_division_members_user` (`user_id`),
  CONSTRAINT `fk_division_members_division_id` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_division_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_division_members_position_id` FOREIGN KEY (`position_id`) REFERENCES `positions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `description` TEXT NULL,
  `project_type` VARCHAR(20) NOT NULL DEFAULT 'one_time',
  `default_estimated_minutes` INT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_templates_type` (`project_type`),
  CONSTRAINT `fk_project_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_template_tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_template_id` BIGINT UNSIGNED NOT NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(250) NOT NULL,
  `description` LONGTEXT NULL,
  `task_type` VARCHAR(40) NOT NULL DEFAULT 'general',
  `estimated_minutes` INT UNSIGNED NULL,
  `relative_due_days` INT UNSIGNED NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ptt_template_parent_order` (`project_template_id`, `parent_id`, `sort_order`),
  CONSTRAINT `fk_project_template_tasks_project_template_id` FOREIGN KEY (`project_template_id`) REFERENCES `project_templates` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_template_tasks_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `project_template_tasks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(180) NOT NULL,
  `description` LONGTEXT NULL,
  `task_type` VARCHAR(40) NOT NULL DEFAULT 'general',
  `default_estimated_minutes` INT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_task_templates_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_template_subtasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_template_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(250) NOT NULL,
  `description` LONGTEXT NULL,
  `estimated_minutes` INT UNSIGNED NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tts_template_order` (`task_template_id`, `sort_order`),
  CONSTRAINT `fk_task_template_subtasks_task_template_id` FOREIGN KEY (`task_template_id`) REFERENCES `task_templates` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `projects` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `client_id` BIGINT UNSIGNED NULL,
  `project_template_id` BIGINT UNSIGNED NULL,
  `pic_user_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(250) NOT NULL,
  `description` LONGTEXT NULL,
  `project_type` VARCHAR(20) NOT NULL DEFAULT 'one_time',
  `status` VARCHAR(25) NOT NULL DEFAULT 'planned',
  `start_at` DATETIME NULL,
  `baseline_due_at` DATETIME NULL,
  `due_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `recurrence_unit` VARCHAR(20) NULL,
  `recurrence_interval` INT UNSIGNED NULL,
  `priority` VARCHAR(15) NOT NULL DEFAULT 'normal',
  `notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_projects_code` (`code`),
  KEY `idx_projects_company_status` (`company_id`, `status`),
  KEY `idx_projects_client` (`client_id`),
  KEY `idx_projects_pic` (`pic_user_id`),
  KEY `idx_projects_due` (`due_at`),
  KEY `idx_projects_type` (`project_type`),
  CONSTRAINT `fk_projects_company_id` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_client_id` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_project_template_id` FOREIGN KEY (`project_template_id`) REFERENCES `project_templates` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_projects_pic_user_id` FOREIGN KEY (`pic_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_projects_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_divisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `division_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_divisions` (`project_id`, `division_id`),
  KEY `idx_project_divisions_division` (`division_id`),
  CONSTRAINT `fk_project_divisions_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_divisions_division_id` FOREIGN KEY (`division_id`) REFERENCES `divisions` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `joined_at` DATETIME NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_members` (`project_id`, `user_id`),
  KEY `idx_project_members_user` (`user_id`),
  CONSTRAINT `fk_project_members_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_members_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_member_divisions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_member_id` BIGINT UNSIGNED NOT NULL,
  `project_division_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_member_divisions` (`project_member_id`, `project_division_id`),
  KEY `idx_pmd_division` (`project_division_id`),
  CONSTRAINT `fk_project_member_divisions_project_member_id` FOREIGN KEY (`project_member_id`) REFERENCES `project_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_member_divisions_project_division_id` FOREIGN KEY (`project_division_id`) REFERENCES `project_divisions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_periods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `period_code` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `status` VARCHAR(25) NOT NULL DEFAULT 'active',
  `completed_at` DATETIME NULL,
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_project_periods` (`project_id`, `period_code`),
  KEY `idx_project_periods_dates` (`starts_at`, `ends_at`),
  CONSTRAINT `fk_project_periods_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_periods_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tasks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `project_period_id` BIGINT UNSIGNED NULL,
  `parent_id` BIGINT UNSIGNED NULL,
  `pic_user_id` BIGINT UNSIGNED NOT NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `completed_by` BIGINT UNSIGNED NULL,
  `task_template_id` BIGINT UNSIGNED NULL,
  `title` VARCHAR(250) NOT NULL,
  `description` LONGTEXT NULL,
  `task_type` VARCHAR(40) NOT NULL DEFAULT 'general',
  `status` VARCHAR(25) NOT NULL DEFAULT 'backlog',
  `priority` VARCHAR(15) NOT NULL DEFAULT 'normal',
  `estimated_minutes` INT UNSIGNED NULL,
  `start_at` DATETIME NULL,
  `baseline_due_at` DATETIME NULL,
  `due_at` DATETIME NULL,
  `completed_at` DATETIME NULL,
  `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
  `platform` VARCHAR(80) NULL,
  `scheduled_publish_at` DATETIME NULL,
  `published_at` DATETIME NULL,
  `publication_status` VARCHAR(25) NULL,
  `publication_url` VARCHAR(1000) NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  `deleted_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tasks_board` (`project_id`, `parent_id`, `status`, `sort_order`),
  KEY `idx_tasks_period` (`project_period_id`),
  KEY `idx_tasks_pic` (`pic_user_id`),
  KEY `idx_tasks_due` (`due_at`),
  KEY `idx_tasks_done` (`completed_at`),
  KEY `idx_tasks_publish` (`scheduled_publish_at`),
  KEY `idx_tasks_pub_status` (`publication_status`),
  CONSTRAINT `fk_tasks_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_project_period_id` FOREIGN KEY (`project_period_id`) REFERENCES `project_periods` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_parent_id` FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tasks_pic_user_id` FOREIGN KEY (`pic_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tasks_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_tasks_completed_by` FOREIGN KEY (`completed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_tasks_task_template_id` FOREIGN KEY (`task_template_id`) REFERENCES `task_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_assignees` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `assigned_by` BIGINT UNSIGNED NULL,
  `assigned_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_task_assignees` (`task_id`, `user_id`),
  KEY `idx_task_assignees_user` (`user_id`),
  CONSTRAINT `fk_task_assignees_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_assignees_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_task_assignees_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_submissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `submitted_by` BIGINT UNSIGNED NOT NULL,
  `submission_note` LONGTEXT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `review_note` TEXT NULL,
  `reviewed_by` BIGINT UNSIGNED NULL,
  `submitted_at` DATETIME NOT NULL,
  `reviewed_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_submissions_task_status` (`task_id`, `status`),
  KEY `idx_task_submissions_reviewer` (`reviewed_by`),
  CONSTRAINT `fk_task_submissions_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_submissions_submitted_by` FOREIGN KEY (`submitted_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_task_submissions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `task_submission_id` BIGINT UNSIGNED NULL,
  `uploaded_by` BIGINT UNSIGNED NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `storage_disk` VARCHAR(40) NULL,
  `storage_path` VARCHAR(1000) NULL,
  `external_url` VARCHAR(1500) NULL,
  `mime_type` VARCHAR(150) NULL,
  `file_size_bytes` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_attachments_task` (`task_id`),
  CONSTRAINT `fk_task_attachments_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_attachments_task_submission_id` FOREIGN KEY (`task_submission_id`) REFERENCES `task_submissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_task_attachments_uploaded_by` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_activities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `activity_type` VARCHAR(50) NOT NULL,
  `description` TEXT NULL,
  `old_value` VARCHAR(255) NULL,
  `new_value` VARCHAR(255) NULL,
  `metadata` JSON NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_activities_task_date` (`task_id`, `created_at`),
  KEY `idx_task_activities_user` (`user_id`),
  CONSTRAINT `fk_task_activities_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_activities_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `task_time_entries` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `task_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `work_date` DATE NOT NULL,
  `minutes_spent` INT UNSIGNED NOT NULL,
  `description` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_task_time_user_date` (`user_id`, `work_date`),
  KEY `idx_task_time_task` (`task_id`),
  CONSTRAINT `fk_task_time_entries_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_task_time_entries_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_activities` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NULL,
  `created_by` BIGINT UNSIGNED NOT NULL,
  `activity_type` VARCHAR(40) NOT NULL DEFAULT 'other',
  `title` VARCHAR(250) NOT NULL,
  `description` TEXT NULL,
  `status` VARCHAR(25) NOT NULL DEFAULT 'planned',
  `scheduled_at` DATETIME NULL,
  `occurred_at` DATETIME NULL,
  `duration_minutes` INT UNSIGNED NULL,
  `impact_type` VARCHAR(25) NOT NULL DEFAULT 'none',
  `impact_notes` TEXT NULL,
  `resolution_notes` TEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_project_activities_project_date` (`project_id`, `occurred_at`),
  KEY `idx_project_activities_schedule` (`scheduled_at`),
  KEY `idx_project_activities_impact` (`impact_type`),
  CONSTRAINT `fk_project_activities_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_activities_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_project_activities_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `project_activity_participants` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_activity_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_activity_participants` (`project_activity_id`, `user_id`),
  KEY `idx_activity_participants_user` (`user_id`),
  CONSTRAINT `fk_project_activity_participants_project_activity_id` FOREIGN KEY (`project_activity_id`) REFERENCES `project_activities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_project_activity_participants_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `schedule_change_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `project_id` BIGINT UNSIGNED NOT NULL,
  `task_id` BIGINT UNSIGNED NULL,
  `project_activity_id` BIGINT UNSIGNED NULL,
  `requested_by` BIGINT UNSIGNED NOT NULL,
  `decided_by` BIGINT UNSIGNED NULL,
  `previous_due_at` DATETIME NULL,
  `proposed_due_at` DATETIME NOT NULL,
  `reason` TEXT NOT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `decision_note` TEXT NULL,
  `requested_at` DATETIME NOT NULL,
  `decided_at` DATETIME NULL,
  `applied_at` DATETIME NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_schedule_requests_project_status` (`project_id`, `status`),
  KEY `idx_schedule_requests_task` (`task_id`),
  CONSTRAINT `fk_schedule_change_requests_project_id` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_schedule_change_requests_task_id` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schedule_change_requests_project_activity_id` FOREIGN KEY (`project_activity_id`) REFERENCES `project_activities` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_schedule_change_requests_requested_by` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_schedule_change_requests_decided_by` FOREIGN KEY (`decided_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(160) NOT NULL,
  `setting_value` JSON NULL,
  `description` VARCHAR(500) NULL,
  `updated_by` BIGINT UNSIGNED NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_system_settings_key` (`setting_key`),
  CONSTRAINT `fk_system_settings_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 02. DATA AWAL: ROLES, PERMISSIONS, DAN 9 MENU UTAMA SUPER ADMIN
-- Tidak membuat akun pengguna, tidak membuat password.
-- ============================================================================

INSERT INTO `roles` (`slug`, `name`, `description`, `is_system`, `created_at`, `updated_at`) VALUES
('super_admin', 'Super Admin', 'Akses seluruh modul dan pengaturan sistem', 1, NOW(), NOW()),
('manager', 'Manager', 'Monitoring dan pengelolaan pekerjaan sesuai kebijakan', 1, NOW(), NOW()),
('member', 'Member', 'Akses pekerjaan sesuai keanggotaan dan penugasan', 1, NOW(), NOW());


INSERT INTO `permissions` (`slug`, `name`, `module`, `created_at`, `updated_at`) VALUES

('dashboard.view', 'Lihat Dashboard', 'dashboard', NOW(), NOW()),
('projects.view', 'Lihat Project', 'projects', NOW(), NOW()),
('projects.create', 'Tambah Project', 'projects', NOW(), NOW()),
('projects.update', 'Edit Project', 'projects', NOW(), NOW()),
('projects.delete', 'Hapus Project', 'projects', NOW(), NOW()),
('projects.manage_team', 'Kelola Tim Project', 'projects', NOW(), NOW()),
('projects.approve_schedule', 'Setujui Perubahan Jadwal Project', 'projects', NOW(), NOW()),
('tasks.view', 'Lihat Task', 'tasks', NOW(), NOW()),
('tasks.create', 'Tambah Task', 'tasks', NOW(), NOW()),
('tasks.update', 'Edit Task', 'tasks', NOW(), NOW()),
('tasks.delete', 'Hapus Task', 'tasks', NOW(), NOW()),
('tasks.assign', 'Kelola Assignee', 'tasks', NOW(), NOW()),
('tasks.move_status', 'Ubah Status', 'tasks', NOW(), NOW()),
('tasks.submit', 'Kirim Hasil Pekerjaan', 'tasks', NOW(), NOW()),
('tasks.review', 'Review Task', 'tasks', NOW(), NOW()),
('tasks.approve_schedule', 'Setujui Perubahan Deadline Task', 'tasks', NOW(), NOW()),
('calendar.view', 'Lihat Kalender', 'calendar', NOW(), NOW()),
('activities.view', 'Lihat Aktivitas', 'activities', NOW(), NOW()),
('activities.create', 'Tambah Aktivitas', 'activities', NOW(), NOW()),
('activities.update', 'Edit Aktivitas', 'activities', NOW(), NOW()),
('activities.delete', 'Hapus Aktivitas', 'activities', NOW(), NOW()),
('templates.view', 'Lihat Template', 'templates', NOW(), NOW()),
('templates.manage', 'Kelola Template', 'templates', NOW(), NOW()),
('management.view', 'Lihat Management', 'management', NOW(), NOW()),
('management.export', 'Export Laporan', 'management', NOW(), NOW()),
('companies.view', 'Lihat Company', 'companies', NOW(), NOW()),
('companies.manage', 'Kelola Company', 'companies', NOW(), NOW()),
('clients.view', 'Lihat Client', 'clients', NOW(), NOW()),
('clients.manage', 'Kelola Client', 'clients', NOW(), NOW()),
('divisions.view', 'Lihat Divisi', 'divisions', NOW(), NOW()),
('divisions.manage', 'Kelola Divisi', 'divisions', NOW(), NOW()),
('positions.view', 'Lihat Jabatan', 'positions', NOW(), NOW()),
('positions.manage', 'Kelola Jabatan', 'positions', NOW(), NOW()),
('members.view', 'Lihat Anggota', 'members', NOW(), NOW()),
('members.manage', 'Kelola Anggota', 'members', NOW(), NOW()),
('settings.roles.view', 'Lihat Role & Permission', 'settings', NOW(), NOW()),
('settings.roles.manage', 'Kelola Role & Permission', 'settings', NOW(), NOW()),
('settings.menus.manage', 'Kelola Menu Dinamis', 'settings', NOW(), NOW()),
('settings.system.manage', 'Kelola Pengaturan Sistem', 'settings', NOW(), NOW());


-- Super Admin: seluruh permissions (juga di-backup Gate::before di Laravel).
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT r.id, p.id, NOW(), NOW() FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug = 'super_admin';


-- Role manager: tetap wajib validasi scope project/task di Policy Laravel.

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT r.id, p.id, NOW(), NOW() FROM `roles` r JOIN `permissions` p ON p.slug IN (

  'dashboard.view',
  'projects.view',
  'projects.create',
  'projects.update',
  'projects.manage_team',
  'projects.approve_schedule',
  'tasks.view',
  'tasks.create',
  'tasks.update',
  'tasks.assign',
  'tasks.move_status',
  'tasks.submit',
  'tasks.review',
  'tasks.approve_schedule',
  'calendar.view',
  'activities.view',
  'activities.create',
  'activities.update',
  'templates.view',
  'templates.manage',
  'management.view',
  'management.export',
  'companies.view',
  'clients.view',
  'divisions.view',
  'positions.view',
  'members.view'

) WHERE r.slug = 'manager';


-- Role member: tetap wajib validasi scope project/task di Policy Laravel.

INSERT INTO `role_permissions` (`role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT r.id, p.id, NOW(), NOW() FROM `roles` r JOIN `permissions` p ON p.slug IN (

  'dashboard.view',
  'projects.view',
  'tasks.view',
  'tasks.create',
  'tasks.update',
  'tasks.move_status',
  'tasks.submit',
  'calendar.view',
  'activities.view',
  'activities.create',
  'templates.view'

) WHERE r.slug = 'member';


INSERT INTO `menu_items` (`slug`, `title`, `icon`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES

('dashboard', 'Dashboard', 'layout-dashboard', 10, 1, NOW(), NOW()),
('projects', 'Projects', 'folder-kanban', 20, 1, NOW(), NOW()),
('tasks', 'Tasks', 'list-todo', 30, 1, NOW(), NOW()),
('calendar', 'Calendar', 'calendar-days', 40, 1, NOW(), NOW()),
('activities', 'Activities', 'activity', 50, 1, NOW(), NOW()),
('templates', 'Templates', 'copy', 60, 1, NOW(), NOW()),
('management', 'Management', 'chart-no-axes-combined', 70, 1, NOW(), NOW()),
('master-data', 'Master Data', 'database', 80, 1, NOW(), NOW()),
('system-settings', 'System Settings', 'settings-2', 90, 1, NOW(), NOW());


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'dashboard-overview', 'Ringkasan Sistem', 'dashboard.index', p.id, 10, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'dashboard.view'
WHERE m.slug = 'dashboard';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'projects-list', 'Daftar Project', 'projects.index', p.id, 20, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'projects.view'
WHERE m.slug = 'projects';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'tasks-all', 'Semua Task', 'tasks.index', p.id, 30, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'tasks.view'
WHERE m.slug = 'tasks';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'tasks-mine', 'Task Saya', 'tasks.mine', p.id, 40, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'tasks.view'
WHERE m.slug = 'tasks';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'tasks-review', 'Menunggu Review', 'tasks.review', p.id, 50, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'tasks.review'
WHERE m.slug = 'tasks';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'calendar-overview', 'Kalender Pekerjaan & Aktivitas', 'calendar.index', p.id, 60, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'calendar.view'
WHERE m.slug = 'calendar';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'activities-all', 'Daftar Aktivitas', 'activities.index', p.id, 70, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'activities.view'
WHERE m.slug = 'activities';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'activities-meetings', 'Rapat & Kunjungan', 'activities.meetings', p.id, 80, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'activities.view'
WHERE m.slug = 'activities';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'activities-impacts', 'Hambatan & Perubahan', 'activities.impacts', p.id, 90, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'activities.view'
WHERE m.slug = 'activities';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'templates-projects', 'Template Project', 'templates.projects.index', p.id, 100, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'templates.view'
WHERE m.slug = 'templates';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'templates-tasks', 'Template Task', 'templates.tasks.index', p.id, 110, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'templates.view'
WHERE m.slug = 'templates';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'management-overview', 'Management Overview', 'management.index', p.id, 120, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'management.view'
WHERE m.slug = 'management';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'management-projects', 'Monitoring Project', 'management.projects', p.id, 130, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'management.view'
WHERE m.slug = 'management';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'management-teams', 'Monitoring Divisi & Anggota', 'management.teams', p.id, 140, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'management.view'
WHERE m.slug = 'management';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'management-reports', 'Laporan Operasional', 'management.reports', p.id, 150, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'management.view'
WHERE m.slug = 'management';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'master-companies', 'Company', 'companies.index', p.id, 160, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'companies.view'
WHERE m.slug = 'master-data';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'master-clients', 'Client', 'clients.index', p.id, 170, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'clients.view'
WHERE m.slug = 'master-data';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'master-divisions', 'Divisi', 'divisions.index', p.id, 180, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'divisions.view'
WHERE m.slug = 'master-data';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'master-positions', 'Jabatan', 'positions.index', p.id, 190, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'positions.view'
WHERE m.slug = 'master-data';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'master-members', 'Anggota Tim', 'members.index', p.id, 200, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'members.view'
WHERE m.slug = 'master-data';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'settings-roles', 'Roles & Permissions', 'settings.roles.index', p.id, 210, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'settings.roles.view'
WHERE m.slug = 'system-settings';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'settings-menus', 'Menu Management', 'settings.menus.index', p.id, 220, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'settings.menus.manage'
WHERE m.slug = 'system-settings';


INSERT INTO `menu_items` (`parent_id`, `slug`, `title`, `route_name`, `permission_id`, `sort_order`, `is_active`, `created_at`, `updated_at`)
SELECT m.id, 'settings-general', 'Pengaturan Sistem', 'settings.general', p.id, 230, 1, NOW(), NOW()
FROM `menu_items` m JOIN `permissions` p ON p.slug = 'settings.system.manage'
WHERE m.slug = 'system-settings';


-- ============================================================================
-- 03. CATATAN LARAVEL & ATURAN BISNIS
-- ============================================================================
-- A. AKUN SUPER ADMIN
--    Buat user menggunakan registrasi/Seeder Laravel (password via Hash::make).
--    Contoh SETELAH user tersedia, ganti email placeholder:
--    INSERT INTO user_roles (user_id, role_id, created_at, updated_at)
--    SELECT u.id, r.id, NOW(), NOW() FROM users u JOIN roles r
--    WHERE u.email = 'admin@example.com' AND r.slug = 'super_admin';
--
-- B. LARAVEL ELOQUENT
--    - Semua ID BIGINT UNSIGNED cocok dengan belongsTo/hasMany/belongsToMany.
--    - Eloquent timestamps: created_at/updated_at.
--    - Untuk deleted_at, tambahkan trait SoftDeletes ke model terkait.
--    - RBAC: pemeriksaan permission di backend via Gate/Policy/middleware.
--    - Menu dinamis hanya navigasi; bukan pengganti keamanan backend.
--    - menu_items.route_name wajib mengarah ke route Laravel yang sudah dibuat.
--    - Menu parent tampil jika ada minimal satu submenu berizin.
--
-- C. PROJECT & TASK
--    - projects.company_id wajib; projects.client_id opsional untuk project internal.
--    - PIC Project: projects.pic_user_id; PIC Task: tasks.pic_user_id.
--    - PIC harus di project_members, dan PIC Task harus anggota project.
--    - task_assignees.user_id harus ada di project_members untuk task.project_id.
--    - Project/Task boleh punya banyak anggota/pelaksana.
--    - project_member_divisions hanya menghubungkan anggota ke divisi
--      yang SUDAH dipilih di project_divisions untuk PROJECT YANG SAMA.
--    - Jumlah divisi = COUNT(DISTINCT project_divisions.division_id).
--    - Jumlah orang = COUNT(DISTINCT project_members.user_id).
--    - Subtask = tasks.parent_id IS NOT NULL, maksimal 1 tingkat (validasi app).
--    - Parent subtask harus dari project yang sama; periode juga harus sama.
--    - Task utama menggunakan parent_id IS NULL untuk progress project.
--    - Saat status done: set completed_at dan completed_by; saat dibuka lagi:
--      set completed_at = NULL, completed_by = NULL.
--    - Target asli baseline_due_at jangan diubah setelah mulai project/task.
--    - Status task: backlog, in_progress, review, done.
--    - Status project: planned, active, on_hold, completed, cancelled.
--    - project_type: one_time, recurring, ongoing.
--    - Recurring/ongoing: buat period manual (mis. 2026-11), lalu generate
--      tasks dari template. Hindari job otomatis pada versi awal trainee.
--    - Validasi sebelum close project/task: status review/subtask/approval.
--
-- D. ACTIVITY, KALENDER, DAN DEADLINE
--    - Tanggal selesai aktual memakai tasks.completed_at (bukan updated_at).
--    - Deadline aktif memakai due_at; terlambat jika due_at < NOW() dan
--      status bukan done. Flag terlambat dihitung di query, bukan kolom tetap.
--    - Jadwal publikasi pada scheduled_publish_at, tanggal publish di published_at;
--      publication_status terpisah dari status pengerjaan task.
--    - project_activities untuk rapat, kunjungan, revisi, hambatan.
--    - project_activity_participants mencatat orang yang BENAR-BENAR hadir.
--    - schedule_change_requests: pending -> approved/rejected oleh PIC/Manager;
--      hanya approved mengubah due_at secara transaction + mencatat applied_at.
--    - Penambahan rapat/kegiatan TIDAK otomatis mengubah deadline.
--    - task_time_entries untuk waktu kerja aktual; project_activities.duration_minutes
--      untuk durasi aktivitas. Jangan menjumlahkan durasi yang duplikat.
--
-- E. CONTOH QUERY MANAJEMEN
--    -- Jumlah anggota unik per project:
--    SELECT p.id, p.name, COUNT(DISTINCT pm.user_id) AS total_members
--    FROM projects p LEFT JOIN project_members pm
--      ON pm.project_id = p.id AND pm.is_active = 1
--    GROUP BY p.id, p.name;
--
--    -- Task terlambat (utama dan subtask):
--    SELECT id, project_id, title, due_at FROM tasks
--    WHERE status <> 'done' AND due_at < NOW() AND deleted_at IS NULL;
--
--    -- Activity selesai di kalender:
--    SELECT id, title, completed_at FROM tasks
--    WHERE completed_at IS NOT NULL AND deleted_at IS NULL;
--
-- F. BELUM DISEDIAKAN DI SQL INI
--    - Route, Controllers, Models, Middleware, FormRequest, Policy, Views.
--    - Data demo, akun pengguna, email, password, upload fisik file.
--    - Tabel bawaan framework yang dibuat oleh Laravel migrations.
-- ============================================================================
