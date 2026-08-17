document.addEventListener("DOMContentLoaded", function () {

    const ensureArrow = (item) => {
        if (item.querySelector('.th-toggle-arrow')) return;

        const arrow = document.createElement('span');
        arrow.className = 'th-toggle-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        arrow.innerHTML = `
            <svg viewBox="0 0 24 24" width="18" height="18" focusable="false" aria-hidden="true">
                <path d="M9 18l6-6-6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
            </svg>
        `;

        // Chèn arrow vào vị trí dễ nhìn: trước text của label.
        // Moodle label thường có .contentafterlink hoặc .activityname
        const target =
            item.querySelector('.activityname') ||
            item.querySelector('.contentafterlink') ||
            item.querySelector('.contentwithoutlink') ||
            item.querySelector('.content') ||
            item;

        // Nếu target là container lớn, cố gắng chèn trước heading
        const heading = item.querySelector('h5, h6');
        if (heading && heading.parentNode) {
            heading.insertAdjacentElement('afterbegin', arrow);
        } else {
            target.insertAdjacentElement('afterbegin', arrow);
        }
    };

    document.querySelectorAll('.single-section ul.section').forEach(list => {

        const items = list.querySelectorAll('li.activity');

        let currentH5 = null;
        let currentH6 = null;

        const groupedH5 = {};              // h5 -> h6[]
        const groupedH6 = {};              // h6 -> activity[]
        const groupedH5Activities = {};    // h5 -> activity[]

        function openSequentially(elements, delay = 120) {
            elements.forEach((el, i) => {
                setTimeout(() => {
                    el.classList.remove('hidden-activity');
                }, i * delay);
            });
        }

        function closeAll(elements) {
            elements.forEach(el => el.classList.add('hidden-activity'));
        }

        const setExpanded = (item, expanded) => {
            item.classList.toggle('is-expanded', expanded);
            item.setAttribute('aria-expanded', expanded ? 'true' : 'false');
        };

        items.forEach(item => {

            if (item.classList.contains('modtype_label')) {

                const heading = item.querySelector('h5, h6');
                if (!heading) return;

                /* ===== H5 ===== */
                if (heading.tagName === 'H5') {
                    currentH5 = item;
                    currentH6 = null;

                    groupedH5[item.id] = [];
                    groupedH5Activities[item.id] = [];

                    item.classList.add('label-toggle', 'level-h5');
                    item.style.cursor = 'pointer';

                    ensureArrow(item);
                    setExpanded(item, false);

                    item.addEventListener('click', e => {
                        if (e.target.closest('.cm_action_menu')) return;

                        const h6s = groupedH5[item.id];
                        const acts = groupedH5Activities[item.id];

                        const isClosed =
                            (h6s[0] && h6s[0].classList.contains('hidden-activity')) ||
                            (acts[0] && acts[0].classList.contains('hidden-activity'));

                        if (isClosed) {
                            setExpanded(item, true);
                            openSequentially(h6s);
                            openSequentially(acts, 80);
                        } else {
                            // đóng tất cả con + reset mũi tên của H6 con
                            h6s.forEach(h6 => {
                                setExpanded(h6, false);
                                closeAll([h6]);
                                if (groupedH6[h6.id]) {
                                    closeAll(groupedH6[h6.id]);
                                }
                            });
                            closeAll(acts);
                            setExpanded(item, false);
                        }
                    });
                }

                /* ===== H6 ===== */
                if (heading.tagName === 'H6') {
                    currentH6 = item;
                    groupedH6[item.id] = [];

                    if (currentH5) {
                        groupedH5[currentH5.id].push(item);
                        item.classList.add('hidden-activity');
                    }

                    item.classList.add('label-toggle', 'level-h6');
                    item.style.cursor = 'pointer';

                    ensureArrow(item);
                    setExpanded(item, false);

                    item.addEventListener('click', e => {
                        if (e.target.closest('.cm_action_menu')) return;
                        e.stopPropagation();

                        const children = groupedH6[item.id];
                        if (!children.length) return;

                        const isClosed = children[0].classList.contains('hidden-activity');
                        if (isClosed) {
                            setExpanded(item, true);
                            openSequentially(children, 100);
                        } else {
                            setExpanded(item, false);
                            closeAll(children);
                        }
                    });
                }
            }
            /* ===== ACTIVITY ===== */
            else {
                if (currentH6) {
                    groupedH6[currentH6.id].push(item);
                    item.classList.add('hidden-activity', 'level-activity');
                }
                else if (currentH5) {
                    groupedH5Activities[currentH5.id].push(item);
                    item.classList.add('hidden-activity', 'level-activity', 'h5-child');
                }
            }

        });

    });

});
