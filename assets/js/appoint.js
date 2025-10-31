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

    document.addEventListener('DOMContentLoaded', function(){
        var service = q('va_service');
        var worker  = q('va_worker');
        var dateInp = q('va_date');
        var slotsContainer = q('va_slots');
        var form = q('va-booking');
        var startHidden = q('va_starts_at');
        var selectedSlot = null;

        var next1 = q('va_next_1');
        var next2 = q('va_next_2');
        var next3 = q('va_next_3');
        var prev2 = q('va_prev_2');
        var prev3 = q('va_prev_3');
        var prev4 = q('va_prev_4');

        // Set min date to today
        if(dateInp){
            var today = new Date().toISOString().split('T')[0];
            dateInp.setAttribute('min', today);
        }

        function refreshNext1(){
            enable(next1, !!service.value && !!worker.value);
        }

        function refreshNext2(){
            enable(next2, !!dateInp.value);
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

        // Step 2: Date change
        if(dateInp){
            dateInp.addEventListener('change', function(){
                refreshNext2();
                selectedSlot = null;
                startHidden.value = '';
                slotsContainer.innerHTML = '<p class="va-loading">Lade verfügbare Zeiten…</p>';
                refreshNext3();

                if(!dateInp.value) return;

                var params = new URLSearchParams();
                params.set('action','valcode_get_slots');
                params.set('nonce', ValcodeAppoint.nonce);
                params.set('service_id', service.value);
                params.set('staff_id', worker.value);
                params.set('date', dateInp.value);

                fetch(ValcodeAppoint.ajax + '?' + params.toString(), { credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    if(res && res.success){
                        var items = res.data.slots || [];
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
                
                btn.addEventListener('click', function(){
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
        if(next1){ next1.addEventListener('click', function(){ goto(2); }); }
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
                            var dateTime = dateInp.value + ' um ' + selectedSlot.split(' ')[1].substring(0,5);
                            
                            successMsg.innerHTML = '<strong>Ihre Buchung wurde bestätigt!</strong><br><br>' +
                                'Service: ' + serviceName + '<br>' +
                                'Mitarbeiter: ' + workerName + '<br>' +
                                'Termin: ' + dateTime + '<br><br>' +
                                'Sie erhalten in Kürze eine Bestätigungs-E-Mail.';
                        }

                        // Reset form
                        form.reset();
                        selectedSlot = null;
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