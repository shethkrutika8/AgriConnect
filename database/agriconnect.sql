-- ============================================================
-- AgriConnect Database Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS agriconnect CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE agriconnect;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','farmer','buyer') DEFAULT 'farmer',
    phone VARCHAR(20),
    avatar VARCHAR(255) DEFAULT 'default.png',
    otp VARCHAR(10),
    otp_expires DATETIME,
    is_verified TINYINT(1) DEFAULT 0,
    is_2fa_enabled TINYINT(1) DEFAULT 0,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    remember_token VARCHAR(255),
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Farmers profile table
CREATE TABLE IF NOT EXISTS farmers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    farm_name VARCHAR(150),
    location VARCHAR(200),
    farm_size DECIMAL(10,2),
    size_unit ENUM('acres','hectares','sq_meters') DEFAULT 'acres',
    soil_type VARCHAR(100),
    irrigation_type VARCHAR(100),
    experience_years INT DEFAULT 0,
    bio TEXT,
    status ENUM('active','inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Crop categories
CREATE TABLE IF NOT EXISTS crop_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    icon VARCHAR(50) DEFAULT 'leaf',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Crops table
CREATE TABLE IF NOT EXISTS crops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    variety VARCHAR(100),
    season ENUM('Kharif','Rabi','Zaid','Year-round') DEFAULT 'Kharif',
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'kg',
    quantity DECIMAL(10,2) DEFAULT 0,
    min_order DECIMAL(10,2) DEFAULT 1,
    harvest_date DATE,
    planting_date DATE,
    description TEXT,
    image VARCHAR(255) DEFAULT 'default_crop.jpg',
    status ENUM('available','sold_out','coming_soon','inactive') DEFAULT 'available',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES crop_categories(id) ON DELETE SET NULL
);

-- Product categories
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Products table
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    farmer_id INT,
    category_id INT,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    unit VARCHAR(50) DEFAULT 'kg',
    quantity INT DEFAULT 0,
    image VARCHAR(255) DEFAULT 'default_product.jpg',
    status ENUM('active','inactive','out_of_stock') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (farmer_id) REFERENCES farmers(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
);

-- Marketplace listings
CREATE TABLE IF NOT EXISTS marketplace_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    type ENUM('crop','product') DEFAULT 'crop',
    item_id INT NOT NULL,
    seller_id INT NOT NULL,
    title VARCHAR(200),
    description TEXT,
    price DECIMAL(10,2),
    quantity DECIMAL(10,2),
    unit VARCHAR(50),
    location VARCHAR(200),
    image VARCHAR(255) DEFAULT 'default_listing.jpg',
    status ENUM('active','sold','expired','inactive') DEFAULT 'active',
    views INT DEFAULT 0,
    listed_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Orders
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE,
    buyer_id INT,
    seller_id INT,
    listing_id INT,
    quantity DECIMAL(10,2),
    unit_price DECIMAL(10,2),
    total_amount DECIMAL(12,2),
    status ENUM('pending','confirmed','processing','shipped','delivered','cancelled') DEFAULT 'pending',
    payment_status ENUM('pending','paid','failed','refunded') DEFAULT 'pending',
    delivery_address TEXT,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Weather data
