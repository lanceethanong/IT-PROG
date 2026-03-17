CREATE DATABASE IF NOT EXISTS lab_res_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE lab_res_db;

CREATE TABLE IF NOT EXISTS users (
  id VARCHAR(24) PRIMARY KEY,
  email VARCHAR(191) NOT NULL UNIQUE,
  username VARCHAR(191) NOT NULL UNIQUE,
  description TEXT NULL,
  remember TINYINT(1) NOT NULL DEFAULT 0,
  password VARCHAR(255) NOT NULL,
  picture VARCHAR(255) NOT NULL DEFAULT 'picture.jpg',
  role ENUM('Student', 'Lab Technician', 'Admin') NOT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS labs (
  id VARCHAR(24) PRIMARY KEY,
  class_name VARCHAR(191) NOT NULL,
  number INT NOT NULL UNIQUE,
  created_at DATETIME NULL,
  updated_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reservations (
  id VARCHAR(24) PRIMARY KEY,
  time_start VARCHAR(20) NOT NULL,
  time_end VARCHAR(20) NOT NULL,
  user_id VARCHAR(24) NOT NULL,
  lab_id VARCHAR(24) NOT NULL,
  date DATE NOT NULL,
  anonymity TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('Scheduled', 'Cancelled', 'In Progress', 'Completed') NOT NULL DEFAULT 'Scheduled',
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_res_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_res_lab FOREIGN KEY (lab_id) REFERENCES labs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS seat_lists (
  id VARCHAR(24) PRIMARY KEY,
  reservation_id VARCHAR(24) NOT NULL,
  row_num INT NOT NULL,
  col_num INT NOT NULL,
  created_at DATETIME NULL,
  updated_at DATETIME NULL,
  CONSTRAINT fk_seat_res FOREIGN KEY (reservation_id) REFERENCES reservations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS error_log (
  id VARCHAR(24) PRIMARY KEY,
  message TEXT NOT NULL,
  stack LONGTEXT NULL,
  source VARCHAR(255) NULL,
  timestamp DATETIME NULL,
  user VARCHAR(191) NULL
) ENGINE=InnoDB;
