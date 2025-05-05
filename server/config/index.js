require("dotenv").config();

module.exports = {
  port: process.env.PORT || 3001,
  db: {
    user: process.env.DB_USER || "postgres",
    password: process.env.DB_PASSWORD || "password",
    host: process.env.DB_HOST || "localhost",
    port: process.env.DB_PORT || 5432,
    database: process.env.DB_NAME || "budget_control_db",
    ssl: process.env.DB_SSL === "true" ? { rejectUnauthorized: false } : false, // For Render/Railway
  },
  google: {
    clientId: process.env.GOOGLE_CLIENT_ID,
    clientSecret: process.env.GOOGLE_CLIENT_SECRET, // Might be needed for refresh tokens if implemented
  },
  jwt: {
    secret: process.env.JWT_SECRET || "your_very_secret_key_here", // CHANGE THIS IN PRODUCTION!
    expiresIn: "1h", // Token expiration time
  },
};

