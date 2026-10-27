-- Importar una sola vez desde phpMyAdmin. No elimina datos existentes.
CREATE DATABASE IF NOT EXISTS halloween CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE halloween;

CREATE TABLE disfraces (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL,
  descripcion TEXT NOT NULL,
  votos INT NOT NULL DEFAULT 0,
  foto VARCHAR(20) NOT NULL DEFAULT '',
  foto_blob MEDIUMBLOB NOT NULL,
  eliminado INT NOT NULL DEFAULT 0,
  CONSTRAINT chk_votos CHECK (votos >= 0)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE,
  clave TEXT NOT NULL,
  rol ENUM('usuario','admin') NOT NULL DEFAULT 'usuario'
) ENGINE=InnoDB;

CREATE TABLE votos (
  id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_disfraz INT NOT NULL,
  UNIQUE KEY usuario_disfraz (id_usuario, id_disfraz),
  FOREIGN KEY (id_usuario) REFERENCES usuarios(id),
  FOREIGN KEY (id_disfraz) REFERENCES disfraces(id)
) ENGINE=InnoDB;

INSERT INTO disfraces (nombre, descripcion, foto_blob) VALUES
('Bruja de medianoche', 'Sombrero puntiagudo, una escoba y un hechizo para conquistar la noche.', ''),
('Vampiro de gala', 'Elegancia eterna, capa de terciopelo y una sonrisa con colmillos.', ''),
('Fantasma del recreo', 'Aparece cuando suena el timbre. Una sábana clásica con mucha personalidad.', '');
