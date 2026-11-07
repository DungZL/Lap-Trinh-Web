const db = require("../config/db"); // Kết nối MySQL

// 1. Lấy danh sách sản phẩm
exports.getProducts = async (req, res) => {
    try {
        const page = Number(req.query.page || 1);
        const limit = Number(req.query.limit || 5);
        const offset = (page - 1) * limit;

        const [rows] = await db.query(`
            SELECT p.*, c.name AS category_name 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LIMIT ? OFFSET ?
        `, [limit, offset]);
        
        const [countResult] = await db.query("SELECT COUNT(*) AS total FROM products");
        const totalItems = countResult[0].total;
        const totalPages = Math.ceil(totalItems / limit);

        res.json({
            success: true,
            pagination: {
                currentPage: page,
                limit: limit,
                totalItems: totalItems,
                totalPages: totalPages
            },
            data: rows
        });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: "Lỗi server" });
    }
};

// 2. Thêm sản phẩm
exports.createProduct = async (req, res) => {
    try {
        const { name, price, sale_price, stock, category_id, description } = req.body || {};
        const image = req.file ? req.file.filename : (req.body.image || "");

        if (!name || price == null || stock == null || category_id == null) {
            return res.status(400).json({ success: false, message: "Vui lòng nhập đủ tên, giá, số lượng, danh mục" });
        }

        const [result] = await db.query(
            "INSERT INTO products (name, price, sale_price, stock, category_id, description) VALUES (?, ?, ?, ?, ?, ?)",
            [name, price, sale_price || null, stock, category_id, description || ""]
        );

        // Thêm ảnh vào bảng product_images nếu có
        if (image) {
            await db.query("INSERT INTO product_images (product_id, image_path, is_main) VALUES (?, ?, 1)", [result.insertId, image]);
        }

        res.status(201).json({ success: true, message: "Thêm thành công", id: result.insertId });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: "Lỗi server khi thêm" });
    }
};

// 3. Sửa sản phẩm
exports.updateProduct = async (req, res) => {
    try {
        const id = Number(req.params.id);
        const { name, price, sale_price, stock, category_id, description } = req.body || {};

        let updateFields = [];
        let values = [];

        if (name !== undefined) { updateFields.push("name = ?"); values.push(name); }
        if (price !== undefined) { updateFields.push("price = ?"); values.push(price); }
        if (sale_price !== undefined) { updateFields.push("sale_price = ?"); values.push(sale_price); }
        if (stock !== undefined) { updateFields.push("stock = ?"); values.push(stock); }
        if (category_id !== undefined) { updateFields.push("category_id = ?"); values.push(category_id); }
        if (description !== undefined) { updateFields.push("description = ?"); values.push(description); }

        if (updateFields.length > 0) {
            values.push(id);
            await db.query(`UPDATE products SET ${updateFields.join(", ")} WHERE id = ?`, values);
        }

        res.json({ success: true, message: "Cập nhật thành công" });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: "Lỗi server khi sửa" });
    }
};

// 4. Xóa sản phẩm
exports.deleteProduct = async (req, res) => {
    try {
        const id = Number(req.params.id);
        await db.query("DELETE FROM products WHERE id = ?", [id]);
        res.json({ success: true, message: "Xóa thành công" });
    } catch (error) {
        console.error(error);
        res.status(500).json({ success: false, message: "Lỗi server khi xóa" });
    }
};