CREATE TABLE parameters (
  id INT PRIMARY KEY AUTO_INCREMENT,
  energy_cost_kwh DECIMAL(10,4) NOT NULL,
  profit_margin_percent DECIMAL(5,2) NOT NULL,
  hourly_additional DECIMAL(10,2) NOT NULL,
  printer_life_hours INT DEFAULT NULL,
  company_name VARCHAR(160) DEFAULT NULL,
  company_phone VARCHAR(40) DEFAULT NULL,
  company_email VARCHAR(160) DEFAULT NULL,
  company_logo VARCHAR(255) DEFAULT NULL,
  print_note1 VARCHAR(255) DEFAULT NULL,
  print_note2 VARCHAR(255) DEFAULT NULL,
  pix_key VARCHAR(255) DEFAULT NULL,
  pix_name VARCHAR(255) DEFAULT NULL,
  pix_city VARCHAR(255) DEFAULT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(80) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE clients (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  phone VARCHAR(20),
  email VARCHAR(120),
  note TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE printers (
  id INT PRIMARY KEY AUTO_INCREMENT,
  name VARCHAR(120) NOT NULL,
  brand VARCHAR(80),
  model VARCHAR(80),
  price DECIMAL(12,2),
  is_multimaterial TINYINT(1) NOT NULL DEFAULT 0,
  power_w INT NOT NULL
);

CREATE TABLE filaments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  description VARCHAR(160) NOT NULL,
  brand VARCHAR(80),
  type ENUM('PLA','PET-G','ABS','TPU','ASA') NOT NULL,
  price_per_kg DECIMAL(12,2) NOT NULL
);

CREATE TABLE services (
  id INT PRIMARY KEY AUTO_INCREMENT,
  description VARCHAR(160) NOT NULL,
  price DECIMAL(12,2) NOT NULL
);

CREATE TABLE budgets (
  id INT PRIMARY KEY AUTO_INCREMENT,
  client_id INT NOT NULL,
  date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  approved TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE
);

CREATE TABLE budget_items (
  id INT PRIMARY KEY AUTO_INCREMENT,
  budget_id INT NOT NULL,
  printer_id INT NOT NULL,
  gcode_file VARCHAR(255),
  print_time_seconds INT NOT NULL DEFAULT 0,
  energy_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  filament_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  hourly_additional_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  services_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  depreciation_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
  quantity INT NOT NULL DEFAULT 1,
  item_total DECIMAL(12,2) NOT NULL DEFAULT 0,
  service_id INT DEFAULT NULL,
  note TEXT,
  thumbnail_base64 MEDIUMTEXT,
  closed TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
  FOREIGN KEY (printer_id) REFERENCES printers(id) ON DELETE RESTRICT,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
);

CREATE TABLE budget_item_filaments (
  id INT PRIMARY KEY AUTO_INCREMENT,
  budget_item_id INT NOT NULL,
  filament_id INT NOT NULL,
  grams DECIMAL(12,3) NOT NULL,
  FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE,
  FOREIGN KEY (filament_id) REFERENCES filaments(id) ON DELETE RESTRICT
);

CREATE INDEX idx_budgets_client ON budgets(client_id);
CREATE INDEX idx_items_budget ON budget_items(budget_id);
CREATE INDEX idx_items_printer ON budget_items(printer_id);
CREATE INDEX idx_bif_item ON budget_item_filaments(budget_item_id);
CREATE TABLE budget_item_services (
  id INT PRIMARY KEY AUTO_INCREMENT,
  budget_item_id INT NOT NULL,
  service_id INT NOT NULL,
  FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE RESTRICT
);

INSERT INTO budget_item_services (budget_item_id, service_id)
SELECT id, service_id FROM budget_items WHERE service_id IS NOT NULL AND service_id > 0;

CREATE TABLE IF NOT EXISTS orders (
  id INT PRIMARY KEY AUTO_INCREMENT,
  budget_id INT NOT NULL,
  budget_item_id INT NOT NULL,
  production_date DATE,
  delivery_date DATE,
  due_date DATE,
  amount DECIMAL(12,2) NOT NULL,
  status ENUM('open','produced','delivered','paid') DEFAULT 'open',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (budget_id) REFERENCES budgets(id) ON DELETE CASCADE,
  FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS accounts_receivable (
  id INT PRIMARY KEY AUTO_INCREMENT,
  order_id INT DEFAULT NULL,
  description VARCHAR(160) NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  received TINYINT(1) NOT NULL DEFAULT 0,
  received_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS finance_categories (
  id INT PRIMARY KEY AUTO_INCREMENT,
  type ENUM('PAYABLE','RECEIVABLE') NOT NULL,
  name VARCHAR(80) NOT NULL,
  color VARCHAR(16) DEFAULT NULL,
  UNIQUE KEY uniq_type_name (type,name)
);

CREATE TABLE IF NOT EXISTS accounts_payable (
  id INT PRIMARY KEY AUTO_INCREMENT,
  category ENUM('FILAMENTO','ENERGIA','OUTROS') NOT NULL,
  category_id INT DEFAULT NULL,
  description VARCHAR(160) NOT NULL,
  due_date DATE NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  paid TINYINT(1) NOT NULL DEFAULT 0,
  paid_date DATE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES finance_categories(id) ON DELETE SET NULL
);