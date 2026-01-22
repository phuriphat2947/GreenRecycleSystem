-- Database Creation
CREATE DATABASE IF NOT EXISTS greendigital_extended CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE greendigital_extended;

-- 1. User Table (Users, Admins, Drivers)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL COMMENT 'Username for login',
    email VARCHAR(100) NOT NULL UNIQUE COMMENT 'Email address (Login/Contact)',
    password VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    level ENUM('Seedling', 'Guardian', 'Titan') DEFAULT 'Seedling' COMMENT 'User eco-level',
    role ENUM('user', 'admin', 'driver') DEFAULT 'user' COMMENT 'System role',
    status ENUM('active', 'suspended', 'banned') DEFAULT 'active' COMMENT 'Account status',
    profile_image VARCHAR(255) DEFAULT 'default_avatar.png' COMMENT 'Profile picture filename',
    total_recycled_weight DECIMAL(12, 2) DEFAULT 0.00 COMMENT 'Cumulative weight recycled',
    membership_level ENUM('seedling', 'guardian', 'titan') DEFAULT 'seedling' COMMENT 'Gamification Level',
    
    -- Contact & Basic Info
    address TEXT NULL COMMENT 'Physical Address for pickup',
    
    -- KYC & Identity Verification
    id_card_number VARCHAR(20) NULL COMMENT 'National ID Card Number 13 digits',
    id_card_image VARCHAR(255) NULL COMMENT 'Filename of ID Card photo',
    kyc_status ENUM('unverified', 'pending', 'verified', 'rejected') DEFAULT 'unverified' COMMENT 'Identity verification status',
    kyc_reject_reason TEXT NULL COMMENT 'Reason for rejection if any',
    
    -- Financial Info (For Withdrawals)
    bank_name VARCHAR(100) NULL COMMENT 'Bank Name e.g. KBANK',
    bank_account VARCHAR(50) NULL COMMENT 'Account Number',
    bank_account_name VARCHAR(100) NULL COMMENT 'Account Name matching ID Card',
    
    -- Security
    remember_token VARCHAR(64) NULL COMMENT 'Cookie token for Remember Me',
    reset_token VARCHAR(255) NULL COMMENT 'Password reset token',
    reset_expiry DATETIME NULL COMMENT 'Token expiration time',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores all user accounts';

-- 2. Waste Types (Master Data)
CREATE TABLE IF NOT EXISTS waste_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'Name of waste type (e.g. Plastic Bottle)',
    description TEXT NULL COMMENT 'Description or recycling instructions',
    price_per_kg DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Standard/Base Price',
    pickup_price_per_kg DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Pickup Service Price (Lower)',
    price_walkin DECIMAL(10, 2) NOT NULL DEFAULT 0.00 COMMENT 'Walk-in Price (Premium)',
    image VARCHAR(255) NULL COMMENT 'Image of the waste type',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master table for waste categories';

-- 3. Pickup Orders (Transactions Header)
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'Customer ID',
    driver_id INT NULL COMMENT 'Assigned Driver ID',
    
    order_type ENUM('pickup', 'walkin') DEFAULT 'pickup' COMMENT 'Service Type',
    status ENUM('pending', 'accepted', 'completed', 'cancelled') DEFAULT 'pending' COMMENT 'Order status',
    
    -- Logistics
    pickup_date DATE NULL COMMENT 'Scheduled pickup date',
    pickup_time TIME NULL COMMENT 'Scheduled pickup time',
    pickup_address TEXT NULL COMMENT 'Address for this specific order',
    request_image VARCHAR(255) NULL COMMENT 'Photo of waste for evaluation',
    latitude DECIMAL(10, 8) NULL COMMENT 'GPS Latitude',
    longitude DECIMAL(11, 8) NULL COMMENT 'GPS Longitude',
    
    -- Financials & Verification
    payment_method ENUM('cash', 'transfer') DEFAULT 'cash' COMMENT 'Preferred payment',
    total_weight DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Total actual weight in KG',
    total_amount DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Total payout amount',
    driver_cash_out DECIMAL(10, 2) DEFAULT 0.00 COMMENT 'Cash paid by driver',
    payment_proof VARCHAR(255) NULL COMMENT 'Slip image if transfer',
    is_verified_by_user TINYINT(1) DEFAULT 0 COMMENT 'Customer confirmed receipt',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Main transaction/order table';

-- 4. Order Items (Transactions Detail)
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    waste_type_id INT NOT NULL,
    
    weight DECIMAL(10, 2) NOT NULL COMMENT 'Estimated Weight',
    actual_weight DECIMAL(10, 2) NULL COMMENT 'Measured Weight by Driver',
    price_at_time DECIMAL(10, 2) NOT NULL COMMENT 'Price per KG used for calculation',
    subtotal DECIMAL(10, 2) NOT NULL COMMENT 'Calculated subtotal',
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (waste_type_id) REFERENCES waste_types(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Line items for each order';

-- 5. Wallet Transactions (History)
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NULL COMMENT 'Related order ID (if applicable)',
    
    type ENUM('deposit', 'withdraw', 'income', 'refund') NOT NULL COMMENT 'Transaction type',
    amount DECIMAL(10, 2) NOT NULL COMMENT 'Transaction amount',
    balance_after DECIMAL(10, 2) NOT NULL COMMENT 'Balance after transaction',
    description VARCHAR(255) NULL COMMENT 'Short description',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Financial transaction history';

-- 6. Withdrawals (New System)
CREATE TABLE IF NOT EXISTS withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL COMMENT 'Withdrawal Amount',
    bank_name VARCHAR(100) NOT NULL,
    bank_account VARCHAR(50) NOT NULL,
    bank_account_name VARCHAR(100) NOT NULL,
    
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    slip_image VARCHAR(255) NULL COMMENT 'Proof of transfer',
    reject_reason TEXT NULL,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Withdrawal requests';

-- 7. Contents (News & Announcements)
CREATE TABLE IF NOT EXISTS contents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    image VARCHAR(255) NULL,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    start_date DATETIME NULL COMMENT 'Scheduled visibility start',
    end_date DATETIME NULL COMMENT 'Scheduled visibility end',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='CMS for announcements';

-- 8. Admin Notifications
CREATE TABLE IF NOT EXISTS admin_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('new_user', 'report', 'withdrawal', 'order') NOT NULL,
    message VARCHAR(255) NOT NULL,
    related_id INT NULL COMMENT 'ID of related entity (user_id, order_id, etc.)',
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Alerts for admins';

-- Initial Seed Data
INSERT INTO waste_types (name, price_per_kg, price_walkin) VALUES 
('พลาสติกใส (PET)', 12.00, 15.00),
('กระป๋องอลูมิเนียม', 35.00, 40.00),
('กระดาษลัง', 4.50, 6.00),
('ขวดแก้ว', 2.00, 3.00);
