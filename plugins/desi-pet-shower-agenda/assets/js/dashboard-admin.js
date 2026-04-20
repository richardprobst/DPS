(() => {
    'use strict';

    const config = window.DPS_Agenda_Admin;

    if (!config || !document.body.classList.contains('toplevel_page_desi-pet-shower') && !document.body.classList.contains('desi-pet-shower_page_dps-agenda-hub') && !document.body.classList.contains('desi-pet-shower_page_dps-agenda-dashboard')) {
        return;
    }

    const feedbackSelector = '.dps-capacity-feedback';

    function setFeedback(message, type = 'info') {
        const feedback = document.querySelector(feedbackSelector);

        if (!feedback) {
            return;
        }

        feedback.hidden = false;
        feedback.className = `dps-agenda-admin-notice dps-capacity-feedback dps-agenda-admin-notice--${type}`;
        feedback.textContent = message;
    }

    function bindQuickDateButtons() {
        document.querySelectorAll('.dps-dashboard-quick-date').forEach((button) => {
            button.addEventListener('click', () => {
                const days = Number.parseInt(button.dataset.days || '0', 10);
                const dateInput = document.querySelector('.dps-dashboard-date-input');
                const form = document.querySelector('.dps-dashboard-form');

                if (!dateInput || !form || Number.isNaN(days)) {
                    return;
                }

                const today = new Date();
                today.setDate(today.getDate() + days);

                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');

                dateInput.value = `${year}-${month}-${day}`;
                form.submit();
            });
        });
    }

    function bindCapacityForm() {
        const form = document.querySelector('#dps-capacity-config-form');

        if (!form || !config.ajaxUrl || !config.nonceCapacity) {
            return;
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const morningInput = form.querySelector('#capacity_morning');
            const afternoonInput = form.querySelector('#capacity_afternoon');
            const submitButton = form.querySelector('button[type="submit"]');

            if (!morningInput || !afternoonInput || !submitButton) {
                return;
            }

            const originalLabel = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = config.messages.saving;
            setFeedback(config.messages.saving, 'info');

            const payload = new URLSearchParams({
                action: 'dps_agenda_save_capacity',
                nonce: config.nonceCapacity,
                morning: morningInput.value,
                afternoon: afternoonInput.value,
            });

            try {
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    },
                    body: payload.toString(),
                    credentials: 'same-origin',
                });

                const raw = await response.text();
                const cleaned = raw.replace(/^\uFEFF+/, '').trim();
                const data = JSON.parse(cleaned);

                if (!data || !data.success) {
                    const message = data && data.data && data.data.message ? data.data.message : config.messages.error;
                    throw new Error(message);
                }

                submitButton.textContent = config.messages.saved;
                setFeedback(config.messages.saved, 'success');
                window.setTimeout(() => window.location.reload(), 900);
            } catch (error) {
                const message = error instanceof Error && error.message ? error.message : config.messages.error;
                submitButton.disabled = false;
                submitButton.textContent = originalLabel || config.messages.submit;
                setFeedback(message, 'warning');
            }
        });
    }

    bindQuickDateButtons();
    bindCapacityForm();
})();
