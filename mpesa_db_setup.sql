-- Create the mpesa_db database if it doesn't exist
CREATE DATABASE IF NOT EXISTS mpesa_db;
USE mpesa_db;

-- Create transactions table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_name VARCHAR(100) NOT NULL,
    phone_number VARCHAR(15) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    transaction_id VARCHAR(50) NOT NULL,
    checkout_request_id VARCHAR(50) NULL,
    mpesa_receipt VARCHAR(50) NULL,
    status ENUM('pending', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    notes TEXT NULL,
    charity_id INT NULL,
    created_at DATETIME NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create mpesa_callbacks table for logging callback data
CREATE TABLE IF NOT EXISTS mpesa_callbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    checkout_request_id VARCHAR(50) NULL,
    result_code INT NULL,
    result_desc TEXT NULL,
    mpesa_receipt VARCHAR(50) NULL,
    transaction_date VARCHAR(50) NULL,
    phone_number VARCHAR(15) NULL,
    amount DECIMAL(10, 2) NULL,
    raw_callback TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample data (optional)
INSERT INTO transactions (donor_name, phone_number, amount, transaction_id, status, created_at)
VALUES 
('John Doe', '254702133372', 500.00, 'MPESA1234567', 'pending', NOW()),
('Jane Smith', '254712345678', 1000.00, 'MPESA7654321', 'completed', NOW());

