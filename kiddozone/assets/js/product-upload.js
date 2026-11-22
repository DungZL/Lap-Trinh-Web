document.addEventListener('DOMContentLoaded', function () {
    const input = document.getElementById('productImages');
    const zone = document.getElementById('uploadZone');
    const preview = document.getElementById('imagePreview');
    const counter = document.getElementById('imageCount');
    const form = document.getElementById('productForm');

    if (!input || !zone || !preview || !counter) {
        console.error('Không tìm thấy các phần tử upload ảnh');
        return;
    }

    let selectedFiles = [];
    let compressingCount = 0;
    const maxFiles = 10;
    const targetMaxBytes = 2 * 1024 * 1024;
    const encodeGoalBytes = Math.floor(1.9 * 1024 * 1024);
    const maxRawSize = 15 * 1024 * 1024;
    const allowedDirectTypes = ['image/jpeg', 'image/png', 'image/webp'];
    const allowedInputTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    function getExistingCount() {
        return document.querySelectorAll('[data-existing-image]').length;
    }

    function getMaxNewFiles() {
        return Math.max(0, maxFiles - getExistingCount());
    }

    function formatSize(bytes) {
        if (bytes < 1024 * 1024) {
            return Math.max(1, Math.round(bytes / 1024)) + ' KB';
        }
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function detectImageType(file) {
        const mime = (file.type || '').toLowerCase();
        if (mime === 'image/jpg') return 'image/jpeg';
        if (allowedInputTypes.includes(mime)) return mime;
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ext === 'jpg' || ext === 'jpeg') return 'image/jpeg';
        if (ext === 'png') return 'image/png';
        if (ext === 'webp') return 'image/webp';
        if (ext === 'avif') return 'image/avif';
        return '';
    }

    function setBusyState(busy) {
        if (!form) return;
        form.querySelectorAll('button[type="submit"]').forEach(function (btn) {
            btn.disabled = busy;
        });
    }

    function updateCounter() {
        const existingCount = getExistingCount();
        if (compressingCount > 0) {
            counter.textContent = `Đang nén ảnh (${compressingCount} ảnh còn lại)...`;
            return;
        }
        counter.textContent = existingCount > 0
            ? `Đã chọn ${selectedFiles.length} ảnh mới (tổng ${existingCount + selectedFiles.length}/${maxFiles} ảnh)`
            : `Đã chọn ${selectedFiles.length}/${maxFiles} ảnh`;
    }

    async function decodeImage(file) {
        if (typeof createImageBitmap === 'function') {
            try {
                const bmp = await createImageBitmap(file);
                return {
                    source: bmp,
                    width: bmp.width,
                    height: bmp.height,
                    close: function () { if (typeof bmp.close === 'function') bmp.close(); }
                };
            } catch (e) {
                // Dự phòng bằng thẻ <img> bên dưới
            }
        }
        return await new Promise(function (resolve, reject) {
            const url = URL.createObjectURL(file);
            const img = new Image();
            img.onload = function () {
                URL.revokeObjectURL(url);
                resolve({
                    source: img,
                    width: img.naturalWidth || img.width,
                    height: img.naturalHeight || img.height,
                    close: function () {}
                });
            };
            img.onerror = function () {
                URL.revokeObjectURL(url);
                reject(new Error('Không đọc được ảnh: ' + file.name));
            };
            img.src = url;
        });
    }

    function canvasToBlob(canvas, mime, quality) {
        return new Promise(function (resolve) {
            canvas.toBlob(function (blob) { resolve(blob); }, mime, quality);
        });
    }

    async function compressImageFile(file, sourceType) {
        const decoded = await decodeImage(file);
        const w = decoded.width;
        const h = decoded.height;
        if (!w || !h) {
            decoded.close();
            throw new Error('Ảnh không hợp lệ: ' + file.name);
        }

        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        if (!ctx) {
            decoded.close();
            throw new Error('Trình duyệt không hỗ trợ xử lý ảnh.');
        }

        let outMime = sourceType === 'image/jpeg' ? 'image/jpeg' : 'image/webp';
        let bestBlob = null;

        try {
            const maxDims = [1920, 1440, 1080, 800];
            const qualities = [0.88, 0.8, 0.72, 0.62, 0.52];

            for (const maxDim of maxDims) {
                const scale = Math.min(1, maxDim / Math.max(w, h));
                const dstW = Math.max(1, Math.round(w * scale));
                const dstH = Math.max(1, Math.round(h * scale));
                canvas.width = dstW;
                canvas.height = dstH;

                const drawFrame = function (fillWhite) {
                    ctx.clearRect(0, 0, dstW, dstH);
                    if (fillWhite) {
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, dstW, dstH);
                    }
                    ctx.drawImage(decoded.source, 0, 0, dstW, dstH);
                };

                drawFrame(outMime === 'image/jpeg' && sourceType !== 'image/jpeg');

                for (const q of qualities) {
                    let blob = await canvasToBlob(canvas, outMime, q);
                    // Trình duyệt không hỗ trợ xuất WebP từ canvas: chuyển sang JPEG
                    if (outMime === 'image/webp' && (!blob || blob.type !== 'image/webp')) {
                        outMime = 'image/jpeg';
                        drawFrame(sourceType !== 'image/jpeg');
                        blob = await canvasToBlob(canvas, outMime, q);
                    }
                    if (!blob) continue;

                    if (blob.size <= targetMaxBytes && (!bestBlob || blob.size < bestBlob.size)) {
                        bestBlob = blob;
                    }
                    if (blob.size <= encodeGoalBytes) {
                        bestBlob = blob;
                        break;
                    }
                }
                if (bestBlob && bestBlob.size <= encodeGoalBytes) {
                    break;
                }
            }
        } finally {
            decoded.close();
            canvas.width = 0;
            canvas.height = 0;
        }

        if (!bestBlob || bestBlob.size > targetMaxBytes) {
            throw new Error('Không thể nén ảnh xuống dưới 2MB: ' + file.name);
        }

        const ext = bestBlob.type === 'image/webp' ? '.webp' : '.jpg';
        const baseName = file.name.replace(/\.[^.]+$/, '') || 'image';
        return new File([bestBlob], baseName + ext, {
            type: bestBlob.type,
            lastModified: file.lastModified
        });
    }

    if (form) {
        form.addEventListener('submit', function (event) {
            if (compressingCount > 0) {
                event.preventDefault();
                alert('Đang nén ảnh, vui lòng đợi trong giây lát.');
            }
        });
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
        addFiles(Array.from(e.dataTransfer.files));
    });

    input.addEventListener('change', function () {
        const newFiles = Array.from(input.files);
        addFiles(newFiles);
    });

    async function addFiles(newFiles) {
        const maxNew = getMaxNewFiles();
        const queue = [];

        for (const file of newFiles) {
            const detectedType = detectImageType(file);
            if (!detectedType) {
                alert('File không hợp lệ: ' + file.name);
                continue;
            }
            if (file.size > maxRawSize) {
                alert('Ảnh gốc vượt quá 15MB: ' + file.name);
                continue;
            }

            const origKey = file.name + '|' + file.size + '|' + file.lastModified;
            if (selectedFiles.some(entry => entry.origKey === origKey) || queue.some(q => q.origKey === origKey)) {
                continue;
            }
            if (selectedFiles.length + queue.length >= maxNew) {
                alert(getExistingCount() > 0
                    ? `Mỗi sản phẩm chỉ được có tối đa ${maxFiles} ảnh (hiện đã có ${getExistingCount()} ảnh cũ).`
                    : `Chỉ được chọn tối đa ${maxFiles} ảnh.`);
                break;
            }

            queue.push({ file: file, origKey: origKey, type: detectedType });
        }

        if (!queue.length) {
            updateInput();
            renderPreview();
            return;
        }

        compressingCount += queue.length;
        setBusyState(true);
        updateCounter();

        for (const item of queue) {
            try {
                if (selectedFiles.length >= getMaxNewFiles()) {
                    break;
                }
                const needsCompress = !allowedDirectTypes.includes(item.type) || item.file.size > targetMaxBytes;
                if (!needsCompress) {
                    selectedFiles.push({
                        file: item.file,
                        origKey: item.origKey,
                        compressed: false,
                        origSize: item.file.size
                    });
                } else {
                    const outFile = await compressImageFile(item.file, item.type);
                    selectedFiles.push({
                        file: outFile,
                        origKey: item.origKey,
                        compressed: true,
                        origSize: item.file.size
                    });
                }
            } catch (err) {
                alert(err && err.message ? err.message : ('Không thể xử lý ảnh: ' + item.file.name));
            } finally {
                compressingCount = Math.max(0, compressingCount - 1);
                updateInput();
                renderPreview();
            }
        }

        if (compressingCount === 0) {
            setBusyState(false);
        }
    }

    function updateInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(entry => dt.items.add(entry.file));
        input.files = dt.files;
    }

    function renderPreview() {
        const existingCount = getExistingCount();
        preview.innerHTML = '';

        selectedFiles.forEach((entry, index) => {
            const file = entry.file;
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

            if (entry.compressed) {
                const badge = document.createElement('span');
                badge.className = 'absolute top-1.5 left-1.5 rounded-full bg-mint-600 px-2 py-0.5 text-[10px] font-extrabold text-white shadow';
                badge.textContent = 'Đã nén ' + formatSize(file.size);
                badge.title = `Đã nén từ ${formatSize(entry.origSize)} còn ${formatSize(file.size)}`;
                item.appendChild(badge);
            }

            if (existingCount === 0 && index === 0) {
                const label = document.createElement('span');
                label.className = 'absolute inset-x-1.5 bottom-1.5 rounded-full bg-sky-600 py-1 text-center text-[11px] font-bold text-white';
                label.textContent = 'Ảnh đại diện';
                item.appendChild(label);
            }

            preview.appendChild(item);
        });

        updateCounter();
    }

    document.addEventListener('kz:existing-images-changed', renderPreview);
    renderPreview();
});
