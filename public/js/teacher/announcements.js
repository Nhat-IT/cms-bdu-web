    let newsModal;

    document.addEventListener('DOMContentLoaded', function () {
        newsModal = new bootstrap.Modal(document.getElementById('newsModal'));

        document.getElementById('newsSource').addEventListener('change', function () {
            handleSourceSelection('newsSource', 'customNewsSourceWrap', 'customNewsSource');
        });

        document.getElementById('modalSourceInput').addEventListener('change', function () {
            handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');
        });

        document.getElementById('newsCategory').addEventListener('change', function () {
            handleCategorySelection('newsCategory', 'customNewsCategoryWrap', 'customNewsCategory');
        });

        document.getElementById('modalCategoryInput').addEventListener('change', function () {
            handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
        });

        document.getElementById('publishNow').addEventListener('change', function () {
            handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
        });

        document.getElementById('publishSchedule').addEventListener('change', function () {
            handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
        });

        document.getElementById('modalPublishNow').addEventListener('change', function () {
            handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
        });

        document.getElementById('modalPublishSchedule').addEventListener('change', function () {
            handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
        });

        handleSourceSelection('newsSource', 'customNewsSourceWrap', 'customNewsSource');
        handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');
        handleCategorySelection('newsCategory', 'customNewsCategoryWrap', 'customNewsCategory');
        handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
        handlePublishMode('publishNow', 'schedulePublishWrap', ['schedulePublishDate', 'schedulePublishTime']);
        handlePublishMode('modalPublishNow', 'modalSchedulePublishWrap', ['modalSchedulePublishDate', 'modalSchedulePublishTime']);
    });

    function handleSourceSelection(selectId, wrapId, inputId) {
        const sourceSelect = document.getElementById(selectId);
        const customWrap = document.getElementById(wrapId);
        const customInput = document.getElementById(inputId);
        const isCustom = sourceSelect.value === 'Khac';

        customWrap.classList.toggle('d-none', !isCustom);
        customInput.required = isCustom;

        if (!isCustom) {
            customInput.value = '';
        }
    }

    function handleCategorySelection(selectId, wrapId, inputId) {
        const categorySelect = document.getElementById(selectId);
        const customWrap = document.getElementById(wrapId);
        const customInput = document.getElementById(inputId);
        const isCustom = categorySelect.value === 'Khac';

        customWrap.classList.toggle('d-none', !isCustom);
        customInput.required = isCustom;

        if (!isCustom) {
            customInput.value = '';
        }
    }

    function handlePublishMode(nowRadioId, wrapId, inputIds) {
        const isNow = document.getElementById(nowRadioId).checked;
        const scheduleWrap = document.getElementById(wrapId);
        const scheduleInputs = inputIds.map(id => document.getElementById(id));

        scheduleWrap.classList.toggle('d-none', isNow);
        scheduleInputs.forEach(input => {
            input.required = !isNow;
        });

        if (isNow) {
            scheduleInputs.forEach(input => {
                input.value = '';
            });
        }
    }

    function confirmAction(actionText, onConfirm) {
        if (confirm('Bạn có chắc chắn muốn ' + actionText + '?')) {
            onConfirm();
        }
    }

    function saveDraft() {
        confirmAction('lưu bản tin vào nháp', function () {
            alert('Đã lưu bản tin vào nháp.');
        });
    }

    function formatScheduleDateTimeVi(dateStr, timeStr) {
        const [year, month, day] = dateStr.split('-');
        return day + '/' + month + '/' + year + ' ' + timeStr;
    }

    function publishNews() {
        confirmAction('đăng bản tin và đẩy thông báo đến sinh viên', function () {
            const isPinned = document.getElementById('pinPost').checked;
            const isNow = document.getElementById('publishNow').checked;
            const scheduleDate = document.getElementById('schedulePublishDate').value;
            const scheduleTime = document.getElementById('schedulePublishTime').value;
            const pinText = isPinned ? ' Bài đăng đã được ghim.' : '';

            if (!isNow && (!scheduleDate || !scheduleTime)) {
                alert('Vui lòng chọn đầy đủ ngày và giờ đăng.');
                return;
            }

            const publishText = isNow
                ? 'Đã đăng bản tin và đẩy thông báo đến sinh viên.'
                : 'Đã hẹn đăng bản tin vào: ' + formatScheduleDateTimeVi(scheduleDate, scheduleTime) + '.';

            alert(publishText + pinText);
        });
    }

    function togglePin(title) {
        confirmAction('ghim bài đăng "' + title + '"', function () {
            alert('Đã ghim bài đăng: ' + title);
        });
    }

    function confirmDelete(title) {
        confirmAction('xóa bản tin "' + title + '"', function () {
            alert('Đã xóa bản tin: ' + title);
        });
    }

    function openViewModal(title) {
        alert('Mở chi tiết bản tin: ' + title);
    }

    function openEditModal(title, source, scope, category, content) {
        document.getElementById('newsModalTitle').innerHTML = '<i class="bi bi-pencil-square me-2"></i>Chỉnh sửa bản tin';
        document.getElementById('modalTitleInput').value = title;

        const modalSource = document.getElementById('modalSourceInput');
        const knownSources = Array.from(modalSource.options).map(option => option.value);

        if (knownSources.includes(source)) {
            modalSource.value = source;
            document.getElementById('customModalSource').value = '';
        } else {
            modalSource.value = 'Khac';
            document.getElementById('customModalSource').value = source;
        }

        handleSourceSelection('modalSourceInput', 'customModalSourceWrap', 'customModalSource');

        const modalCategory = document.getElementById('modalCategoryInput');
        const knownCategories = Array.from(modalCategory.options).map(option => option.value);

        if (knownCategories.includes(category)) {
            modalCategory.value = category;
            document.getElementById('customModalCategory').value = '';
        } else {
            modalCategory.value = 'Khac';
            document.getElementById('customModalCategory').value = category;
        }

        handleCategorySelection('modalCategoryInput', 'customModalCategoryWrap', 'customModalCategory');
        document.getElementById('modalScopeInput').value = scope;
        document.getElementById('modalContentInput').value = content;
        newsModal.show();
    }
