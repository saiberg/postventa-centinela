/**
 * POSTVENTA CENTINELA - JS para Panel de Administración
 */

// ==================== SISTEMA DE NOTIFICACIONES TOAST ====================
function showToast(message, type, duration) {
    type = type || 'info';
    duration = duration || 4000;
    
    var icons = { success: 'fa-check-circle', error: 'fa-exclamation-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    var icon = icons[type] || icons.info;
    
    var $toast = $('<div class="app-toast app-toast-' + type + '"><i class="fas ' + icon + '"></i><span>' + message + '</span></div>');
    $('body').append($toast);
    
    setTimeout(function() { $toast.addClass('show'); }, 10);
    setTimeout(function() {
        $toast.removeClass('show');
        setTimeout(function() { $toast.remove(); }, 300);
    }, duration);
}

function showConfirm(message, onConfirm, onCancel) {
    var $overlay = $('<div class="app-confirm-overlay"></div>');
    var $dialog = $(
        '<div class="app-confirm-dialog">' +
        '<div class="app-confirm-icon"><i class="fas fa-question-circle"></i></div>' +
        '<p>' + message + '</p>' +
        '<div class="app-confirm-actions">' +
        '<button class="btn btn-secondary btn-sm app-confirm-cancel">Cancelar</button>' +
        '<button class="btn btn-primary btn-sm app-confirm-ok">Confirmar</button>' +
        '</div></div>'
    );
    
    $('body').append($overlay).append($dialog);
    setTimeout(function() { $overlay.addClass('show'); $dialog.addClass('show'); }, 10);
    
    function close() {
        $overlay.removeClass('show');
        $dialog.removeClass('show');
        setTimeout(function() { $overlay.remove(); $dialog.remove(); }, 300);
    }
    
    $dialog.find('.app-confirm-ok').on('click', function() { close(); if (onConfirm) onConfirm(); });
    $dialog.find('.app-confirm-cancel').on('click', function() { close(); if (onCancel) onCancel(); });
    $overlay.on('click', function() { close(); if (onCancel) onCancel(); });
}

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
    
    // --- Cerrar modal (botones con clase modal-close o modal-close-btn, y overlay) ---
    $(document).on('click', '.modal-close, .modal-close-btn', function() {
        $('#caseModal').removeClass('show');
        $('body').css('overflow', '');
    });
    
    $(document).on('click', '.modal-overlay', function(e) {
        if (e.target === this) {
            $('#caseModal').removeClass('show');
            $('body').css('overflow', '');
        }
    });
    
    // --- Botón Aprobar ---
    $('.approve-case').on('click', function() {
        var caseId = $(this).data('case-id');
        var $btn = $(this);
        var $row = $btn.closest('tr');
        
        showConfirm(
            '¿Confirmas la aprobación del caso <strong>#' + caseId + '</strong>?<br><small>Se creará un caso en el sistema SIGRO.</small>',
            function() {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: 'api/solicitudes.php?action=aprobar',
                    method: 'POST',
                    data: { id: caseId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'success');
                            $row.find('.badge').removeClass('badge-pending').addClass('badge-approved').text('Aprobado');
                            $row.find('.approve-case, .reject-case').remove();
                        } else {
                            showToast(response.message, 'error', 6000);
                            $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                        }
                    },
                    error: function() {
                        showToast('Error de conexión al aprobar el caso.', 'error');
                        $btn.prop('disabled', false).html('<i class="fas fa-check"></i>');
                    }
                });
            }
        );
    });
    
    // --- Botón Rechazar ---
    $('.reject-case').on('click', function() {
        var caseId = $(this).data('case-id');
        var $btn = $(this);
        var $row = $btn.closest('tr');
        
        showConfirm(
            '¿Confirmas el <strong>rechazo</strong> del caso <strong>#' + caseId + '</strong>?<br><small>Esta acción marcará el caso como "No Corresponde".</small>',
            function() {
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                
                $.ajax({
                    url: 'api/solicitudes.php?action=rechazar',
                    method: 'POST',
                    data: { id: caseId },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            showToast(response.message, 'warning');
                            $row.find('.badge').removeClass('badge-pending').addClass('badge-rejected').text('No Corresponde');
                            $row.find('.approve-case, .reject-case').remove();
                        } else {
                            showToast(response.message, 'error', 6000);
                            $btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
                        }
                    },
                    error: function() {
                        showToast('Error de conexión al rechazar el caso.', 'error');
                        $btn.prop('disabled', false).html('<i class="fas fa-times"></i>');
                    }
                });
            }
        );
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
    // Mostrar loading
    $('#modalCaseId').html('<i class="fas fa-spinner fa-spin"></i> Cargando...');
    
    $.ajax({
        url: 'api/solicitudes.php?action=detalle&id=' + caseId,
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (!response.success || !response.solicitud) {
                showToast('No se pudo cargar el detalle del caso.', 'error');
                return;
            }
            
            var s = response.solicitud;
            var seguimiento = response.seguimiento || [];
            
            // Formatear fechas
            var fecha = s.created_at ? s.created_at.substring(0, 10).split('-').reverse().join('/') : '—';
            
            // Labels de estado
            var estadoLabels = {
                'pendiente': 'Pendiente', 'aprobado': 'Aprobado', 'no_corresponde': 'No Corresponde',
                'agendado': 'Agendado', 'en_proceso': 'En Proceso', 'resuelto': 'Resuelto'
            };
            var badgeClasses = {
                'pendiente': 'badge-pending', 'aprobado': 'badge-approved', 'no_corresponde': 'badge-rejected',
                'agendado': 'badge-scheduled', 'en_proceso': 'badge-in-progress', 'resuelto': 'badge-resolved'
            };
            
            // Llenar campos del modal
            $('#modalCaseId').html('<i class="fas fa-folder-open"></i> Caso #PC-' + s.created_at.substring(0,4) + '-' + String(s.id).padStart(3,'0'));
            $('#modalRut').text(s.rut || '—');
            $('#modalNombre').text(s.nombre || '—');
            $('#modalEmail').text(s.email || '—');
            $('#modalTelefono').text(s.telefono || '—');
            $('#modalRol').text(s.rol_solicitante === 'administrador_edificio' ? 'Administrador del Edificio' : 'Propietario / Residente');
            $('#modalUbicacion').text(s.ubicacion_valor || '—');
            $('#modalCategoria').text(s.categoria || '—');
            $('#modalSubcategoria').text(s.subcategoria || '—');
            $('#modalDetalle').text(s.detalle || 'Sin descripción.');
            $('#modalDias').text(s.dias_disponibles || '—');
            $('#modalFecha').text(fecha);
            
            // Badge de estado
            var $badge = $('#modalStatusBadge');
            $badge.removeClass().addClass('badge ' + (badgeClasses[s.estado] || 'badge-pending'));
            $badge.text(estadoLabels[s.estado] || s.estado);
            
            // Select de estado
            $('#modalStatusSelect').val(s.estado).data('current-status', s.estado);
            
            // Cargar archivos adjuntos
            $.ajax({
                url: 'api/solicitudes.php?action=archivos&solicitud_id=' + s.id,
                method: 'GET',
                dataType: 'json',
                success: function(archResponse) {
                    var $gallery = $('.evidence-gallery');
                    $gallery.empty();
                    
                    if (archResponse.success && archResponse.archivos && archResponse.archivos.length > 0) {
                        $.each(archResponse.archivos, function(i, arch) {
                            var icon = arch.tipo === 'video' ? 'fa-video' : 'fa-image';
                            var $thumb = $(
                                '<a href="' + arch.ruta + '" target="_blank" class="evidence-thumb" title="' + arch.nombre_original + '">' +
                                '<i class="fas ' + icon + '"></i>' +
                                '</a>'
                            );
                            $gallery.append($thumb);
                        });
                    } else {
                        $gallery.html('<span class="text-muted" style="font-size:0.85rem;">Sin archivos adjuntos.</span>');
                    }
                },
                error: function() {
                    $('.evidence-gallery').html('<span class="text-muted" style="font-size:0.85rem;">Error al cargar archivos.</span>');
                }
            });
        },
        error: function() {
            showToast('Error de conexión al cargar el detalle.', 'error');
        }
    });
}
