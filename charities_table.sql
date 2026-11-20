-- Create charities table if it doesn't exist
CREATE TABLE IF NOT EXISTS `charities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `charity_name` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `mission` text DEFAULT NULL,
  `vision` text DEFAULT NULL,
  `logo_url` varchar(255) DEFAULT NULL,
  `date_established` date DEFAULT NULL,
  `date_registered` date DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `contact_phone` varchar(50) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample charity data
INSERT INTO `charities` (`charity_name`, `description`, `mission`, `vision`, `logo_url`, `date_established`, `date_registered`, `contact_email`, `contact_phone`, `status`)
VALUES
('Hope for Children', 'Hope for Children is dedicated to providing essential support to orphaned and vulnerable children across Kenya. We focus on providing nutritious meals, quality education, healthcare, and a safe environment for children to grow and thrive.', 'To ensure every child has access to basic needs, education, and a loving environment regardless of their circumstances.', 'A world where all children have equal opportunities to reach their full potential.', 'https://wallpaperaccess.com/full/4219101.jpg', '2019-03-15', '2019-05-20', 'info@hopeforchildren.org', '+254 712 345 678', 'active'),

('Clothing for All', 'Clothing for All collects, sorts, and distributes clothing to individuals and families in need. We believe that everyone deserves the dignity of clean, appropriate clothing regardless of their financial situation.', 'To provide quality clothing to those in need while promoting sustainability and reducing textile waste.', 'A community where no one lacks proper clothing and where textile recycling is the norm.', 'https://wallpapercave.com/wp/wp2595327.jpg', '2018-06-10', '2018-08-22', 'donate@clothingforall.org', '+254 723 456 789', 'active'),

('Education Empowerment', 'Education Empowerment works to ensure that all students have the tools they need to succeed academically. We provide school supplies, textbooks, and technology to underserved schools and communities.', 'To bridge the educational resource gap and empower students through access to quality learning materials.', 'An educated society where all students have equal access to the resources they need for academic success.', 'https://wallpaperbat.com/img/135836994-educational-background-wallpaper.jpg', '2020-01-25', '2020-03-12', 'help@educationempowerment.org', '+254 734 567 890', 'active');