CREATE TABLE IF NOT EXISTS weather_data (
    id INT AUTO_INCREMENT PRIMARY KEY,
    city VARCHAR(100) DEFAULT 'New Delhi',
    temperature DECIMAL(5,2),
    feels_like DECIMAL(5,2),
    `condition` VARCHAR(100),
    humidity INT,
    wind_speed DECIMAL(5,2),
    wind_direction VARCHAR(10),
    visibility DECIMAL(5,2),
    uv_index INT,
    forecast_date DATE,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Activity log
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100),
    description TEXT,
    ip_address VARCHAR(45),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Settings
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin user (password: Admin@123)
INSERT INTO users (name, email, password, role, phone, is_verified, status) VALUES
('Admin User', 'admin@agriconnect.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+91-9876543210', 1, 'active'),
('Rajesh Kumar', 'rajesh@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '+91-9812345678', 1, 'active'),
('Priya Sharma', 'priya@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '+91-9823456789', 1, 'active'),
('Suresh Patel', 'suresh@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+91-9834567890', 1, 'active'),
('Anita Singh', 'anita@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '+91-9845678901', 1, 'active'),
('Mohan Das', 'mohan@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '+91-9856789012', 1, 'active'),
('Kavitha Reddy', 'kavitha@example.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+91-9867890123', 1, 'active'),
('Farmer Demo', 'farmer@agriconnect.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'farmer', '+91-9878901234', 1, 'active');

-- Farmers profiles
INSERT INTO farmers (user_id, farm_name, location, farm_size, size_unit, soil_type, irrigation_type, experience_years, bio, status) VALUES
(2, 'Kumar Organic Farm', 'Punjab, India', 45.5, 'acres', 'Alluvial', 'Drip Irrigation', 15, 'Specializing in organic wheat and rice cultivation.', 'active'),
(3, 'Sharma Green Fields', 'Haryana, India', 32.0, 'acres', 'Sandy Loam', 'Sprinkler', 10, 'Expert in vegetable and fruit farming.', 'active'),
(5, 'Singh Agro Farm', 'Uttar Pradesh, India', 60.0, 'acres', 'Black Cotton', 'Canal', 20, 'Multi-crop farmer with expertise in sugarcane.', 'active'),
(6, 'Das Agri Estate', 'Maharashtra, India', 28.5, 'acres', 'Red Laterite', 'Drip Irrigation', 8, 'Focuses on cotton and soybean cultivation.', 'active'),
(8, 'AgriConnect Demo Farm', 'Rajasthan, India', 50.0, 'acres', 'Alluvial', 'Flood', 12, 'Demo farm for testing all features.', 'active');

-- Crop categories
INSERT INTO crop_categories (name, description, icon) VALUES
('Cereals & Grains', 'Wheat, Rice, Maize, Barley', 'grain'),
('Vegetables', 'All types of vegetables', 'carrot'),
('Fruits', 'Fresh fruits', 'apple'),
('Pulses & Legumes', 'Lentils, Chickpeas, Peas', 'bean'),
('Oilseeds', 'Soybean, Sunflower, Mustard', 'oil'),
('Cash Crops', 'Cotton, Sugarcane, Tobacco', 'money'),
('Spices & Herbs', 'Turmeric, Ginger, Chilli', 'spice'),
('Horticulture', 'Flowers, Ornamental plants', 'flower');

-- Crops
INSERT INTO crops (farmer_id, category_id, name, variety, season, price, unit, quantity, harvest_date, planting_date, description, status) VALUES
(1, 1, 'Wheat', 'HD-2967', 'Rabi', 22.50, 'kg', 5000, '2026-04-15', '2025-11-01', 'High-yield wheat variety, organically grown without pesticides.', 'available'),
(1, 1, 'Basmati Rice', 'Pusa-1121', 'Kharif', 85.00, 'kg', 3000, '2025-10-20', '2025-06-15', 'Premium quality basmati rice with long grain and aroma.', 'available'),
(2, 2, 'Tomato', 'Hybrid-123', 'Year-round', 35.00, 'kg', 800, '2026-01-30', '2025-11-15', 'Fresh red tomatoes, no chemical fertilizers.', 'available'),
(2, 2, 'Potato', 'Kufri Jyoti', 'Rabi', 18.00, 'kg', 2500, '2026-02-28', '2025-12-01', 'Medium-sized, uniform potatoes suitable for export.', 'available'),
(3, 6, 'Sugarcane', 'Co-0238', 'Year-round', 350.00, 'quintal', 150, '2026-11-01', '2025-11-01', 'High-sugar content sugarcane for jaggery and sugar mills.', 'available'),
(3, 2, 'Onion', 'Nasik Red', 'Rabi', 25.00, 'kg', 1200, '2026-03-10', '2025-12-20', 'Bold, red onions with good shelf life.', 'available'),
(4, 6, 'Cotton', 'Bt Cotton', 'Kharif', 6500.00, 'quintal', 80, '2026-01-15', '2025-06-01', 'Bt cotton with high fiber quality.', 'available'),
(4, 5, 'Soybean', 'JS-335', 'Kharif', 42.00, 'kg', 600, '2025-10-30', '2025-06-20', 'Protein-rich soybean grown on black soil.', 'sold_out'),
(5, 1, 'Maize', 'HQPM-1', 'Kharif', 19.00, 'kg', 4000, '2026-09-15', '2026-06-01', 'High-quality protein maize for poultry and food industry.', 'available'),
(5, 2, 'Green Peas', 'Arkel', 'Rabi', 55.00, 'kg', 300, '2026-01-20', '2025-11-10', 'Sweet green peas, good for fresh market.', 'coming_soon'),
(1, 3, 'Mango', 'Alphonso', 'Zaid', 120.00, 'kg', 500, '2026-05-30', '2026-03-01', 'Premium Alphonso mangoes from organic orchard.', 'coming_soon'),
(2, 7, 'Turmeric', 'Salem', 'Kharif', 95.00, 'kg', 200, '2026-01-10', '2025-05-15', 'High-curcumin turmeric, sun-dried naturally.', 'available');

-- Product categories
INSERT INTO product_categories (name, description) VALUES
('Seeds', 'Agricultural Seeds'),
('Fertilizers', 'Organic and Chemical Fertilizers'),
('Pesticides', 'Crop Protection Products'),
('Equipment', 'Farm Tools and Machinery'),
('Fresh Produce', 'Direct Farm Produce');

-- Products
INSERT INTO products (farmer_id, category_id, name, description, price, unit, quantity, status) VALUES
(1, 1, 'Wheat Seeds HD-2967', 'Certified wheat seeds for Rabi season', 125.00, 'kg', 500, 'active'),
(1, 2, 'Vermi-Compost', 'Premium quality vermicompost for all crops', 12.00, 'kg', 2000, 'active'),
(2, 5, 'Fresh Spinach', 'Organically grown fresh spinach leaves', 40.00, 'bunch', 150, 'active'),
(2, 5, 'Bitter Gourd', 'Fresh bitter gourd harvested daily', 38.00, 'kg', 80, 'active'),
(3, 3, 'Neem Oil Spray', 'Natural neem oil pesticide, 500ml bottle', 220.00, 'bottle', 200, 'active'),
(4, 4, 'Hand Sprayer', 'Manual 16-litre knapsack sprayer', 850.00, 'piece', 30, 'active'),
(5, 1, 'Maize Seeds HQPM-1', 'Certified quality protein maize seeds', 180.00, 'kg', 300, 'active'),
(5, 5, 'Sweet Corn', 'Fresh sweet corn cobs, good for eating', 25.00, 'kg', 120, 'active');

-- Marketplace listings
INSERT INTO marketplace_listings (type, item_id, seller_id, title, description, price, quantity, unit, location, status) VALUES
('crop', 1, 2, 'Organic Wheat – Punjab', 'Fresh harvested organic wheat, HD-2967 variety', 22.50, 5000, 'kg', 'Amritsar, Punjab', 'active'),
('crop', 2, 2, 'Premium Basmati Rice', 'Pusa-1121 Basmati, long grain aromatic rice', 85.00, 3000, 'kg', 'Amritsar, Punjab', 'active'),
('crop', 3, 3, 'Fresh Tomatoes', 'Farm-fresh tomatoes, harvested daily', 35.00, 800, 'kg', 'Karnal, Haryana', 'active'),
('product', 3, 3, 'Organic Spinach Bunches', 'Leafy green spinach from certified farm', 40.00, 100, 'bunch', 'Karnal, Haryana', 'active'),
('crop', 6, 5, 'Red Onion – Nasik', 'Bold, fresh red onions with 3-month shelf life', 25.00, 1200, 'kg', 'Nashik, Maharashtra', 'active'),
('crop', 7, 6, 'Bt Cotton Kapas', 'High-fiber Bt cotton bolls ready for ginning', 6500.00, 80, 'quintal', 'Nagpur, Maharashtra', 'active'),
('crop', 12, 3, 'Salem Turmeric', 'High-curcumin turmeric, organically grown', 95.00, 200, 'kg', 'Erode, Tamil Nadu', 'active'),
('product', 1, 2, 'Certified Wheat Seeds', 'HD-2967 certified seeds for Rabi season', 125.00, 500, 'kg', 'Amritsar, Punjab', 'active');

-- Orders
INSERT INTO orders (order_number, buyer_id, seller_id, listing_id, quantity, unit_price, total_amount, status, payment_status, delivery_address) VALUES
('ORD-2025-001', 4, 2, 1, 500, 22.50, 11250.00, 'delivered', 'paid', '123 Market Road, Delhi'),
('ORD-2025-002', 7, 3, 3, 100, 35.00, 3500.00, 'shipped', 'paid', '45 Sector 12, Gurgaon'),
('ORD-2025-003', 4, 5, 5, 200, 25.00, 5000.00, 'processing', 'pending', '78 MG Road, Mumbai'),
('ORD-2025-004', 7, 2, 2, 50, 85.00, 4250.00, 'confirmed', 'paid', '90 Lake View, Hyderabad'),
('ORD-2025-005', 4, 6, 6, 10, 6500.00, 65000.00, 'pending', 'pending', '12 Town Hall, Nagpur'),
('ORD-2025-006', 7, 3, 4, 30, 40.00, 1200.00, 'delivered', 'paid', '56 West End, Chennai'),
('ORD-2025-007', 4, 6, 7, 80, 95.00, 7600.00, 'processing', 'pending', '34 Old Town, Kolkata'),
('ORD-2025-008', 7, 2, 8, 50, 125.00, 6250.00, 'pending', 'pending', '67 Fort Area, Mumbai');

-- Weather data (7-day forecast)
INSERT INTO weather_data (city, temperature, feels_like, `condition`, humidity, wind_speed, wind_direction, visibility, uv_index, forecast_date) VALUES
('New Delhi', 28.5, 30.2, 'Partly Cloudy', 65, 12.5, 'NE', 8.0, 6, CURDATE()),
('New Delhi', 30.1, 32.5, 'Sunny', 58, 10.0, 'N', 10.0, 8, DATE_ADD(CURDATE(), INTERVAL 1 DAY)),
('New Delhi', 27.3, 29.0, 'Cloudy', 72, 15.0, 'NW', 7.0, 4, DATE_ADD(CURDATE(), INTERVAL 2 DAY)),
('New Delhi', 25.8, 27.1, 'Light Rain', 85, 18.0, 'W', 5.0, 2, DATE_ADD(CURDATE(), INTERVAL 3 DAY)),
('New Delhi', 24.5, 26.0, 'Rainy', 90, 20.0, 'SW', 4.0, 1, DATE_ADD(CURDATE(), INTERVAL 4 DAY)),
('New Delhi', 26.2, 27.8, 'Partly Cloudy', 75, 13.0, 'S', 8.0, 5, DATE_ADD(CURDATE(), INTERVAL 5 DAY)),
('New Delhi', 29.0, 31.0, 'Sunny', 60, 11.0, 'SE', 10.0, 7, DATE_ADD(CURDATE(), INTERVAL 6 DAY));

-- Activity log
INSERT INTO activity_log (user_id, action, description, ip_address) VALUES
(1, 'login', 'Admin user logged in', '127.0.0.1'),
(2, 'crop_added', 'Added new crop: Wheat (HD-2967)', '127.0.0.1'),
(3, 'product_updated', 'Updated product: Fresh Spinach', '127.0.0.1'),
(2, 'listing_created', 'Created marketplace listing for Basmati Rice', '127.0.0.1'),
(4, 'order_placed', 'Placed order ORD-2025-001 for Organic Wheat', '127.0.0.1'),
(5, 'crop_added', 'Added new crop: Sugarcane (Co-0238)', '127.0.0.1'),
(1, 'user_updated', 'Updated user profile for Priya Sharma', '127.0.0.1'),
(7, 'order_placed', 'Placed order ORD-2025-006 for Organic Spinach', '127.0.0.1');

-- Settings
INSERT INTO settings (setting_key, setting_value) VALUES
('site_name', 'AgriConnect'),
('site_tagline', 'Connecting Farmers to Markets'),
('currency', 'INR'),
('currency_symbol', '₹'),
('items_per_page', '10'),
('otp_expiry_minutes', '10'),
('admin_email', 'admin@agriconnect.com'),
('weather_city', 'New Delhi');
