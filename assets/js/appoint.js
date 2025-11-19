(function(){
    function q(id){ return document.getElementById(id); }
    
    function setOptions(select, items, placeholder){
        select.innerHTML = '';
        var opt = document.createElement('option');
        opt.value = '';
        opt.textContent = placeholder || 'Bitte wählen…';
        select.appendChild(opt);
        items.forEach(function(it){
            var o = document.createElement('option');
            o.value = it.value || it.id;
            o.textContent = it.label || it.name;
            select.appendChild(o);
        });
    }
    
    function msg(txt, ok){
        var p = q('va_msg');
        if(!p) return;
        p.textContent = txt || '';
        p.hidden = !txt;
        p.className = 'va-msg ' + (ok ? 'ok' : 'err');
    }
    
    function goto(step){
        document.querySelectorAll('.va-step').forEach(function(el){
            el.hidden = el.getAttribute('data-step') !== String(step);
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
    
    function enable(el, yes){ 
        if(el){ el.disabled = !yes; } 
    }

    // Calendar Widget with availability checking
    function CalendarWidget(containerId, onDateSelect){
        this.container = document.getElementById(containerId);
        this.currentMonth = new Date();
        this.currentMonth.setDate(1);
        this.selectedDate = null;
        this.onDateSelect = onDateSelect;
        this.minAdvanceDays = parseInt(ValcodeAppoint.minAdvanceDays || '0', 10);
        this.availabilityCache = {};
        this.serviceId = null;
        this.workerId = null;
        
        this.render();
    }
    
    CalendarWidget.prototype.setService = function(serviceId, workerId){
        this.serviceId = serviceId;
        this.workerId = workerId;
        this.availabilityCache = {};
        this.render();
    };
    
    CalendarWidget.prototype.checkAvailability = function(dateStr, callback){
        if(this.availabilityCache[dateStr] !== undefined){
            callback(this.availabilityCache[dateStr]);
            return;
        }
        
        if(!this.serviceId || !this.workerId){
            callback(false);
            return;
        }
        
        var self = this;
        var params = new URLSearchParams();
        params.set('action','valcode_get_slots');
        params.set('nonce', ValcodeAppoint.nonce);
        params.set('service_id', this.serviceId);
        params.set('staff_id', this.workerId);
        params.set('date', dateStr);
        
        fetch(ValcodeAppoint.ajax + '?' + params.toString(), { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
            var hasSlots = res && res.success && res.data && res.data.slots && res.data.slots.length > 0;
            self.availabilityCache[dateStr] = hasSlots;
            callback(hasSlots);
        })
        .catch(function(){
            self.availabilityCache[dateStr] = false;
            callback(false);
        });
    };
    
    CalendarWidget.prototype.render = function(){
        var self = this;
        var year = this.currentMonth.getFullYear();
        var month = this.currentMonth.getMonth();
        
        var monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
                         'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
        var dayNames = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var prevLastDay = new Date(year, month, 0);

        // Adjust startDay so Monday = 0, Sunday = 6
        var startDay = firstDay.getDay();
        startDay = (startDay === 0) ? 6 : startDay - 1;
        var daysInMonth = lastDay.getDate();
        var prevDaysInMonth = prevLastDay.getDate();
        
        var html = '<div class="va-calendar-widget">';
        html += '<div class="va-calendar-header">';
        html += '<h4>' + monthNames[month] + ' ' + year + '</h4>';
        html += '<div class="va-calendar-nav">';
        html += '<button type="button" data-action="prev">◀</button>';
        html += '<button type="button" data-action="next">▶</button>';
        html += '</div>';
        html += '</div>';
        
        html += '<div class="va-calendar-grid">';
        
        // Day headers
        dayNames.forEach(function(day){
            html += '<div class="va-calendar-day-header">' + day + '</div>';
        });
        
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        
        // Calculate minimum booking date
        var minDate = new Date(today);
        minDate.setDate(minDate.getDate() + this.minAdvanceDays);
        minDate.setHours(0, 0, 0, 0);
        
        // Previous month days
        for(var i = startDay - 1; i >= 0; i--){
            var day = prevDaysInMonth - i;
            html += '<div class="va-calendar-day other-month">' + day + '</div>';
        }
        
        // Current month days
        for(var day = 1; day <= daysInMonth; day++){
            var date = new Date(year, month, day);
            date.setHours(0, 0, 0, 0);
            var classes = ['va-calendar-day'];
            var disabled = date < minDate;
            
            if(date.getTime() === today.getTime()){
                classes.push('today');
            }
            
            if(this.selectedDate && date.getTime() === this.selectedDate.getTime()){
                classes.push('selected');
            }
            
            if(disabled){
                classes.push('disabled');
            }
            
            var dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            html += '<div class="' + classes.join(' ') + '" data-date="' + dateStr + '" data-disabled="' + disabled + '" data-checking="true">' + day + '</div>';
        }
        
        // Next month days
        var remainingDays = 42 - (startDay + daysInMonth);
        for(var day = 1; day <= remainingDays; day++){
            html += '<div class="va-calendar-day other-month">' + day + '</div>';
        }
        
        html += '</div></div>';
        
        this.container.innerHTML = html;
        
        // Check availability for each day if service/worker is set
        if(this.serviceId && this.workerId){
            this.container.querySelectorAll('.va-calendar-day[data-date][data-checking="true"]').forEach(function(dayEl){
                var dateStr = dayEl.getAttribute('data-date');
                var alreadyDisabled = dayEl.getAttribute('data-disabled') === 'true';
                
                if(!alreadyDisabled){
                    self.checkAvailability(dateStr, function(hasSlots){
                        if(!hasSlots){
                            dayEl.classList.add('disabled', 'no-availability');
                            dayEl.setAttribute('data-disabled', 'true');
                            dayEl.title = 'Keine Termine verfügbar';
                        }
                        dayEl.removeAttribute('data-checking');
                    });
                }
            });
        }
        
        // Event listeners
        this.container.querySelectorAll('[data-action="prev"]').forEach(function(btn){
            btn.addEventListener('click', function(){
                self.currentMonth.setMonth(self.currentMonth.getMonth() - 1);
                self.render();
            });
        });
        
        this.container.querySelectorAll('[data-action="next"]').forEach(function(btn){
            btn.addEventListener('click', function(){
                self.currentMonth.setMonth(self.currentMonth.getMonth() + 1);
                self.render();
            });
        });
        
        this.container.querySelectorAll('.va-calendar-day[data-date]').forEach(function(day){
            day.addEventListener('click', function(){
                if(this.getAttribute('data-disabled') === 'true') return;
                
                var dateStr = this.getAttribute('data-date');
                self.selectedDate = new Date(dateStr + 'T00:00:00');
                self.render();
                
                if(self.onDateSelect){
                    self.onDateSelect(dateStr);
                }
            });
        });
    };

    document.addEventListener('DOMContentLoaded', function(){
        var service = q('va_service');
        var worker  = q('va_worker');
        var slotsContainer = q('va_slots');
        var form = q('va-booking');
        var startHidden = q('va_starts_at');
        var selectedSlot = null;
        var selectedDate = null;
        var bookedSlots = [];

        var next1 = q('va_next_1');
        var next2 = q('va_next_2');
        var next3 = q('va_next_3');
        var prev2 = q('va_prev_2');
        var prev3 = q('va_prev_3');
        var prev4 = q('va_prev_4');

        var calendar = null;
        var currentCustomer = null;

        // Check customer login status on load
        function checkCustomerStatus(){
            fetch(ValcodeAppoint.ajax + '?action=valcode_customer_check&nonce=' + encodeURIComponent(ValcodeAppoint.nonce), {
                credentials: 'same-origin'
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if(res && res.success && res.data.logged_in){
                    currentCustomer = res.data;
                    showLoggedInState();
                } else {
                    showLoggedOutState();
                }
            })
            .catch(function(){
                showLoggedOutState();
            });
        }

        function showLoggedInState(){
            var loggedInDiv = q('va_customer_logged_in');
            var authContainer = q('va_auth_container');
            var nameEl = q('va_logged_customer_name');
            var emailEl = q('va_logged_customer_email');
            var customerIdEl = q('va_customer_id');

            if(loggedInDiv && authContainer && currentCustomer){
                if(nameEl) nameEl.textContent = currentCustomer.customer_name;
                if(emailEl) emailEl.textContent = currentCustomer.customer_email;
                if(customerIdEl) customerIdEl.value = currentCustomer.customer_id;
                loggedInDiv.hidden = false;
                authContainer.hidden = true;
            }
        }

        function showLoggedOutState(){
            var loggedInDiv = q('va_customer_logged_in');
            var authContainer = q('va_auth_container');

            if(loggedInDiv && authContainer){
                loggedInDiv.hidden = true;
                authContainer.hidden = false;
            }
            currentCustomer = null;
        }

        // Logout handler
        var logoutBtn = q('va_logout_btn');
        if(logoutBtn){
            logoutBtn.addEventListener('click', function(){
                this.disabled = true;
                this.textContent = 'Wird abgemeldet...';

                var fd = new FormData();
                fd.append('action', 'valcode_customer_logout');
                fd.append('nonce', ValcodeAppoint.nonce);

                fetch(ValcodeAppoint.ajax, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if(res && res.success){
                        currentCustomer = null;
                        showLoggedOutState();
                        logoutBtn.disabled = false;
                        logoutBtn.textContent = 'Abmelden';
                    }
                })
                .catch(function(){
                    logoutBtn.disabled = false;
                    logoutBtn.textContent = 'Abmelden';
                });
            });
        }

        // Check status on page load
        checkCustomerStatus();

        function refreshNext1(){
            enable(next1, !!service.value && !!worker.value);
        }

        function refreshNext2(){
            enable(next2, !!selectedDate);
        }

        function refreshNext3(){
            enable(next3, !!selectedSlot);
        }

        // Step 1: Service change
        if(service && worker){
            service.addEventListener('change', function(){
                worker.disabled = true;
                setOptions(worker, [], 'Lade Mitarbeiter…');
                refreshNext1();
                
                if(!service.value) return;

                fetch(ValcodeAppoint.ajax + '?action=valcode_get_workers&nonce=' + encodeURIComponent(ValcodeAppoint.nonce) + '&service_id=' + encodeURIComponent(service.value), { 
                    credentials: 'same-origin' 
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    var items = (res && res.success && res.data && res.data.workers) ? res.data.workers : [];
                    items = items.map(function(w){ return { id: w.id, name: w.name }; });
                    setOptions(worker, items, items.length ? 'Bitte wählen…' : 'Keine Mitarbeiter verfügbar');
                    worker.disabled = items.length === 0;
                    refreshNext1();
                })
                .catch(function(){
                    setOptions(worker, [], 'Fehler beim Laden');
                    worker.disabled = true;
                    refreshNext1();
                });
            });

            worker.addEventListener('change', refreshNext1);
        }

        // Step 2: Initialize calendar
        if(next1){
            next1.addEventListener('click', function(){
                goto(2);
                if(!calendar){
                    calendar = new CalendarWidget('va_calendar', function(dateStr){
                        selectedDate = dateStr;
                        refreshNext2();
                        loadSlots(dateStr);
                    });
                    // Set service and worker for availability checking
                    calendar.setService(service.value, worker.value);
                }
            });
        }

        function loadSlots(dateStr){
            selectedSlot = null;
            startHidden.value = '';
            slotsContainer.innerHTML = '<p class="va-loading">Lade verfügbare Zeiten…</p>';
            refreshNext3();

            var params = new URLSearchParams();
            params.set('action','valcode_get_slots');
            params.set('nonce', ValcodeAppoint.nonce);
            params.set('service_id', service.value);
            params.set('staff_id', worker.value);
            params.set('date', dateStr);

            fetch(ValcodeAppoint.ajax + '?' + params.toString(), { credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if(res && res.success){
                    var items = res.data.slots || [];
                    bookedSlots = res.data.booked || [];
                    renderSlots(items);
                } else {
                    slotsContainer.innerHTML = '<p class="va-no-slots">Keine freien Zeiten an diesem Tag</p>';
                }
                refreshNext3();
            })
            .catch(function(){
                slotsContainer.innerHTML = '<p class="va-error">Fehler beim Laden der Zeiten</p>';
                refreshNext3();
            });
        }

        function renderSlots(slots){
            if(!slots.length){
                slotsContainer.innerHTML = '<p class="va-no-slots">Keine freien Zeiten verfügbar</p>';
                return;
            }

            slotsContainer.innerHTML = '';
            slots.forEach(function(slot){
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'va-slot-btn';
                btn.textContent = slot.label;
                btn.setAttribute('data-start', slot.start);
                
                // Check if slot is booked
                var isBooked = bookedSlots.indexOf(slot.start) !== -1;
                if(isBooked){
                    btn.disabled = true;
                    btn.title = 'Dieser Zeitslot ist bereits gebucht';
                }
                
                btn.addEventListener('click', function(){
                    if(this.disabled) return;
                    
                    document.querySelectorAll('.va-slot-btn').forEach(function(b){
                        b.classList.remove('selected');
                    });
                    btn.classList.add('selected');
                    selectedSlot = slot.start;
                    startHidden.value = slot.start;
                    refreshNext3();
                });
                
                slotsContainer.appendChild(btn);
            });
        }

        // Navigation buttons
        if(prev2){ prev2.addEventListener('click', function(){ goto(1); }); }
        if(next2){ next2.addEventListener('click', function(){ goto(3); }); }
        if(prev3){ prev3.addEventListener('click', function(){ goto(2); }); }
        if(next3){ next3.addEventListener('click', function(){ goto(4); }); }
        if(prev4){ prev4.addEventListener('click', function(){ goto(3); }); }

        // Booking mode selection
        var bookingModeRadios = document.querySelectorAll('input[name="booking_mode"]');
        var guestForm = q('va_guest_form');
        var loginForm = q('va_login_form');
        var registerForm = q('va_register_form');

        if(bookingModeRadios.length > 0){
            bookingModeRadios.forEach(function(radio){
                radio.addEventListener('change', function(){
                    var mode = this.value;
                    if(guestForm) guestForm.hidden = mode !== 'guest';
                    if(loginForm) loginForm.hidden = mode !== 'login';
                    if(registerForm) registerForm.hidden = mode !== 'register';
                });
            });
        }

        // Form submit
        if(form){
            form.addEventListener('submit', function(e){
                e.preventDefault();
                msg('', false);

                var submitBtn = form.querySelector('button[type="submit"]');
                if(submitBtn){ submitBtn.disabled = true; submitBtn.textContent = 'Bitte warten...'; }

                // Determine booking mode
                var bookingMode = 'guest';

                // Check if user is already logged in
                if(currentCustomer && currentCustomer.customer_id){
                    bookingMode = 'logged_in';
                } else {
                    var modeInput = q('va_booking_mode');
                    if(modeInput){
                        bookingMode = modeInput.value;
                    } else {
                        var selectedMode = document.querySelector('input[name="booking_mode"]:checked');
                        if(selectedMode) bookingMode = selectedMode.value;
                    }
                }

                // Handle login first
                if(bookingMode === 'login'){
                    var loginEmail = q('va_login_email');
                    var loginPassword = q('va_login_password');

                    if(!loginEmail || !loginPassword || !loginEmail.value || !loginPassword.value){
                        msg('Bitte E-Mail und Passwort eingeben.', false);
                        if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                        return;
                    }

                    var loginData = new FormData();
                    loginData.append('action', 'valcode_customer_login');
                    loginData.append('nonce', ValcodeAppoint.nonce);
                    loginData.append('email', loginEmail.value);
                    loginData.append('password', loginPassword.value);

                    fetch(ValcodeAppoint.ajax, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: loginData
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if(res && res.success){
                            // Login successful, update state and create appointment
                            currentCustomer = res.data;
                            var customerIdEl = q('va_customer_id');
                            if(customerIdEl) customerIdEl.value = res.data.customer_id;
                            createAppointment(submitBtn, 'logged_in', res.data.customer_id);
                        } else {
                            msg(res && res.data && res.data.message ? res.data.message : 'Anmeldung fehlgeschlagen.', false);
                            if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                        }
                    })
                    .catch(function(){
                        msg('Fehler bei der Anmeldung.', false);
                        if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                    });
                    return;
                }

                // Handle registration first
                if(bookingMode === 'register'){
                    var regFirstname = q('va_reg_firstname');
                    var regLastname = q('va_reg_lastname');
                    var regEmail = q('va_reg_email');
                    var regPhone = q('va_reg_phone');
                    var regPassword = q('va_reg_password');
                    var regNotes = q('va_reg_notes');

                    if(!regFirstname || !regLastname || !regEmail || !regPassword ||
                       !regFirstname.value || !regLastname.value || !regEmail.value || !regPassword.value){
                        msg('Bitte alle Pflichtfelder ausfüllen.', false);
                        if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                        return;
                    }

                    var registerData = new FormData();
                    registerData.append('action', 'valcode_customer_register');
                    registerData.append('nonce', ValcodeAppoint.nonce);
                    registerData.append('first_name', regFirstname.value);
                    registerData.append('last_name', regLastname.value);
                    registerData.append('email', regEmail.value);
                    registerData.append('phone', regPhone ? regPhone.value : '');
                    registerData.append('password', regPassword.value);
                    registerData.append('notes', regNotes ? regNotes.value : '');

                    fetch(ValcodeAppoint.ajax, {
                        method: 'POST',
                        credentials: 'same-origin',
                        body: registerData
                    })
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        if(res && res.success){
                            // Registration successful, update state and create appointment
                            currentCustomer = res.data;
                            var customerIdEl = q('va_customer_id');
                            if(customerIdEl) customerIdEl.value = res.data.customer_id;
                            createAppointment(submitBtn, 'logged_in', res.data.customer_id);
                        } else {
                            msg(res && res.data && res.data.message ? res.data.message : 'Registrierung fehlgeschlagen.', false);
                            if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                        }
                    })
                    .catch(function(){
                        msg('Fehler bei der Registrierung.', false);
                        if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                    });
                    return;
                }

                // Handle guest or already logged in
                var customerId = null;
                if(bookingMode === 'logged_in'){
                    // Get customer ID from hidden field or currentCustomer object
                    var customerIdEl = q('va_customer_id');
                    customerId = customerIdEl ? customerIdEl.value : null;
                    if(!customerId && currentCustomer){
                        customerId = currentCustomer.customer_id;
                    }
                }
                createAppointment(submitBtn, bookingMode, customerId);
            });
        }

        function createAppointment(submitBtn, mode, userId){
            var fd = new FormData();
            fd.append('action','valcode_create_appointment');
            fd.append('nonce', ValcodeAppoint.nonce);
            fd.append('service_id', service.value);
            fd.append('staff_id', worker.value);
            fd.append('starts_at', startHidden.value);

            // Get customer info based on mode
            if(mode === 'logged_in' && userId){
                // Use customer data from currentCustomer object
                fd.append('user_id', userId);
                if(currentCustomer){
                    fd.append('customer_name', currentCustomer.customer_name || '');
                    fd.append('customer_email', currentCustomer.customer_email || '');
                } else {
                    // Fallback if currentCustomer is not set
                    fd.append('customer_name', '');
                    fd.append('customer_email', '');
                }
            } else {
                // Guest mode
                var guestName = q('va_guest_name');
                var guestEmail = q('va_guest_email');
                if(guestName && guestEmail){
                    fd.append('customer_name', guestName.value);
                    fd.append('customer_email', guestEmail.value);
                }
            }

            // Get notes from the appropriate field
            var notesField = null;
            if(mode === 'logged_in'){
                notesField = q('va_notes_logged_in');
            } else {
                notesField = q('va_notes') || q('va_reg_notes');
            }
            if(notesField) fd.append('notes', notesField.value);

            fetch(ValcodeAppoint.ajax, {
                method:'POST',
                credentials:'same-origin',
                body: fd
            })
            .then(function(r){ return r.json(); })
            .then(function(res){
                if(res && res.success){
                    goto(5);

                    // Display success message with details
                    var successMsg = q('va_success_msg');
                    if(successMsg){
                        var serviceName = service.options[service.selectedIndex].text;
                        var workerName = worker.options[worker.selectedIndex].text;

                        // Format date nicely
                        var dateObj = new Date(selectedDate);
                        var formattedDate = dateObj.toLocaleDateString('de-CH', {
                            weekday: 'long',
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        });
                        var timeStr = selectedSlot.split(' ')[1].substring(0,5);

                        // Get plugin colors
                        var accentColor = ValcodeAppoint.colors && ValcodeAppoint.colors.accent ? ValcodeAppoint.colors.accent : '#6366f1';

                        successMsg.innerHTML = '<div style="background: #fff; border-radius: 8px; padding: 20px; margin: 20px 0; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">' +
                            '<p style="font-size: 15px; color: #3c434a; line-height: 2; margin: 0; text-align: left;">' +
                            '<strong style="color: #1e1e1e; font-size: 16px; display: block; margin-bottom: 12px;">Ihre Buchungsdetails</strong>' +
                            '<span style="display: block; margin: 8px 0;"><strong style="color: #646970;">Service:</strong> <span style="color: #1e1e1e;">' + serviceName + '</span></span>' +
                            '<span style="display: block; margin: 8px 0;"><strong style="color: #646970;">Mitarbeiter:</strong> <span style="color: #1e1e1e;">' + workerName + '</span></span>' +
                            '<span style="display: block; margin: 8px 0;"><strong style="color: #646970;">Datum:</strong> <span style="color: #1e1e1e;">' + formattedDate + '</span></span>' +
                            '<span style="display: block; margin: 8px 0;"><strong style="color: #646970;">Uhrzeit:</strong> <span style="color: ' + accentColor + '; font-weight: 600;">' + timeStr + ' Uhr</span></span>' +
                            '</p>' +
                            '</div>' +
                            '<p style="font-size: 15px; color: #3c434a; line-height: 1.8; margin: 20px 0 0 0;">' +
                            '<strong style="color: #1e1e1e; font-size: 16px;">Was passiert jetzt?</strong><br><br>' +
                            'Sie erhalten in Kürze eine Bestätigungs-E-Mail mit:<br>' +
                            '• Allen Termindetails<br>' +
                            '• Einem Kalendereintrag (.ics Datei)<br>' +
                            '• Link zum Google Kalender<br><br>' +
                            'Wir freuen uns auf Ihren Besuch!' +
                            '</p>';
                    }

                    // Reset form
                    if(form) form.reset();
                    selectedSlot = null;
                    selectedDate = null;
                    calendar = null;
                    enable(worker, false);
                    setOptions(worker, [], 'Bitte zuerst Service wählen…');
                    if(slotsContainer) slotsContainer.innerHTML = '';
                } else {
                    msg(res && res.data && res.data.message ? res.data.message : 'Fehler beim Speichern.', false);
                    if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
                }
            })
            .catch(function(){
                msg('Fehler beim Absenden.', false);
                if(submitBtn){ submitBtn.disabled = false; submitBtn.textContent = 'Termin verbindlich buchen'; }
            });
        }

        // Password Reset functionality
        var forgotLink = q('va_forgot_password_link');
        var backToLoginLink = q('va_back_to_login');
        var forgotForm = q('va_forgot_form');
        var resetRequestBtn = q('va_reset_request_btn');
        var resetMsg = q('va_reset_msg');

        if(forgotLink){
            forgotLink.addEventListener('click', function(e){
                e.preventDefault();
                if(loginForm) loginForm.hidden = true;
                if(forgotForm) forgotForm.hidden = false;
            });
        }

        if(backToLoginLink){
            backToLoginLink.addEventListener('click', function(e){
                e.preventDefault();
                if(forgotForm) forgotForm.hidden = true;
                if(loginForm) loginForm.hidden = false;
                if(resetMsg) resetMsg.textContent = '';
            });
        }

        if(resetRequestBtn){
            resetRequestBtn.addEventListener('click', function(){
                var resetEmail = q('va_reset_email');
                if(!resetEmail || !resetEmail.value){
                    if(resetMsg){
                        resetMsg.textContent = 'Bitte E-Mail-Adresse eingeben.';
                        resetMsg.className = 'va-msg err';
                    }
                    return;
                }

                resetRequestBtn.disabled = true;
                resetRequestBtn.textContent = 'Sende...';

                var fd = new FormData();
                fd.append('action', 'valcode_customer_reset_request');
                fd.append('nonce', ValcodeAppoint.nonce);
                fd.append('email', resetEmail.value);

                fetch(ValcodeAppoint.ajax, {
                    method: 'POST',
                    credentials: 'same-origin',
                    body: fd
                })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if(res && res.success){
                        if(resetMsg){
                            resetMsg.textContent = res.data.message || 'Reset-Link wurde gesendet.';
                            resetMsg.className = 'va-msg ok';
                        }
                        if(resetEmail) resetEmail.value = '';
                    } else {
                        if(resetMsg){
                            resetMsg.textContent = res && res.data && res.data.message ? res.data.message : 'Fehler beim Senden.';
                            resetMsg.className = 'va-msg err';
                        }
                    }
                    resetRequestBtn.disabled = false;
                    resetRequestBtn.textContent = 'Reset-Link senden';
                })
                .catch(function(){
                    if(resetMsg){
                        resetMsg.textContent = 'Fehler beim Senden.';
                        resetMsg.className = 'va-msg err';
                    }
                    resetRequestBtn.disabled = false;
                    resetRequestBtn.textContent = 'Reset-Link senden';
                });
            });
        }

        // Initialize
        refreshNext1();
        refreshNext2();
        refreshNext3();
    });
})();