const express = require("express");
const router = express.Router();

const productController = require("../controllers/productController");
const productSearchController = require("../controllers/productSearchController");
const upload = require("../middleware/upload"); // Đảm bảo đúng đường dẫn

router.get("/", productController.getProducts);
router.get("/search", productSearchController.searchProducts);
router.post("/", upload.single("image"), productController.createProduct); // Dòng số 11
router.put("/:id", upload.single("image"), productController.updateProduct);
router.delete("/:id", productController.deleteProduct);

module.exports = router;