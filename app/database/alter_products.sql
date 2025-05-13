-- Add status column to products table
ALTER TABLE products 
ADD COLUMN status ENUM('active', 'inactive') NOT NULL DEFAULT 'active' 
AFTER image; 