CREATE DATABASE IF NOT EXISTS siged_kennedy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE siged_kennedy;

CREATE TABLE IF NOT EXISTS users (
 id INT AUTO_INCREMENT PRIMARY KEY,
 full_name VARCHAR(120) NOT NULL,
 username VARCHAR(60) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 role ENUM('admin','docente') NOT NULL DEFAULT 'docente',
 active TINYINT(1) NOT NULL DEFAULT 1,
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
 id INT AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(80) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS materials (
 id INT AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 name VARCHAR(120) NOT NULL,
 category_id INT NULL,
 brand VARCHAR(80) NULL,
 total_qty INT NOT NULL DEFAULT 0,
 available_qty INT NOT NULL DEFAULT 0,
 condition_status ENUM('Bueno','Regular','Dañado/Baja') NOT NULL DEFAULT 'Bueno',
 created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS loans (
 id INT AUTO_INCREMENT PRIMARY KEY,
 teacher_id INT NOT NULL,
 material_id INT NOT NULL,
 quantity INT NOT NULL,
 loan_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 expected_return DATETIME NULL,
 returned_at DATETIME NULL,
 return_condition ENUM('Óptimo','Con desgaste','Inservible/Dañado') NULL,
 observations TEXT NULL,
 status ENUM('Activo','Concluido','Con retraso') NOT NULL DEFAULT 'Activo',
 registered_by INT NOT NULL,
 FOREIGN KEY (teacher_id) REFERENCES users(id),
 FOREIGN KEY (material_id) REFERENCES materials(id),
 FOREIGN KEY (registered_by) REFERENCES users(id)
);

INSERT IGNORE INTO categories(name) VALUES ('Fútbol'),('Básquet'),('Voleibol'),('Atletismo'),('Otros');

-- Cuenta inicial para pruebas locales: usuario admin / contraseña password.
INSERT IGNORE INTO users(full_name, username, password_hash, role)
VALUES ('Encargado de Depósito', 'admin', '$2y$12$L5Yygk0NTgEsxZ8b6PLv9ulyQMPTxhaFILLMYeSpz.Mk/OyWyOhQK', 'admin');
