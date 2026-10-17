/**
 * EEMT 2027 - Dynamic Frontend Logic
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Cascading Dropdown: District -> Mandal -> School
    const districtSelect = document.getElementById('district_select');
    const mandalSelect = document.getElementById('mandal_select');
    const schoolSelect = document.getElementById('school_select');
    const uniqueCodeInput = document.getElementById('unique_code');
    const verifyStatus = document.getElementById('code_verify_status');

    // Contact Section Locking DOM Elements
    const contactSection = document.getElementById('contact_info_section');
    const contactLockBadge = document.getElementById('contact_lock_badge');
    const contactPersonInput = document.getElementById('contact_person');
    const designationSelect = document.getElementById('designation');
    const mobileInput = document.getElementById('mobile');
    const emailInput = document.getElementById('email');
    const submitBtn = document.getElementById('btn_submit_registration');
    const registrationForm = document.getElementById('registration_form');

    function lockContactFields() {
        if (contactSection) {
            contactSection.classList.remove('contact-section-unlocked');
            contactSection.classList.add('contact-section-locked');
        }
        if (contactLockBadge) {
            contactLockBadge.textContent = '🔒 Locked — Verify School Code First';
            contactLockBadge.style.background = '#f1f5f9';
            contactLockBadge.style.color = '#64748b';
            contactLockBadge.style.borderColor = '#cbd5e1';
        }
        if (contactPersonInput) contactPersonInput.disabled = true;
        if (designationSelect) designationSelect.disabled = true;
        if (mobileInput) mobileInput.disabled = true;
        if (emailInput) emailInput.disabled = true;
        if (submitBtn) submitBtn.disabled = true;
    }

    function unlockContactFields() {
        if (contactSection) {
            contactSection.classList.remove('contact-section-locked');
            contactSection.classList.add('contact-section-unlocked');
        }
        if (contactLockBadge) {
            contactLockBadge.textContent = '✓ Verified & Unlocked';
            contactLockBadge.style.background = '#dcfce7';
            contactLockBadge.style.color = '#15803d';
            contactLockBadge.style.borderColor = '#86efac';
        }
        if (contactPersonInput) contactPersonInput.disabled = false;
        if (designationSelect) designationSelect.disabled = false;
        if (mobileInput) mobileInput.disabled = false;
        if (emailInput) emailInput.disabled = false;
        if (submitBtn) submitBtn.disabled = false;

        if (contactPersonInput) {
            setTimeout(() => contactPersonInput.focus(), 150);
        }
    }

    if (districtSelect && mandalSelect) {
        districtSelect.addEventListener('change', async () => {
            const district = districtSelect.value;
            mandalSelect.innerHTML = '<option value="">Loading Mandals...</option>';
            mandalSelect.disabled = true;

            if (schoolSelect) {
                schoolSelect.innerHTML = '<option value="">Select Mandal first</option>';
                schoolSelect.disabled = true;
            }

            if (uniqueCodeInput) uniqueCodeInput.value = '';
            if (verifyStatus) {
                verifyStatus.textContent = '';
                verifyStatus.className = 'code-status';
            }
            lockContactFields();

            if (!district) {
                mandalSelect.innerHTML = '<option value="">Select District first</option>';
                return;
            }

            try {
                const res = await fetch(`api/get_mandals.php?district=${encodeURIComponent(district)}`);
                const mandals = await res.json();
                
                mandalSelect.innerHTML = '<option value="">-- Select Mandal --</option>';
                mandals.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m;
                    opt.textContent = m;
                    mandalSelect.appendChild(opt);
                });
                mandalSelect.disabled = false;
            } catch (err) {
                console.error(err);
                mandalSelect.innerHTML = '<option value="">Error loading mandals</option>';
            }
        });
    }

    if (mandalSelect && schoolSelect) {
        mandalSelect.addEventListener('change', async () => {
            const district = districtSelect.value;
            const mandal = mandalSelect.value;

            schoolSelect.innerHTML = '<option value="">Loading Schools...</option>';
            schoolSelect.disabled = true;

            if (uniqueCodeInput) uniqueCodeInput.value = '';
            if (verifyStatus) {
                verifyStatus.textContent = '';
                verifyStatus.className = 'code-status';
            }
            lockContactFields();

            if (!mandal) {
                schoolSelect.innerHTML = '<option value="">Select Mandal first</option>';
                return;
            }

            try {
                const res = await fetch(`api/get_schools.php?district=${encodeURIComponent(district)}&mandal=${encodeURIComponent(mandal)}`);
                const schools = await res.json();
                
                schoolSelect.innerHTML = '<option value="">-- Select School --</option>';
                schools.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.school_name;
                    opt.textContent = s.school_name;
                    opt.dataset.registered = s.is_registered;
                    schoolSelect.appendChild(opt);
                });
                schoolSelect.disabled = false;
            } catch (err) {
                console.error(err);
                schoolSelect.innerHTML = '<option value="">Error loading schools</option>';
            }
        });
    }

    if (uniqueCodeInput) {
        uniqueCodeInput.addEventListener('input', () => {
            lockContactFields();
            if (verifyStatus && verifyStatus.classList.contains('valid')) {
                verifyStatus.textContent = '';
                verifyStatus.className = 'code-status';
            }
        });
    }

    if (schoolSelect) {
        // Reset code input, verification status, and lock contact section when school changes
        schoolSelect.addEventListener('change', () => {
            if (uniqueCodeInput) {
                uniqueCodeInput.value = '';
            }
            if (verifyStatus) {
                verifyStatus.textContent = '';
                verifyStatus.className = 'code-status';
            }
            lockContactFields();
        });
    }

    // 2. School Unique Code Check
    const verifyCodeBtn = document.getElementById('btn_verify_code');
    if (verifyCodeBtn && uniqueCodeInput) {
        verifyCodeBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const code = uniqueCodeInput.value.trim();
            const district = districtSelect ? districtSelect.value : '';
            const mandal = mandalSelect ? mandalSelect.value : '';
            const school = schoolSelect ? schoolSelect.value : '';

            if (!district || !mandal || !school) {
                lockContactFields();
                showCodeStatus('Please select District, Mandal, and School first.', 'invalid');
                return;
            }

            if (!code) {
                lockContactFields();
                showCodeStatus('Please enter your School Unique Code or UDISE Code.', 'invalid');
                return;
            }

            verifyCodeBtn.textContent = 'Verifying...';
            verifyCodeBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('code', code);
                formData.append('district', district);
                formData.append('mandal', mandal);
                formData.append('school', school);

                const res = await fetch('api/verify_code.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await res.json();

                if (result.valid) {
                    if (result.already_registered) {
                        lockContactFields();
                        showCodeStatus(`Notice: Already registered with Ref ID: ${result.reference_id}. You can log in directly!`, 'invalid');
                    } else {
                        unlockContactFields();
                        showCodeStatus(`✓ ${result.message}`, 'valid');
                    }
                } else {
                    lockContactFields();
                    showCodeStatus(`✗ ${result.message}`, 'invalid');
                }
            } catch (err) {
                console.error(err);
                lockContactFields();
                showCodeStatus('Server connection error while verifying code.', 'invalid');
            } finally {
                verifyCodeBtn.textContent = 'Verify Code';
                verifyCodeBtn.disabled = false;
            }
        });
    }

    // Digits only enforcement for Unique Code (11 digits)
    if (uniqueCodeInput) {
        uniqueCodeInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11);
        });
    }

    // Digits only enforcement for Mobile Number (10 digits)
    if (mobileInput) {
        mobileInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10);
        });
    }

    if (registrationForm) {
        registrationForm.addEventListener('submit', (e) => {
            if (contactPersonInput && contactPersonInput.disabled) {
                e.preventDefault();
                alert('Please enter and verify your School Code first.');
                return false;
            }

            // Validate Unique Code: exactly 11 digits
            if (uniqueCodeInput) {
                const uVal = uniqueCodeInput.value.trim();
                if (!/^[0-9]{11}$/.test(uVal)) {
                    e.preventDefault();
                    alert('School UDISE Code must be exactly 11 numeric digits (no alphabets).');
                    uniqueCodeInput.focus();
                    return false;
                }
            }

            // Validate Mobile: exactly 10 digits
            if (mobileInput) {
                const mVal = mobileInput.value.trim();
                if (!/^[0-9]{10}$/.test(mVal)) {
                    e.preventDefault();
                    alert('Mobile Number must be exactly 10 numeric digits.');
                    mobileInput.focus();
                    return false;
                }
            }

            // Validate Email: must contain @ and valid domain
            if (emailInput) {
                const eVal = emailInput.value.trim();
                if (!eVal.includes('@') || !eVal.includes('.') || eVal.indexOf('@') > eVal.lastIndexOf('.')) {
                    e.preventDefault();
                    alert('Please enter a valid email address with "@" and domain (e.g., school@gmail.com).');
                    emailInput.focus();
                    return false;
                }
            }

            if (contactPersonInput) contactPersonInput.disabled = false;
            if (designationSelect) designationSelect.disabled = false;
            if (mobileInput) mobileInput.disabled = false;
            if (emailInput) emailInput.disabled = false;
            if (submitBtn) submitBtn.disabled = false;
        });
    }

    function showCodeStatus(msg, statusClass) {
        if (!verifyStatus) return;
        verifyStatus.textContent = msg;
        verifyStatus.className = 'code-status ' + statusClass;
    }

    // 3. Tab Switcher (Login Page)
    window.switchLoginTab = function(tabName) {
        const schoolTab = document.getElementById('tab-school');
        const staffTab = document.getElementById('tab-staff');
        const schoolForm = document.getElementById('form-school');
        const staffForm = document.getElementById('form-staff');

        if (tabName === 'school') {
            schoolTab?.classList.add('active');
            staffTab?.classList.remove('active');
            if (schoolForm) schoolForm.style.display = 'block';
            if (staffForm) staffForm.style.display = 'none';
        } else {
            staffTab?.classList.add('active');
            schoolTab?.classList.remove('active');
            if (staffForm) staffForm.style.display = 'block';
            if (schoolForm) schoolForm.style.display = 'none';
        }
    };

    // 4. Copy to Clipboard
    window.copyRefId = function(text, btnElement) {
        navigator.clipboard.writeText(text).then(() => {
            const originalText = btnElement.innerHTML;
            btnElement.innerHTML = '✓ Copied!';
            btnElement.style.background = '#10b981';
            btnElement.style.color = '#ffffff';
            setTimeout(() => {
                btnElement.innerHTML = originalText;
                btnElement.style.background = '';
                btnElement.style.color = '';
            }, 2000);
        });
    };
});
