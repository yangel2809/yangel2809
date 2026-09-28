-- Generado con: php artisan deploy:sql --all
-- Importar en phpMyAdmin con la base de datos seleccionada.
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Tabla de control de migraciones
create table `migrations` (`id` int unsigned not null auto_increment primary key, `migration` varchar(191) not null, `batch` int not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

SET @batch := (SELECT COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`);

-- 0001_01_01_000000_create_users_table
create table `users` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(191) not null, `email` varchar(191) not null, `email_verified_at` timestamp null, `password` varchar(191) not null, `remember_token` varchar(100) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `users` add unique `users_email_unique`(`email`);
create table `password_reset_tokens` (`email` varchar(191) not null, `token` varchar(191) not null, `created_at` timestamp null, primary key (`email`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `sessions` (`id` varchar(191) not null, `user_id` bigint unsigned null, `ip_address` varchar(45) null, `user_agent` text null, `payload` longtext not null, `last_activity` int not null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `sessions` add index `sessions_user_id_index`(`user_id`);
alter table `sessions` add index `sessions_last_activity_index`(`last_activity`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000000_create_users_table', @batch);

-- 0001_01_01_000001_create_cache_table
create table `cache` (`key` varchar(191) not null, `value` mediumtext not null, `expiration` int not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `cache_locks` (`key` varchar(191) not null, `owner` varchar(191) not null, `expiration` int not null, primary key (`key`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000001_create_cache_table', @batch);

-- 0001_01_01_000002_create_jobs_table
create table `jobs` (`id` bigint unsigned not null auto_increment primary key, `queue` varchar(191) not null, `payload` longtext not null, `attempts` tinyint unsigned not null, `reserved_at` int unsigned null, `available_at` int unsigned not null, `created_at` int unsigned not null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `jobs` add index `jobs_queue_index`(`queue`);
create table `job_batches` (`id` varchar(191) not null, `name` varchar(191) not null, `total_jobs` int not null, `pending_jobs` int not null, `failed_jobs` int not null, `failed_job_ids` longtext not null, `options` mediumtext null, `cancelled_at` int null, `created_at` int not null, `finished_at` int null, primary key (`id`)) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
create table `failed_jobs` (`id` bigint unsigned not null auto_increment primary key, `uuid` varchar(191) not null, `connection` text not null, `queue` text not null, `payload` longtext not null, `exception` longtext not null, `failed_at` timestamp not null default CURRENT_TIMESTAMP) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `failed_jobs` add unique `failed_jobs_uuid_unique`(`uuid`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('0001_01_01_000002_create_jobs_table', @batch);

-- 2026_09_27_000001_create_habits_table
create table `habits` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `name` varchar(100) not null, `frequency_type` enum('daily', 'weekly') not null default 'daily', `weekly_target` tinyint unsigned null, `color` varchar(7) not null default '#10b981', `start_date` date not null, `sort_order` smallint unsigned not null default '0', `archived_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `habits` add constraint `habits_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `habits` add index `habits_user_id_archived_at_index`(`user_id`, `archived_at`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_27_000001_create_habits_table', @batch);

-- 2026_09_27_000002_create_habit_logs_table
create table `habit_logs` (`id` bigint unsigned not null auto_increment primary key, `habit_id` bigint unsigned not null, `date` date not null, `completed` tinyint(1) not null default '0', `note` varchar(500) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `habit_logs` add constraint `habit_logs_habit_id_foreign` foreign key (`habit_id`) references `habits` (`id`) on delete cascade;
alter table `habit_logs` add unique `habit_logs_habit_id_date_unique`(`habit_id`, `date`);
alter table `habit_logs` add index `habit_logs_date_index`(`date`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_27_000002_create_habit_logs_table', @batch);

-- 2026_09_27_000003_create_daily_priorities_table
create table `daily_priorities` (`id` bigint unsigned not null auto_increment primary key, `user_id` bigint unsigned not null, `date` date not null, `position` tinyint unsigned not null, `text` varchar(200) not null, `completed` tinyint(1) null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `daily_priorities` add constraint `daily_priorities_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `daily_priorities` add unique `daily_priorities_user_id_date_position_unique`(`user_id`, `date`, `position`);
INSERT INTO `migrations` (`migration`, `batch`) VALUES ('2026_09_27_000003_create_daily_priorities_table', @batch);

SET FOREIGN_KEY_CHECKS = 1;
