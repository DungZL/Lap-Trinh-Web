<?php
require_once __DIR__ . '/../includes/init.php';
require_admin();

$pageTitle = 'Tổng quan quản trị';
$pageStylesheet = 'assets/css/admin.css';
require __DIR__ . '/../includes/header.php';
?>
<div class="admin-dashboard">
    <aside class="admin-sidebar" aria-label="Điều hướng quản trị">
        <a class="admin-brand" href="<?= e(url('admin/index.php')) ?>">
            <span class="admin-brand-icon"><i class="bi bi-stars" aria-hidden="true"></i></span>
            <span>Kiddo<span class="admin-brand-accent">Zone</span><small>TRUNG TÂM QUẢN TRỊ</small></span>
        </a>

        <p class="admin-nav-label">MENU CHÍNH</p>
        <nav class="admin-nav">
            <a class="admin-nav-link is-active" href="#overview" aria-current="page">
                <i class="bi bi-grid-1x2-fill" aria-hidden="true"></i><span>Tổng quan</span>
            </a>
            <a class="admin-nav-link" href="#orders">
                <i class="bi bi-receipt" aria-hidden="true"></i><span>Đơn hàng</span>
                <span class="admin-nav-soon">Sắp có</span>
            </a>
            <a class="admin-nav-link" href="#products">
                <i class="bi bi-box-seam" aria-hidden="true"></i><span>Sản phẩm</span>
                <span class="admin-nav-soon">Sắp có</span>
            </a>
            <a class="admin-nav-link" href="#customers">
                <i class="bi bi-people" aria-hidden="true"></i><span>Khách hàng</span>
                <span class="admin-nav-soon">Sắp có</span>
            </a>
        </nav>

        <div class="admin-sidebar-bottom">
            <div class="admin-help-card">
                <span class="admin-help-icon"><i class="bi bi-lightbulb" aria-hidden="true"></i></span>
                <strong>Sẵn sàng khởi tạo?</strong>
                <p>Các công cụ quản lý sẽ xuất hiện tại đây.</p>
            </div>
            <a class="admin-store-link" href="<?= e(url('index.php')) ?>">
                <i class="bi bi-arrow-left" aria-hidden="true"></i> Quay lại cửa hàng
            </a>
        </div>
    </aside>

    <div class="admin-main">
        <header class="admin-topbar">
            <div class="admin-breadcrumb"><span>KiddoZone</span><i class="bi bi-chevron-right" aria-hidden="true"></i><strong>Quản trị</strong></div>
            <div class="admin-account">
                <span class="admin-account-avatar" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
                <span class="admin-account-name"><?= e(current_user()['full_name']) ?><small>Quản trị viên</small></span>
            </div>
        </header>

        <div class="admin-content" id="overview">
            <div class="admin-heading">
                <div>
                    <p class="admin-eyebrow">BẢNG ĐIỀU KHIỂN</p>
                    <h1>Chào bạn, <?= e(current_user()['full_name']) ?> <span aria-hidden="true">👋</span></h1>
                    <p class="admin-heading-subtitle">Đây là những gì đang diễn ra tại cửa hàng của bạn.</p>
                </div>
                <span class="admin-date"><i class="bi bi-calendar3" aria-hidden="true"></i> <?= date('d/m/Y') ?></span>
            </div>

            <section class="admin-stats" aria-label="Chỉ số cửa hàng">
                <article class="admin-stat-card">
                    <div class="admin-stat-top"><span>Doanh thu</span><span class="admin-stat-icon icon-violet"><i class="bi bi-currency-dollar" aria-hidden="true"></i></span></div>
                    <strong class="admin-stat-value">—</strong>
                    <span class="admin-stat-note">Chưa có dữ liệu thống kê</span>
                </article>
                <article class="admin-stat-card">
                    <div class="admin-stat-top"><span>Đơn hàng</span><span class="admin-stat-icon icon-orange"><i class="bi bi-bag-check" aria-hidden="true"></i></span></div>
                    <strong class="admin-stat-value">—</strong>
                    <span class="admin-stat-note">Chưa có dữ liệu thống kê</span>
                </article>
                <article class="admin-stat-card">
                    <div class="admin-stat-top"><span>Sản phẩm</span><span class="admin-stat-icon icon-blue"><i class="bi bi-box2-heart" aria-hidden="true"></i></span></div>
                    <strong class="admin-stat-value">—</strong>
                    <span class="admin-stat-note">Chưa có dữ liệu thống kê</span>
                </article>
                <article class="admin-stat-card">
                    <div class="admin-stat-top"><span>Khách hàng</span><span class="admin-stat-icon icon-green"><i class="bi bi-person-hearts" aria-hidden="true"></i></span></div>
                    <strong class="admin-stat-value">—</strong>
                    <span class="admin-stat-note">Chưa có dữ liệu thống kê</span>
                </article>
            </section>

            <section class="admin-panel admin-chart-panel" aria-labelledby="revenue-title">
                <div class="admin-panel-heading">
                    <div>
                        <h2 id="revenue-title">Tổng quan doanh thu</h2>
                        <p>Dữ liệu bán hàng sẽ được hiển thị tại đây</p>
                    </div>
                    <span class="admin-period"><i class="bi bi-clock" aria-hidden="true"></i> 7 ngày qua</span>
                </div>
                <div class="admin-chart-placeholder" role="img" aria-label="Biểu đồ doanh thu sẽ hiển thị khi có dữ liệu">
                    <div class="admin-chart-y-axis" aria-hidden="true"><span>—</span><span>—</span><span>—</span><span>—</span></div>
                    <div class="admin-chart-area" aria-hidden="true">
                        <div class="admin-chart-grid"><span></span><span></span><span></span><span></span></div>
                        <div class="admin-chart-empty"><span class="admin-chart-empty-icon"><i class="bi bi-bar-chart-line" aria-hidden="true"></i></span><strong>Chưa có dữ liệu doanh thu</strong><span>Biểu đồ sẽ được cập nhật khi cửa hàng phát sinh đơn hàng.</span></div>
                    </div>
                </div>
            </section>

            <section class="admin-panel admin-orders-panel" id="orders" aria-labelledby="orders-title">
                <div class="admin-panel-heading">
                    <div>
                        <h2 id="orders-title">Đơn hàng gần đây</h2>
                        <p>Theo dõi các đơn hàng mới nhất của cửa hàng</p>
                    </div>
                    <a class="admin-text-link" href="#orders">Xem tất cả <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
                </div>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr><th>MÃ ĐƠN</th><th>KHÁCH HÀNG</th><th>NGÀY ĐẶT</th><th>GIÁ TRỊ</th><th>TRẠNG THÁI</th></tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="5"><div class="admin-empty-state"><span><i class="bi bi-inbox" aria-hidden="true"></i></span><strong>Chưa có đơn hàng nào</strong><small>Đơn hàng mới sẽ xuất hiện tại đây.</small></div></td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <div class="admin-bottom-grid">
                <section class="admin-panel admin-quick-panel" id="products" aria-labelledby="quick-title">
                    <div class="admin-panel-heading">
                        <div><h2 id="quick-title">Quản lý nhanh</h2><p>Các khu vực quản trị cửa hàng</p></div>
                    </div>
                    <div class="admin-quick-links">
                        <a href="#products"><span class="admin-quick-icon icon-blue"><i class="bi bi-box-seam" aria-hidden="true"></i></span><span><strong>Sản phẩm</strong><small>Quản lý danh mục và kho hàng</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        <a href="#orders"><span class="admin-quick-icon icon-orange"><i class="bi bi-receipt" aria-hidden="true"></i></span><span><strong>Đơn hàng</strong><small>Xem và xử lý đơn hàng</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        <a href="#customers"><span class="admin-quick-icon icon-green"><i class="bi bi-people" aria-hidden="true"></i></span><span><strong>Khách hàng</strong><small>Thông tin khách mua hàng</small></span><i class="bi bi-chevron-right" aria-hidden="true"></i></a>
                    </div>
                </section>
                <section class="admin-panel admin-welcome-panel" id="customers" aria-labelledby="welcome-title">
                    <span class="admin-welcome-decoration decoration-one"></span><span class="admin-welcome-decoration decoration-two"></span>
                    <div class="admin-welcome-content">
                        <span class="admin-welcome-icon"><i class="bi bi-balloon-heart" aria-hidden="true"></i></span>
                        <p class="admin-eyebrow">KIDDOZONE ADMIN</p>
                        <h2 id="welcome-title">Cùng tạo nên niềm vui!</h2>
                        <p>Cửa hàng của bạn đã sẵn sàng. Các số liệu sẽ được cập nhật khi tính năng quản lý được kết nối.</p>
                    </div>
                </section>
            </div>
            <footer class="admin-footer">© <?= date('Y') ?> KiddoZone <span>•</span> Đồ chơi an toàn, tuổi thơ trọn vẹn</footer>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
