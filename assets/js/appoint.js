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
        var dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
        
        var firstDay = new Date(year, month, 1);
        var lastDay = new Date(year, month + 1, 0);
        var prevLastDay = new Date(year, month, 0);
        
        var startDay = firstDay.getDay();
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

        // Form submit
        if(form){
            form.addEventListener('submit', function(e){
                e.preventDefault();
                msg('', false);

                var submitBtn = form.querySelector('button[type="submit"]');
                if(submitBtn){ submitBtn.disabled = true; }

                var fd = new FormData(form);
                fd.append('action','valcode_create_appointment');
                fd.append('nonce', ValcodeAppoint.nonce);

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
                            var dateTime = selectedDate + ' um ' + selectedSlot.split(' ')[1].substring(0,5);
                            
                            successMsg.innerHTML = '<strong>Ihre Buchung wurde bestätigt!</strong><br><br>' +
                                'Service: ' + serviceName + '<br>' +
                                'Mitarbeiter: ' + workerName + '<br>' +
                                'Termin: ' + dateTime + '<br><br>' +
                                'Sie erhalten in Kürze eine Bestätigungs-E-Mail.';
                        }

                        // Reset form
                        form.reset();
                        selectedSlot = null;
                        selectedDate = null;
                        calendar = null;
                        enable(worker, false);
                        setOptions(worker, [], 'Bitte zuerst Service wählen…');
                        slotsContainer.innerHTML = '';
                    } else {
                        msg(res && res.data && res.data.message ? res.data.message : 'Fehler beim Speichern.', false);
                        if(submitBtn){ submitBtn.disabled = false; }
                    }
                })
                .catch(function(){
                    msg('Fehler beim Absenden.', false);
                    if(submitBtn){ submitBtn.disabled = false; }
                });
            });
        }

        // Initialize
        refreshNext1();
        refreshNext2();
        refreshNext3();
    });
})();