define(['core/modal_factory', 'core/notification'], function(ModalFactory, Notification) {
    return {
        init: function () {
            const form = document.querySelector('form[id="mass_approval"]');
            if (!form) {return;}

            form.addEventListener('submit', function (e) {
                e.preventDefault(); // Luôn chặn mặc định

                const checkboxes = form.querySelectorAll('input[name="select_row[]"]:not([disabled])');
                const hasChecked = Array.from(checkboxes).some(cb => cb.checked);

                if (!hasChecked) {
                    Notification.alert(
                        'Thông báo',
                        'Vui lòng chọn ít nhất một dòng để duyệt.'
                    );
                    return;
                }

                ModalFactory.create({
                    title: 'Xác nhận duyệt',
                    body: '<p>Bạn có chắc chắn muốn duyệt các bài đã chọn không?</p>',
                    footer:
                        '<button type="button" class="btn btn-secondary" data-action="cancel">Hủy</button>' +
                        '<button type="button" class="btn btn-primary" data-action="confirm">Duyệt</button>',
                    removeOnClose: true
                }).then(modal => {
                    const root = modal.getRoot();

                    root.on('click', '[data-action="confirm"]', () => {
                        modal.hide();
                        form.submit(); // Submit lại form sau khi confirm
                    });

                    root.on('click', '[data-action="cancel"]', () => {
                        modal.hide();
                    });

                    modal.show();
                });
            });
        }
    };
});
