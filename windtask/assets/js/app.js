(function ($) {
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    function toast(icon, title) {
        return Swal.fire({ toast: true, icon, title, position: 'bottom-end', timer: 2500, showConfirmButton: false });
    }

    function serializeFilters() {
        return $('#taskFilters').serialize();
    }

    function loadTasks() {
        if (!$('#tasksTable').length) return;
        $.get('api/tasks.php', serializeFilters(), function (res) {
            const rows = res.data || [];
            const tbody = $('#tasksTable tbody');
            tbody.empty();
            rows.forEach(task => {
                const badge = `<span class="badge bg-info text-dark text-capitalize">${task.status.replace('_', ' ')}</span>`;
                const priorityClass = task.priority === 'critical' ? 'bg-danger'
                    : task.priority === 'high' ? 'bg-warning text-dark'
                        : task.priority === 'medium' ? 'bg-primary'
                            : 'bg-secondary';
                const priority = `<span class="badge ${priorityClass} text-uppercase">${task.priority}</span>`;
                const deadline = task.deadline || '—';
                tbody.append(`
                    <tr data-task-id="${task.id}">
                        <td>
                            <a href="task.php?id=${task.id}" class="fw-semibold text-decoration-none">${task.title}</a>
                            <div class="small text-muted">Oluşturan: ${task.creator_name || '-'}</div>
                        </td>
                        <td>${badge}</td>
                        <td>${priority}</td>
                        <td>${task.assignee_name || '—'}</td>
                        <td>${deadline}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary quick-update" data-id="${task.id}" data-status="${task.status}">
                                Güncelle
                            </button>
                        </td>
                    </tr>
                `);
            });
            $('#taskCount').text(`${rows.length} görev`);
            renderKanban(rows);
        });
    }

    function renderKanban(tasks) {
        $('.kanban-items').empty();
        tasks.forEach(task => {
            const card = $(`
                <div class="kanban-card" draggable="true" data-task-id="${task.id}">
                    <div class="fw-semibold">${task.title}</div>
                    <div class="small text-muted">${task.assignee_name || 'Atanmadı'}</div>
                </div>
            `);
            $(`#kanban-${task.status}`).append(card);
        });
        initDrag();
    }

    function initDrag() {
        $('.kanban-card').on('dragstart', function (e) {
            $(this).addClass('dragging');
            e.originalEvent.dataTransfer.setData('text/plain', $(this).data('task-id'));
        }).on('dragend', function () {
            $(this).removeClass('dragging');
        });

        $('.kanban-items').on('dragover', function (e) {
            e.preventDefault();
            $(this).addClass('bg-light');
        }).on('dragleave', function () {
            $(this).removeClass('bg-light');
        }).on('drop', function (e) {
            e.preventDefault();
            $(this).removeClass('bg-light');
            const taskId = e.originalEvent.dataTransfer.getData('text/plain');
            const status = $(this).closest('.kanban-column').data('status');
            $(this).append($(`.kanban-card[data-task-id="${taskId}"]`));
            $.post('api/tasks.php', {
                action: 'kanban_move',
                task_id: taskId,
                status,
                csrf_token: csrfToken
            });
        });
    }

    function loadLiveTimeline() {
        if (!$('#liveTimeline').length) return;
        $.get('api/tasks.php', { type: 'live' }, function (res) {
            const list = $('#liveTimeline').empty();
            (res.data || []).forEach(item => {
                list.append(`<li class="mb-2"><div class="small text-muted">${item.created_at}</div><div>${item.detail}</div></li>`);
            });
        });
    }

    function loadComments() {
        const chat = $('#taskChat');
        if (!chat.length) return;
        const taskId = chat.data('task-id');
        $.get('api/comment.php', { task_id: taskId }, function (res) {
            chat.empty();
            const data = res.data || [];
            if (!data.length) {
                chat.append('<p class="text-muted">Henüz mesaj yok.</p>');
            }
            data.forEach(comment => {
                const bubbleClass = Number(comment.user_id) === window.currentUserId ? 'chat-bubble me' : 'chat-bubble';
                chat.append(`
                    <div class="${bubbleClass}">
                        <div class="d-flex justify-content-between">
                            <strong>${comment.user_name}</strong>
                            <small class="text-muted">${comment.created_at}</small>
                        </div>
                        <div>${comment.message}</div>
                    </div>
                `);
            });
            chat.scrollTop(chat.prop('scrollHeight'));
        });
    }

    function initCommentForm() {
        const form = $('#commentForm');
        if (!form.length) return;
        const progress = $('<div class="progress mt-2 d-none"><div class="progress-bar" role="progressbar"></div></div>').insertAfter(form.find('input[type="file"]'));
        form.on('submit', function (e) {
            e.preventDefault();
            const fd = new FormData(this);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'api/comment.php');
            xhr.upload.addEventListener('progress', function (evt) {
                if (evt.lengthComputable) {
                    const percent = Math.round((evt.loaded / evt.total) * 100);
                    progress.removeClass('d-none').find('.progress-bar').css('width', percent + '%');
                }
            });
            xhr.onload = function () {
                progress.addClass('d-none').find('.progress-bar').css('width', '0%');
                if (xhr.status === 200) {
                    form[0].reset();
                    loadComments();
                    toast('success', 'Mesaj gönderildi');
                } else {
                    toast('error', 'Mesaj gönderilemedi');
                }
            };
            xhr.send(fd);
        });
    }

    function initChecklist() {
        const form = $('#checklistForm');
        if (!form.length) return;
        form.on('submit', function (e) {
            e.preventDefault();
            $.post('api/tasks.php', {
                action: 'checklist_add',
                task_id: form.find('[name="task_id"]').val(),
                label: form.find('[name="label"]').val(),
                csrf_token: csrfToken
            }, function (res) {
                form[0].reset();
                renderChecklist(res.items || []);
                toast('success', 'Madde eklendi');
            }, 'json');
        });
        $('#checklist').on('change', '.checklist-toggle', function () {
            const itemId = $(this).data('item');
            $.post('api/tasks.php', {
                action: 'checklist_toggle',
                item_id: itemId,
                is_done: $(this).is(':checked') ? 1 : 0,
                csrf_token: csrfToken
            });
        });
    }

    function renderChecklist(items) {
        const list = $('#checklist').empty();
        items.forEach(item => {
            list.append(`
                <li class="list-group-item d-flex align-items-center justify-content-between">
                    <div>
                        <input class="form-check-input me-2 checklist-toggle" type="checkbox" data-item="${item.id}" ${item.is_done ? 'checked' : ''}>
                        <span class="${item.is_done ? 'text-decoration-line-through text-muted' : ''}">${item.label}</span>
                    </div>
                    <small class="text-muted">${item.created_at}</small>
                </li>
            `);
        });
        const done = items.filter(item => Number(item.is_done) === 1).length;
        const displayTotal = items.length;
        const total = displayTotal || 1;
        const percent = Math.round((done / total) * 100);
        $('#checklistStats').text(`Tamamlanan: ${done}/${displayTotal} (%${percent})`);
        $('#checklistProgressBar').css('width', percent + '%');
    }

    function initStatusForm() {
        const form = $('#statusUpdateForm');
        if (!form.length) return;
        form.on('submit', function (e) {
            e.preventDefault();
            $.post('api/tasks.php', {
                action: 'update_status',
                task_id: form.data('task-id'),
                status: form.find('select[name="status"]').val(),
                csrf_token: csrfToken
            }, function () {
                toast('success', 'Durum güncellendi');
                loadTasks();
            }).fail(() => toast('error', 'Durum güncellenemedi'));
        });
    }

    function initFilters() {
        applyQueryFilters();
        $('#taskFilters input, #taskFilters select').on('change', loadTasks);
        $('#resetFilters').on('click', function () {
            $('#taskFilters')[0].reset();
            loadTasks();
        });
        $('#globalSearchForm').on('submit', function (e) {
            e.preventDefault();
            const query = $(this).find('input').val();
            $('#taskFilters [name="query"]').val(query);
            loadTasks();
        });
    }

    function applyQueryFilters() {
        if (!$('#taskFilters').length) return;
        const params = new URLSearchParams(window.location.search);
        params.forEach((value, key) => {
            const el = $(`#taskFilters [name="${key}"]`);
            if (el.length) {
                el.val(value);
            }
        });
    }

    function initTheme() {
        const html = document.documentElement;
        const saved = document.cookie.split('; ').find(row => row.startsWith('theme='));
        const theme = saved ? saved.split('=')[1] : 'light';
        html.setAttribute('data-theme', theme);
        toggleThemeIcon(theme);
        $('#themeToggle').on('click', () => {
            const current = html.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', current);
            document.cookie = `theme=${current}; path=/; max-age=${60 * 60 * 24 * 30}`;
            toggleThemeIcon(current);
        });
    }

    function toggleThemeIcon(theme) {
        if (!$('#themeToggle').length) return;
        $('#themeToggle .light-icon').toggleClass('d-none', theme === 'dark');
        $('#themeToggle .dark-icon').toggleClass('d-none', theme === 'light');
    }

    function initSidebarToggle() {
        $('#sidebarToggle').on('click', function () {
            $('.sidebar').toggleClass('open');
        });
    }

    function initTaskCreateForm() {
        const form = $('#createTaskForm');
        if (!form.length) return;
        const progress = $('#uploadProgress');
        form.on('submit', function (e) {
            if (!form.data('ajax')) {
                e.preventDefault();
                const fd = new FormData(this);
                $.ajax({
                    url: 'task_create.php',
                    method: 'POST',
                    data: fd,
                    processData: false,
                    contentType: false,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    xhr: function () {
                        const xhr = $.ajaxSettings.xhr();
                        xhr.upload.onprogress = function (evt) {
                            if (evt.lengthComputable) {
                                const percent = Math.round((evt.loaded / evt.total) * 100);
                                progress.removeClass('d-none').find('.progress-bar').css('width', percent + '%');
                            }
                        };
                        return xhr;
                    },
                    success: function (res) {
                        progress.addClass('d-none').find('.progress-bar').css('width', '0%');
                        if (res.success) {
                            toast('success', res.message || 'Görev oluşturuldu');
                            form[0].reset();
                            loadTasks();
                        } else {
                            toast('error', res.error || 'Hata oluştu');
                        }
                    },
                    error: function () {
                        progress.addClass('d-none').find('.progress-bar').css('width', '0%');
                        toast('error', 'Görev oluşturulamadı');
                    }
                });
            }
        });
    }

    function initLightbox() {
        $(document).on('click', '.lightbox-trigger', function () {
            const src = $(this).data('src');
            const modal = $(`
                <div class="modal fade show" style="display:block;background:rgba(0,0,0,0.8);">
                    <div class="modal-dialog modal-lg modal-dialog-centered">
                        <div class="modal-content bg-transparent border-0">
                            <img src="${src}" class="img-fluid rounded">
                            <button class="btn btn-light mt-2 close-lightbox">Kapat</button>
                        </div>
                    </div>
                </div>
            `);
            $('body').append(modal);
        });
        $(document).on('click', '.close-lightbox', function () {
            $(this).closest('.modal').remove();
        });
    }

    $(function () {
        window.currentUserId = Number($('meta[name="current-user"]').attr('content')) || null;
        initFilters();
        initTheme();
        initSidebarToggle();
        initCommentForm();
        initChecklist();
        initStatusForm();
        initTaskCreateForm();
        initLightbox();
        $(document).on('click', '.task-tag-filter', function () {
            const tagId = $(this).data('tag');
            window.location.href = `index.php?tag_id=${tagId}`;
        });
        loadTasks();
        loadLiveTimeline();
        loadComments();
        setInterval(loadLiveTimeline, 5000);
        setInterval(loadComments, 3000);
    });
})(jQuery);
