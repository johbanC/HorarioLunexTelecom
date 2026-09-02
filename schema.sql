-- Horario Lunex — esquema de base de datos (MySQL / MariaDB)
-- Importa este archivo completo en phpMyAdmin o HeidiSQL (ambos vienen con Laragon),
-- o por línea de comandos: mysql -u root < schema.sql

CREATE DATABASE IF NOT EXISTS horario_lunex
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE horario_lunex;

CREATE TABLE IF NOT EXISTS employees (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS shifts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employee_id INT NOT NULL,
  work_date DATE NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  break_min INT NOT NULL DEFAULT 0,
  break_mode ENUM('auto','manual') NOT NULL DEFAULT 'auto',
  cobro ENUM('anticipado','posterior') NOT NULL DEFAULT 'anticipado',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_shifts_employee FOREIGN KEY (employee_id) REFERENCES employees(id) ON DELETE CASCADE,
  INDEX idx_work_date (work_date),
  INDEX idx_employee (employee_id)
) ENGINE=InnoDB;

-- Empleados iniciales (puedes editarlos, agregar o quitar desde la propia app)
INSERT INTO employees (name, sort_order) VALUES
  ('Karelys', 0),
  ('Juana', 1),
  ('Valentina', 2),
  ('Juan Manuel', 3),
  ('Juanita Restrepo', 4);
