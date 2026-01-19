-- Promote user ID 1 to admin
UPDATE users SET role = 'admin' WHERE id = 1;

-- Also try to promote 'admin' username just in case
UPDATE users SET role = 'admin' WHERE username = 'admin';
