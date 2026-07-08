/**
 * POSTVENTA CENTINELA - JS para Panel de Administración
 */

$(document).ready(function() {
    
    // --- Abrir modal de detalle ---
    $('.view-case').on('click', function() {
        var caseId = $(this).data('case-id');
        // En producción, esto cargaría datos vía AJAX
        // Por ahora mostramos datos fijos de la maqueta
        loadCaseDetail(caseId);
        $('#caseModal').addClass('show');
        $('body').css('overflow', 'hidden');
    });
    
    // --- Cerrar modal ---
    $('.modal-close, .modal-overlay').on('click', function(e) {
        if (e.target === this) {
            $('#caseModal').removeClass('show');
            $('body').css('overflow', '');
        }
    });
    
    // --- Botón Aprobar ---
    $('.approve-case').on('click', function() {
        var caseId = $(this).data('case-id');
        if (confirm('¿Confirmas la aprobación del caso #' + caseId + '?')) {
            // Simulación
            alert('Caso #' + caseId + ' aprobado correctamente.');
            // En producción: AJAX para actualizar estado
            $(this).closest('tr').find('.badge').removeClass('badge-pending').addClass('badge-approved').text('Aprobado');
        }
    });
    
    // --- Botón Rechazar ---
    $('.reject-case').on('click', function() {
        var caseId = $(this).data('case-id');
        var motivo = prompt('Ingrese el motivo del rechazo para el caso #' + caseId + ':');
        if (motivo !== null && motivo.trim() !== '') {
            // Simulación
            alert('Caso #' + caseId + ' rechazado. Motivo: ' + motivo);
            $(this).closest('tr').find('.badge').removeClass('badge-pending').addClass('badge-rejected').text('No corresponde');
        }
    });
    
    // --- Cambio de estado en modal ---
    $('#modalStatusSelect').on('change', function() {
        var newStatus = $(this).val();
        var statusLabels = {
            'pendiente': 'Pendiente',
            'aprobado': 'Aprobado',
            'no_corresponde': 'No Corresponde',
            'agendado': 'Agendado',
            'en_proceso': 'En Proceso',
            'resuelto': 'Resuelto'
        };
        if (confirm('¿Cambiar estado a "' + statusLabels[newStatus] + '"?')) {
            // Simulación
            alert('Estado actualizado correctamente.');
        } else {
            // Revertir
            $(this).val($(this).data('current-status'));
        }
    });
    
    // --- Filtros ---
    $('#filterEstado').on('change', function() {
        var estado = $(this).val();
        if (estado) {
            $('.case-row').hide();
            $('.case-row[data-estado="' + estado + '"]').show();
        } else {
            $('.case-row').show();
        }
    });
    
    $('#filterSearch').on('keyup', function() {
        var search = $(this).val().toLowerCase();
        $('.case-row').each(function() {
            var text = $(this).text().toLowerCase();
            $(this).toggle(text.indexOf(search) > -1);
        });
    });
    
});

function loadCaseDetail(caseId) {
    // Datos de ejemplo - en producción se cargarían vía AJAX
    var cases = {
        '001': {
            id: 'PC-2024-001',
            fecha: '15/03/2024',
            estado: 'pendiente',
            rut: '12.345.678-9',
            nombre: 'Carlos Muñoz R.',
            email: 'carlos.munoz@email.com',
            telefono: '+56 9 1234 5678',
            rol: 'Propietario',
            ubicacion: 'Depto 502, Torre A',
            categoria: 'Fallas Estructurales/Estéticas',
            subcategoria: 'Fisuras en muros',
            detalle: 'Se observa una fisura de aproximadamente 30cm en el muro del living, cercana a la ventana.',
            dias: 'Lunes AM, Miércoles AM/PM'
        },
        '002': {
            id: 'PC-2024-002',
            fecha: '18/03/2024',
            estado: 'aprobado',
            rut: '9.876.543-2',
            nombre: 'María González L.',
            email: 'maria.gonzalez@email.com',
            telefono: '+56 9 8765 4321',
            rol: 'Propietario',
            ubicacion: 'Depto 1104, Torre B',
            categoria: 'Instalaciones (Gas/Agua/Luz)',
            subcategoria: 'Filtraciones de agua',
            detalle: 'Hay una filtración de agua en el baño principal que moja el piso constantemente.',
            dias: 'Martes PM, Jueves AM'
        }
    };
    
    var c = cases[caseId] || cases['001'];
    
    $('#modalCaseId').text('Caso #' + c.id);
    $('#modalRut').text(c.rut);
    $('#modalNombre').text(c.nombre);
    $('#modalEmail').text(c.email);
    $('#modalTelefono').text(c.telefono);
    $('#modalRol').text(c.rol);
    $('#modalUbicacion').text(c.ubicacion);
    $('#modalCategoria').text(c.categoria);
    $('#modalSubcategoria').text(c.subcategoria);
    $('#modalDetalle').text(c.detalle);
    $('#modalDias').text(c.dias);
    $('#modalFecha').text(c.fecha);
    
    var $statusBadge = $('#modalStatusBadge');
    $statusBadge.removeClass();
    $statusBadge.addClass('badge');
    switch(c.estado) {
        case 'pendiente': $statusBadge.addClass('badge-pending').text('Pendiente'); break;
        case 'aprobado': $statusBadge.addClass('badge-approved').text('Aprobado'); break;
        case 'no_corresponde': $statusBadge.addClass('badge-rejected').text('No Corresponde'); break;
        case 'agendado': $statusBadge.addClass('badge-scheduled').text('Agendado'); break;
        case 'en_proceso': $statusBadge.addClass('badge-in-progress').text('En Proceso'); break;
        case 'resuelto': $statusBadge.addClass('badge-resolved').text('Resuelto'); break;
    }
    
    $('#modalStatusSelect').val(c.estado);
    $('#modalStatusSelect').data('current-status', c.estado);
}
