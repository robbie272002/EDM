CREATE TABLE IF NOT EXISTS inventory_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    type ENUM('restock', 'adjustment', 'sale') NOT NULL,
    quantity INT NOT NULL,
    reason VARCHAR(50),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

CREATE INDEX idx_inventory_logs_product ON inventory_logs(product_id);
CREATE INDEX idx_inventory_logs_type ON inventory_logs(type);
CREATE INDEX idx_inventory_logs_created ON inventory_logs(created_at); 