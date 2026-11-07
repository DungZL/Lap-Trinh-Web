const mysql = require("mysql2/promise");
const path = require("path");

// Trỏ đường dẫn chính xác tới file .env ở thư mục gốc
// __dirname là thư mục admin/config, lùi 2 cấp (../../) sẽ ra thư mục gốc webbanhang
require("dotenv").config({ path: path.join(__dirname, "../../.env") });

const pool = mysql.createPool({
    host: process.env.DB_HOST,
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME,
    port: process.env.DB_PORT || 3306,
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

module.exports = pool;