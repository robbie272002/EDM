-- Table for tracking product locations and their stock levels
CREATE TABLE IF NOT EXISTS product_locations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    product_id INT NOT NULL,
    location VARCHAR(50) NOT NULL,
    stock INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_product_location (product_id, location)
);

-- Table for tracking inventory changes
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

-- Add indexes for better performance
CREATE INDEX idx_inventory_logs_product ON inventory_logs(product_id);
CREATE INDEX idx_inventory_logs_type ON inventory_logs(type);
CREATE INDEX idx_inventory_logs_created ON inventory_logs(created_at);
CREATE INDEX idx_product_locations_product ON product_locations(product_id);
CREATE INDEX idx_product_locations_location ON product_locations(location); 