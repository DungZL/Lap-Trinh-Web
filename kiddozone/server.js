const express = require("express");
const cors = require("cors");
const multer = require("multer");
require("dotenv").config();

// SỬA ĐƯỜNG DẪN TẠI ĐÂY (bỏ chữ kiddozone đi)
const db = require("./admin/config/db");

const app = express();

app.use("/uploads", express.static("uploads"));

app.use(cors());
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

app.use((req, res, next) => {
    console.log("========== REQUEST ==========");
    console.log("Method:", req.method);
    console.log("URL:", req.url);
    console.log("Content-Type:", req.headers["content-type"]);
    console.log("Body:", req.body);
    next();
});

// SỬA ĐƯỜNG DẪN TẠI ĐÂY (bỏ chữ kiddozone đi)
const productRoutes = require("./admin/routes/products");
app.use("/api/admin/products", productRoutes);

app.get("/", (req, res) => {
    res.json({
        message: "Backend Web Ban Hang dang hoat dong!"
    });
});

app.set("view engine", "ejs");
app.set("views", "./admin/views"); 

app.get("/admin/products", async (req, res) => {
    try {
        const [products] = await db.query("SELECT * FROM products ORDER BY id DESC");
        res.render("products", { products: products });
    } catch (error) {
        console.error(error);
        res.send("Lỗi khi tải trang Admin");
    }
});

app.use((err, req, res, next) => {
    if (err instanceof multer.MulterError) {
        return res.status(400).json({
            success: false,
            message: "Lỗi upload file: " + err.message
        });
    } else if (err) {
        return res.status(400).json({
            success: false,
            message: err.message
        });
    }
    next();
});

const PORT = process.env.PORT || 3000;

app.listen(PORT, () => {
    console.log(`Server dang chay tai http://localhost:${PORT}`);
});