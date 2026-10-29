/* KalaSetu – small client-side helpers (no frameworks) */
document.addEventListener('DOMContentLoaded', function () {

    // 1. Ask for confirmation before any delete form is submitted
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.getAttribute('data-confirm'))) {
                e.preventDefault();
            }
        });
    });

    // 2. Bootstrap client-side validation styling for forms marked .needs-validation
    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // 3. Filter dropdowns submit automatically when changed
    document.querySelectorAll('select[data-autosubmit]').forEach(function (sel) {
        sel.addEventListener('change', function () { sel.form.submit(); });
    });

    // 4. Register page: only show skills that belong to the chosen district
    var districtSel = document.getElementById('district_id');
    var skillSel = document.getElementById('skill_id');
    if (document.getElementById('registerForm') && districtSel && skillSel) {
        var help = document.getElementById('skillHelp');
        var filterSkills = function () {
            var d = districtSel.value, visible = 0;
            Array.prototype.forEach.call(skillSel.options, function (opt) {
                if (!opt.value) { return; }                      // keep the "Choose…" option
                var match = d !== '' && opt.dataset.district === d;
                opt.hidden = !match;
                opt.disabled = !match;
                if (match) { visible++; }
            });
            var current = skillSel.options[skillSel.selectedIndex];
            if (current && current.disabled) { skillSel.value = ''; }
            skillSel.disabled = d === '';
            if (help) {
                help.textContent = d === '' ? 'Choose your district first.'
                    : visible + ' skill' + (visible === 1 ? '' : 's') + ' listed for this district.';
            }
        };
        districtSel.addEventListener('change', filterSkills);
        filterSkills();
    }

    // 6. Dropdowns inside scrollable tables (admin request list: Reject + reason)
    //    use fixed positioning so the table's overflow does not cut them off
    if (window.bootstrap) {
        document.querySelectorAll('.table-responsive [data-bs-toggle="dropdown"]').forEach(function (el) {
            bootstrap.Dropdown.getOrCreateInstance(el, {
                popperConfig: function (cfg) { return Object.assign({}, cfg, { strategy: 'fixed' }); }
            });
        });
    }

    // 5. If the interest form has server-side errors, open the modal again
    var interestModal = document.getElementById('interestModal');
    if (interestModal && interestModal.dataset.open === '1' && window.bootstrap) {
        new bootstrap.Modal(interestModal).show();
    }
});
