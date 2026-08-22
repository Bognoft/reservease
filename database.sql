CREATE DATABASE IF NOT EXISTS reservease CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE reservease;

CREATE TABLE admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE restaurant_tables (
  id INT UNSIGNED PRIMARY KEY,
  name VARCHAR(20) NOT NULL,
  seats TINYINT UNSIGNED NOT NULL,
  x SMALLINT NOT NULL,
  y SMALLINT NOT NULL,
  width SMALLINT NOT NULL,
  height SMALLINT NOT NULL,
  status ENUM('available','reserved','occupied') NOT NULL DEFAULT 'available'
);

CREATE TABLE reservations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(20) NOT NULL UNIQUE,
  table_id INT UNSIGNED NOT NULL,
  guest_name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  party_size TINYINT UNSIGNED NOT NULL,
  reservation_date DATE NOT NULL,
  time_slot VARCHAR(60) NOT NULL,
  deposit DECIMAL(10,2) NOT NULL DEFAULT 200,
  payment_method VARCHAR(30) NOT NULL,
  status ENUM('upcoming','arrived','completed','cancelled') NOT NULL DEFAULT 'upcoming',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_res_table FOREIGN KEY (table_id) REFERENCES restaurant_tables(id)
);

CREATE TABLE waitlist (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  party_size TINYINT UNSIGNED NOT NULL,
  reservation_date DATE NOT NULL,
  time_slot VARCHAR(60) NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  status ENUM('waiting','converted','cancelled') NOT NULL DEFAULT 'waiting',
  seated_table_id INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_wait_table FOREIGN KEY (seated_table_id) REFERENCES restaurant_tables(id)
);

INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$12$UtAziGiBLfm2Ws7NYBxdJu6FEx3m0sotBTKp/Jj19zuYYIy17fNxe');

INSERT INTO restaurant_tables (id, name, seats, x, y, width, height) VALUES
(1,'T1',4,40,150,120,80),(2,'T2',4,40,300,120,80),(3,'T3',8,250,170,130,170),
(4,'T4',8,520,170,130,170),(5,'T5',6,740,150,120,80),(6,'T6',6,740,300,120,80),
(7,'T7',4,210,410,120,75),(8,'T8',4,390,410,120,75),(9,'T9',4,570,410,120,75),(10,'T10',4,750,410,120,75);
