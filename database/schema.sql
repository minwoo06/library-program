-- Fresh installation only: tables and public category names.
-- No users, password hashes, contact information or rental history.
SET NAMES utf8mb4;

CREATE TABLE `categories` (
  `category_id` INT NOT NULL AUTO_INCREMENT,
  `category_name` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `users` (
  `user_id`    INT NOT NULL AUTO_INCREMENT,
  `username`   VARCHAR(50)  NOT NULL UNIQUE,
  `password`   VARCHAR(255) NOT NULL,
  `name`       VARCHAR(50)  NOT NULL,
  `email`      VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(20)  DEFAULT NULL,
  `role`       ENUM('admin','member') NOT NULL DEFAULT 'member',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `books` (
  `book_id`     INT NOT NULL AUTO_INCREMENT,
  `title`       VARCHAR(200) NOT NULL,
  `author`      VARCHAR(100) NOT NULL,
  `publisher`   VARCHAR(100) NOT NULL,
  `category_id` INT NOT NULL,
  `description` TEXT,
  `status`      ENUM('available','rented') NOT NULL DEFAULT 'available',
  `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`book_id`),
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `rentals` (
  `rental_id`   INT NOT NULL AUTO_INCREMENT,
  `user_id`     INT NOT NULL,
  `book_id`     INT NOT NULL,
  `rent_date`   DATE NOT NULL,
  `due_date`    DATE NOT NULL,
  `return_date` DATE DEFAULT NULL,
  `period_days` INT NOT NULL DEFAULT 7,
  `status`      ENUM('active','returned') NOT NULL DEFAULT 'active',
  PRIMARY KEY (`rental_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`user_id`),
  FOREIGN KEY (`book_id`) REFERENCES `books`(`book_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` VALUES
(1, '소설'),
(2, '과학'),
(3, '역사'),
(4, '자기계발'),
(5, '컴퓨터/IT'),
(6, '경제/경영'),
(7, '사회/정치'),
(8, '예술/문화'),
(9, '건강/의학'),
(10, '여행'),
(11, '요리/생활'),
(12, '언어/외국어'),
(13, '만화/그래픽노블'),
(14, '시/에세이'),
(15, '종교/철학'),
(16, '청소년'),
(17, '어린이'),
(18, '잡지'),
(19, '인문학'),
(20, '스포츠/레저');
