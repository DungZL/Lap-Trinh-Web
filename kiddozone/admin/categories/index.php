<?php
require_once __DIR__ . '/../../includes/init.php';
require_admin(); // Chỉ Admin được truy cập

$pageTitle = 'Quản lý danh mục';

// Tổng số danh mục và phân trang 10 dòng/trang (ITEMS_PER_PAGE_ADMIN).
$total = (int)db_query('SELECT COUNT(*) FROM categories')->fetchColumn();
[$offset, $pages, $page] = paginate($total, ITEMS_PER_PAGE_ADMIN);

// Danh mục của trang hiện tại, kèm số sản phẩm (để biết danh mục nào không xóa được).
$categories = db_query(
    'SELECT c.id, c.name, c.description, c.status, COUNT(p.id) AS product_count
     FROM categories c
     LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name, c.description, c.status
     ORDER BY c.id ASC
     LIMIT ' . (int)ITEMS_PER_PAGE_ADMIN . ' OFFSET ' . (int)$offset
)->fetchAll();

$pageSubtitle = 'Tổng cộng ' . $total . ' danh mục';
$pageActions = '<a href="' . e(url('admin/categories/create.php')) . '" class="btn btn-primary"><i class="ph-bold ph-plus" aria-hidden="true"></i> Thêm danh mục</a>';
require __DIR__ . '/../admin_header.php';
$tints = ['bg-sun-100 text-sun-800', 'bg-sky-100 text-sky-700', 'bg-coral-100 text-coral-700', 'bg-mint-100 text-ok', 'bg-grape-100 text-grape-600'];
?>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
    <?php foreach ($categories as $i => $category): ?>
        <article data-reveal class="card flex flex-col p-5">
            <div class="flex items-start gap-4">
                <span class="grid size-14 shrink-0 place-items-center rounded-2xl text-2xl <?= $tints[(int)$category['id'] % count($tints)] ?>"><i class="ph-fill ph-folder-simple" aria-hidden="true"></i></span>
                <div class="min-w-0 flex-1">
                    <h2 class="truncate font-display text-lg font-semibold"><?= e($category['name']) ?></h2>
                    <p class="line-clamp-2 text-sm text-muted"><?= e($category['description'] ?? 'Chưa có mô tả') ?></p>
                </div>
            </div>
            <div class="mt-4 flex items-center gap-2 border-t border-ink-100 pt-4 dark:border-ink-800">
                <span class="badge badge-sky"><?= (int)$category['product_count'] ?> sản phẩm</span>
                <?= (int)$category['status'] === 1 ? '<span class="badge badge-mint">Hiển thị</span>' : '<span class="badge badge-ink">Đang ẩn</span>' ?>
                <span class="ml-auto flex gap-1">
                    <a href="<?= e(url('admin/categories/edit.php?id=' . (int)$category['id'])) ?>" class="btn btn-ghost btn-sm" aria-label="Sửa <?= e($category['name']) ?>"><i class="ph-bold ph-pencil-simple" aria-hidden="true"></i></a>
                    <form action="<?= e(url('admin/categories/delete.php')) ?>" method="post" data-confirm="Bạn có chắc muốn xóa danh mục này?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
                        <button type="submit" class="btn btn-danger-ghost btn-sm" aria-label="Xóa <?= e($category['name']) ?>"><i class="ph-bold ph-trash" aria-hidden="true"></i></button>
                    </form>
                </span>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if (!$categories): ?>
        <p class="card p-10 text-center text-muted sm:col-span-2 xl:col-span-3">Chưa có danh mục nào.</p>
    <?php endif; ?>
</div>
<?= render_pagination($page, $pages) ?>

<?php require __DIR__ . '/../admin_footer.php'; ?>
