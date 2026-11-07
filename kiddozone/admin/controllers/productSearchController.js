const db = require("../config/db");

// Tìm kiếm, lọc, sắp xếp và phân trang (Dùng MySQL)
exports.searchProducts = async (req, res) => {
    try {
        const {
            search,
            category,
            minPrice,
            maxPrice,
            page = 1,
            limit = 5,
            sortOrder,
            stock
        } = req.query;

        // 1. Kiểm tra phân trang
        const currentPage = Number(page);
        const pageLimit = Number(limit);

        if (
            !Number.isInteger(currentPage) ||
            !Number.isInteger(pageLimit) ||
            currentPage < 1 ||
            pageLimit < 1 ||
            pageLimit > 100
        ) {
            return res.status(400).json({
                success: false,
                message: "Page hoặc limit không hợp lệ"
            });
        }

        // 2. Kiểm tra khoảng giá
        const min = minPrice !== undefined ? Number(minPrice) : undefined;
        const max = maxPrice !== undefined ? Number(maxPrice) : undefined;

        if (
            (min !== undefined && (!Number.isFinite(min) || min < 0)) ||
            (max !== undefined && (!Number.isFinite(max) || max < 0))
        ) {
            return res.status(400).json({
                success: false,
                message: "Khoảng giá không hợp lệ"
            });
        }

        if (min !== undefined && max !== undefined && min > max) {
            return res.status(400).json({
                success: false,
                message: "Giá thấp nhất không được lớn hơn giá cao nhất"
            });
        }

        // 3. Kiểm tra cách sắp xếp
        if (sortOrder && !["asc", "desc"].includes(sortOrder)) {
            return res.status(400).json({
                success: false,
                message: "sortOrder chỉ nhận asc hoặc desc"
            });
        }

        // 4. Kiểm tra tình trạng hàng
        if (stock && !["in", "out"].includes(stock)) {
            return res.status(400).json({
                success: false,
                message: "stock chỉ nhận in hoặc out"
            });
        }

        // 5. Xây dựng câu lệnh SQL động (Dynamic SQL)
        let query = "SELECT * FROM products WHERE 1=1";
        let countQuery = "SELECT COUNT(*) AS total FROM products WHERE 1=1";
        const queryParams = [];

        // Tìm theo tên
        if (search) {
            query += " AND name LIKE ?";
            countQuery += " AND name LIKE ?";
            queryParams.push(`%${search}%`);
        }

        // Lọc theo danh mục
        if (category) {
            query += " AND category = ?";
            countQuery += " AND category = ?";
            queryParams.push(category);
        }

        // Lọc giá thấp nhất
        if (min !== undefined) {
            query += " AND price >= ?";
            countQuery += " AND price >= ?";
            queryParams.push(min);
        }

        // Lọc giá cao nhất
        if (max !== undefined) {
            query += " AND price <= ?";
            countQuery += " AND price <= ?";
            queryParams.push(max);
        }

        // Lọc tình trạng kho
        if (stock === "in") {
            query += " AND quantity > 0";
            countQuery += " AND quantity > 0";
        } else if (stock === "out") {
            query += " AND quantity = 0";
            countQuery += " AND quantity = 0";
        }

        // 6. Sắp xếp
        if (sortOrder === "asc") {
            query += " ORDER BY price ASC";
        } else if (sortOrder === "desc") {
            query += " ORDER BY price DESC";
        } else {
            query += " ORDER BY id DESC"; // Mặc định xếp mới nhất
        }

        // 7. Tính toán phân trang
        // Lấy tổng số bản ghi thỏa mãn điều kiện
        const [countResult] = await db.query(countQuery, queryParams);
        const totalItems = countResult[0].total;
        const totalPages = Math.ceil(totalItems / pageLimit);

        // Thêm LIMIT và OFFSET vào câu lệnh chính
        const offset = (currentPage - 1) * pageLimit;
        query += " LIMIT ? OFFSET ?";
        
        // Cẩn thận: Mảng params cần thêm limit và offset vào cuối
        const finalParams = [...queryParams, pageLimit, offset];

        // 8. Thực thi truy vấn lấy dữ liệu
        const [rows] = await db.query(query, finalParams);

        // 9. Trả kết quả
        res.json({
            success: true,
            pagination: {
                currentPage,
                limit: pageLimit,
                totalItems,
                totalPages,
                hasNextPage: currentPage < totalPages,
                hasPrevPage: currentPage > 1
            },
            data: rows
        });

    } catch (error) {
        console.error(error);
        res.status(500).json({
            success: false,
            message: "Lỗi server khi tìm kiếm sản phẩm"
        });
    }
};