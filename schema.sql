SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS termekkatalogus
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_hungarian_ci;

USE termekkatalogus;

CREATE TABLE IF NOT EXISTS products (
    id          INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    name        VARCHAR(150)  NOT NULL,
    price       DECIMAL(10,2) NOT NULL,
    description TEXT          NOT NULL,
    category    ENUM('laptop', 'telefon', 'monitor', 'ssd', 'tartozék') NOT NULL,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CHECK (price >= 0)
);