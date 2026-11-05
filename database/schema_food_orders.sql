-- Food ordering tables for the student module.
-- Assumption: students.id is an INT primary key. If your existing column is
-- INT UNSIGNED (or another integer type), change food_orders.student_id to the
-- exact same type before importing this file so the foreign key is compatible.

CREATE TABLE IF NOT EXISTS food_items (
    id INT NOT NULL AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10, 2) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT chk_food_items_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS food_orders (
    id BIGINT NOT NULL AUTO_INCREMENT,
    student_id INT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_food_orders_student_created (student_id, created_at),
    CONSTRAINT fk_food_orders_student
        FOREIGN KEY (student_id) REFERENCES students (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_food_orders_total CHECK (total_amount >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS food_order_items (
    id BIGINT NOT NULL AUTO_INCREMENT,
    order_id BIGINT NOT NULL,
    food_item_id INT NOT NULL,
    item_name VARCHAR(120) NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    quantity INT NOT NULL,
    line_total DECIMAL(10, 2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_food_order_items_order (order_id),
    KEY idx_food_order_items_food_item (food_item_id),
    CONSTRAINT fk_food_order_items_order
        FOREIGN KEY (order_id) REFERENCES food_orders (id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_food_order_items_food_item
        FOREIGN KEY (food_item_id) REFERENCES food_items (id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT chk_food_order_items_price CHECK (unit_price >= 0),
    CONSTRAINT chk_food_order_items_quantity CHECK (quantity > 0),
    CONSTRAINT chk_food_order_items_line_total CHECK (line_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Example menu rows so the ordering page can be tested after installation.
-- Replace these with your school's real menu; no menu-management interface is added.
INSERT INTO food_items (name, description, price) VALUES
    ('Sample sandwich', 'Replace with a real menu item', 3.00),
    ('Sample fruit cup', 'Replace with a real menu item', 1.50)
ON DUPLICATE KEY UPDATE name = VALUES(name);
