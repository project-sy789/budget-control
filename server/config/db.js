const { Pool } = require("pg");
const config = require("./index");

// Create a new PostgreSQL connection pool
// Configuration is taken from config/index.js, which reads from .env variables
const pool = new Pool({
  user: config.db.user,
  host: config.db.host,
  database: config.db.database,
  password: config.db.password,
  port: config.db.port,
  ssl: config.db.ssl,
});

// Test the connection
pool.connect((err, client, release) => {
  if (err) {
    console.error("Error acquiring client", err.stack);
    // Consider exiting the process if DB connection fails on startup
    // process.exit(1);
  } else {
    console.log("Successfully connected to PostgreSQL database");
    client.query("SELECT NOW()", (err, result) => {
      release(); // Release the client back to the pool
      if (err) {
        return console.error("Error executing query", err.stack);
      }
      console.log("Current time from DB:", result.rows[0].now);
    });
  }
});

// Export the pool for use in other modules
module.exports = {
  query: (text, params) => pool.query(text, params),
  pool: pool, // Export the pool itself if needed for transactions
};

// Optional: Graceful shutdown
process.on("SIGINT", () => {
  pool.end(() => {
    console.log("PostgreSQL pool has been closed gracefully");
    process.exit(0);
  });
});

