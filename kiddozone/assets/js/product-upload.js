
document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('productImages');
    const zone = document.getElementById('uploadZone');
    const preview = document.getElementById('imagePreview');
    const counter = document.getElementById('imageCount');

    if (!input || !zone || !preview || !counter) {
        console.error('Không tìm thấy các phần tử upload ảnh');
        return;
    }

    let selectedFiles = [];
    const maxFiles = 10;
    const maxSize = 2 * 1024 * 1024;

    function getExistingCount() {
        return document.querySelectorAll('[data-existing-image]').length;
    }

    function getMaxNewFiles() {
        return Math.max(0, maxFiles - getExistingCount());
    }

    zone.addEventListener('click', () => input.click());

    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        zone.classList.add('dragover');
    });

    zone.addEventListener('dragleave', function () {
        zone.classList.remove('dragover');
    });

    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        zone.classList.remove('dragover');
        addFiles(e.dataTransfer.files);
    });

    input.addEventListener('change', function () {
        const newFiles = Array.from(input.files);
        addFiles(newFiles);
    });

    function addFiles(newFiles) {
        const maxNew = getMaxNewFiles();
        for (const file of Array.from(newFiles)) {
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                alert('File không hợp lệ: ' + file.name);
                continue;
            }

            if (file.size > maxSize) {
                alert('Ảnh vượt quá 2MB: ' + file.name);
                continue;
            }

            if (selectedFiles.some(f =>
                f.name === file.name &&
                f.size === file.size &&
                f.lastModified === file.lastModified
            )) continue;

            if (selectedFiles.length >= maxNew) {
                alert(getExistingCount() > 0
                    ? `Mỗi sản phẩm chỉ được có tối đa ${maxFiles} ảnh (hiện đã có ${getExistingCount()} ảnh cũ).`
                    : `Chỉ được chọn tối đa ${maxFiles} ảnh.`);
                break;
            }

            selectedFiles.push(file);
        }

        updateInput();
        renderPreview();
    }

    function updateInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        input.files = dt.files;
    }

    function renderPreview() {
        const existingCount = getExistingCount();
        preview.innerHTML = '';

        selectedFiles.forEach((file, index) => {
            const item = document.createElement('div');
            item.className = 'product-preview-item';
item.style.cssText = `
    position: relative;
    width: 100%;
    min-width: 0;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    border-radius: 10px;
`;
            const img = document.createElement('img');
            const objectUrl = URL.createObjectURL(file);
            img.src = objectUrl;
            img.style.cssText = `
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
`;
            img.alt = file.name;
            img.onload = () => URL.revokeObjectURL(objectUrl);


const remove = document.createElement('button');
remove.type = 'button';
remove.className = 'product-image-remove';
remove.textContent = '×';
remove.title = 'Xóa ảnh này';

// Ép nút X hiển thị trên ảnh
remove.style.cssText = `
    position: absolute;
    top: 6px;
    right: 6px;
    width: 26px;
    height: 26px;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: #dc3545;
    color: #ffffff;
    font-size: 20px;
    font-weight: bold;
    line-height: 26px;
    text-align: center;
    cursor: pointer;
    z-index: 999;
    display: flex;
    align-items: center;
    justify-content: center;
`;

remove.addEventListener('click', function (e) {
    e.preventDefault();
    e.stopPropagation();

    selectedFiles.splice(index, 1);
    updateInput();
    renderPreview();
});

            item.appendChild(img);
            item.appendChild(remove);

            if (existingCount === 0 && index === 0) {
                const label = document.createElement('span');
                label.className = 'product-main-label';
                label.textContent = 'Ảnh đại diện';
                item.appendChild(label);
            }

            preview.appendChild(item);
        });

        counter.textContent = existingCount > 0
            ? `Đã chọn ${selectedFiles.length} ảnh mới (tổng ${existingCount + selectedFiles.length}/${maxFiles} ảnh)`
            : `Đã chọn ${selectedFiles.length}/${maxFiles} ảnh`;
    }

    document.addEventListener('kz:existing-images-changed', renderPreview);
    renderPreview();

    console.log('Product upload initialized');
});
