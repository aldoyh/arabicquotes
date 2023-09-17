-- The Arabic quotes databse schema

DROP TABLE IF EXISTS `quotes`;
DROP TABLE IF EXISTS `quotes_review`;

CREATE TABLE `quotes` (
    `id` int NOT NULL AUTO_INCREMENT,
    `head` mediumtext,
    `quotes` text,
    `author` mediumtext,
    `quotescol` varchar(45) DEFAULT NULL,
    `hits` int NOT NULL DEFAULT '0',
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `category` varchar(45) NOT NULL DEFAULT 'General',
    PRIMARY KEY (`id`),
    UNIQUE KEY `id_UNIQUE` (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;

CREATE TABLE `quotes_review` (
    `id` int NOT NULL AUTO_INCREMENT,
    `quote_id` int NOT NULL,
    `review` text,
    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `id_UNIQUE` (`id`),
    KEY `fk_quotes_review_quotes_idx` (`quote_id`),
    CONSTRAINT `fk_quotes_review_quotes` FOREIGN KEY (`quote_id`) REFERENCES `quotes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_0900_ai_ci;