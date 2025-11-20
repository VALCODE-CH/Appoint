(function($){
    // Appointments page: dynamic worker select
    $(document).on('change', '#service_id, #modal_service_id', function(){
        var service = $(this).val();
        var isModal = $(this).attr('id') === 'modal_service_id';
        var $worker = isModal ? $('#modal_staff_id') : $('#staff_id');

        if(!service){
            $worker.prop('disabled', true).html('<option value="">Bitte zuerst Service wählen…</option>');
            return;
        }
        $worker.prop('disabled', true).html('<option value="">Lade Mitarbeiter…</option>');
        $.get(ValcodeAppointAdmin.ajax, {
            action:'valcode_get_workers',
            nonce: ValcodeAppointAdmin.nonce,
            service_id: service
        }).done(function(res){
            var items = (res && res.success && res.data && res.data.workers) ? res.data.workers : [];
            var html = '<option value="0">-</option>';
            html += '<option value="">' + (items.length ? 'Bitte wählen…' : 'Keine Mitarbeiter verfügbar') + '</option>';
            items.forEach(function(it){ html += '<option value="'+it.id+'">'+it.name+'</option>'; });
            $worker.html(html).prop('disabled', items.length === 0);
        }).fail(function(){
            $worker.html('<option value="">Fehler beim Laden</option>').prop('disabled', true);
        });
    });

    // Customer select: auto-fill customer name and email
    $(document).on('change', '#customer_select, #modal_customer_select', function(){
        var $option = $(this).find('option:selected');
        var name = $option.data('name');
        var email = $option.data('email');
        var isModal = $(this).attr('id') === 'modal_customer_select';

        if(name && email){
            if(isModal) {
                $('#modal_customer_name').val(name);
                $('#modal_customer_email').val(email);
            } else {
                $('#customer_name').val(name);
                $('#customer_email').val(email);
            }
        }
    });

    // Modal functionality for appointments
    var $modal = $('#va-appointment-modal');
    var $form = $('#va-appointment-form');
    var appointmentsData = window.ValcodeAppointData || {};

    // Open modal for new appointment
    $(document).on('click', '#va-add-appointment-btn', function(e){
        e.preventDefault();
        openModal();
    });

    // Open modal for editing appointment
    $(document).on('click', '.va-edit-appointment', function(e){
        e.preventDefault();
        var appointmentId = $(this).data('id');
        openModal(appointmentId);
    });

    // Close modal
    $(document).on('click', '.va-modal-close, #va-modal-cancel', function(e){
        e.preventDefault();
        closeModal();
    });

    // Close modal on outside click
    $(document).on('click', '#va-appointment-modal', function(e){
        if(e.target.id === 'va-appointment-modal'){
            closeModal();
        }
    });

    // Close modal on ESC key
    $(document).keydown(function(e){
        if(e.key === 'Escape' && $modal.is(':visible')){
            closeModal();
        }
    });

    function openModal(appointmentId) {
        if(appointmentId) {
            // Edit mode
            $('#va-modal-title').text('Termin bearbeiten');
            loadAppointmentData(appointmentId);
        } else {
            // Add mode
            $('#va-modal-title').text('Neuen Termin anlegen');
            resetForm();
        }
        $modal.fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeModal() {
        $modal.fadeOut(200);
        $('body').css('overflow', '');
        resetForm();
    }

    function resetForm() {
        $form[0].reset();
        $('#appointment_id').val('');
        $('#modal_staff_id').prop('disabled', true).html('<option value="">Bitte zuerst Service wählen…</option>');
    }

    function loadAppointmentData(appointmentId) {
        var appointment = null;
        if(appointmentsData.appointments) {
            appointment = appointmentsData.appointments.find(function(a){ return a.id == appointmentId; });
        }

        if(!appointment) {
            // Fetch via AJAX
            $.get(appointmentsData.ajaxUrl, {
                action: 'valcode_get_appointment',
                nonce: appointmentsData.nonce,
                id: appointmentId
            }).done(function(res){
                if(res && res.success && res.data) {
                    fillForm(res.data);
                }
            }).fail(function(){
                alert('Fehler beim Laden des Termins');
                closeModal();
            });
        } else {
            fillForm(appointment);
        }
    }

    function fillForm(data) {
        $('#appointment_id').val(data.id);
        $('#modal_customer_name').val(data.customer_name);
        $('#modal_customer_email').val(data.customer_email);
        $('#modal_notes').val(data.notes);
        $('#modal_status').val(data.status);

        // Format datetime for input
        if(data.starts_at) {
            var dt = new Date(data.starts_at);
            var formatted = dt.getFullYear() + '-' +
                String(dt.getMonth() + 1).padStart(2, '0') + '-' +
                String(dt.getDate()).padStart(2, '0') + 'T' +
                String(dt.getHours()).padStart(2, '0') + ':' +
                String(dt.getMinutes()).padStart(2, '0');
            $('#modal_starts_at').val(formatted);
        }

        // Set service
        if(data.service_id) {
            $('#modal_service_id').val(data.service_id).trigger('change');

            // Set staff after a brief delay to allow workers to load
            setTimeout(function(){
                if(data.staff_id) {
                    $('#modal_staff_id').val(data.staff_id);
                }
            }, 500);
        }
    }

    // CSV Import Modal functionality
    var $importModal = $('#va-import-modal');

    // Open import modal
    $(document).on('click', '#va-open-import-modal', function(e){
        e.preventDefault();
        $importModal.fadeIn(200);
        $('body').css('overflow', 'hidden');
    });

    // Close import modal
    $(document).on('click', '.va-modal-close, #va-import-cancel', function(e){
        e.preventDefault();
        closeImportModal();
    });

    // Close modal on outside click
    $(document).on('click', '#va-import-modal', function(e){
        if(e.target.id === 'va-import-modal'){
            closeImportModal();
        }
    });

    // Close modal on ESC key
    $(document).keydown(function(e){
        if(e.key === 'Escape' && $importModal.is(':visible')){
            closeImportModal();
        }
    });

    function closeImportModal() {
        $importModal.fadeOut(200);
        $('body').css('overflow', '');
        // Reset file input
        $('#csv_file').val('');
    }
})(jQuery);