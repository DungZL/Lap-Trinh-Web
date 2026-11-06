</main>
<footer class="kz-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">

            <div class="col-12 col-md-5">
                <h2 class="kz-brand h4">KiddoZone</h2>
                <p class="mb-0">Đồ chơi an toàn, vui và hữu ích cho bé từ 0 đến 12 tuổi.</p>
            </div>

            <div class="col-6 col-md-3">
                <h3 class="kz-footer-title">Mua sắm</h3>
                <ul class="list-unstyled mb-0">
                    <li><a href="<?= e(url('index.php')) ?>">Trang chủ</a></li>
                    <li><a href="<?= e(url('products.php')) ?>">Tất cả sản phẩm</a></li>
                    <li><a href="<?= e(url('cart.php')) ?>">Giỏ hàng</a></li>
                </ul>
            </div>

            <div class="col-6 col-md-4">
                <h3 class="kz-footer-title">Liên hệ</h3>
                <ul class="list-unstyled mb-0">
                    <li><i class="bi bi-geo-alt"></i> TP. Hồ Chí Minh</li>
                    <li><i class="bi bi-telephone"></i> 0900 000 000</li>
                    <li><i class="bi bi-envelope"></i> hello@kiddozone.vn</li>
                </ul>
            </div>

        </div>
    </div>
    <div class="kz-footer-bottom text-center py-3">
        <small>&copy; <?= date('Y') ?> KiddoZone - Đồ chơi trẻ em</small>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
