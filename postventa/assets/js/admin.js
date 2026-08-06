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

// showConfirm avanzado con validación (onBeforeConfirm debe retornar true para continuar)
function showConfirmAdvanced(message, onBeforeConfirm, onConfirm, onCancel) {
    var $overlay = $('<div class="app-confirm-overlay"></div>');
    var $dialog = $(
        '<div class="app-confirm-dialog" style="max-width:520px;">' +
        '<div class="app-confirm-icon"><i class="fas fa-question-circle"></i></div>' +
        '<div class="app-confirm-body">' + message + '</div>' +
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
    
    $dialog.find('.app-confirm-ok').on('click', function() {
        if (onBeforeConfirm) {
            var result = onBeforeConfirm($dialog);
            if (result !== true) {
                return; // No cerrar si la validación falla
            }
        }
        close();
        if (onConfirm) onConfirm();
    });
    $dialog.find('.app-confirm-cancel').on('click', function() { close(); if (onCancel) onCancel(); });
    $overlay.on('click', function() { close(); if (onCancel) onCancel(); });
}

// Popup de aprobación con dropdowns de categorías en cascada
function showAprobarConfirm(caseId, urgencia, $btn, $row, onSuccess) {
    // Cargar categorías primero
    $.ajax({
        url: 'api/solicitudes.php?action=categorias&tipo=categorias',
        method: 'GET',
        dataType: 'json',
        success: function(catResponse) {
            if (!catResponse.success || !catResponse.items) {
                showToast('Error al cargar categorías', 'error');
                return;
            }
            
            var catOptions = '<option value="">-- Seleccione Categoría --</option>';
            catResponse.items.forEach(function(cat) {
                catOptions += '<option value="' + cat.caso_categoria_id + '">' + cat.caso_categoria_nombre + '</option>';
            });
            
            var messageHtml = '' +
                '<div style="text-align:center; margin-bottom:8px;">' +
                '<i class="fas fa-exclamation-triangle" style="color:#f0ad4e; font-size:2rem; display:block; margin-bottom:10px;"></i>' +
                '<strong style="font-size:1rem;">¿Confirmas la aprobación del caso #' + caseId + '?</strong>' +
                '</div>' +
                '<div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:12px; margin:10px 0; text-align:center;">' +
                '<i class="fas fa-share-square" style="color:#d39e00;"></i> ' +
                '<strong style="color:#856404;">Al aprobar, esta solicitud será enviada al sistema de la constructora (SIGRO).</strong>' +
                '</div>' +
                '<div style="margin-top:16px; text-align:left;">' +
                '<label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:4px; color:#333;">Categoría <span style="color:red;">*</span></label>' +
                '<select class="form-control aprobar-cat-select" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc; margin-bottom:12px;">' +
                catOptions +
                '</select>' +
                '<label style="display:block; font-weight:600; font-size:0.85rem; margin-bottom:4px; color:#333;">Detalle <span style="color:red;">*</span></label>' +
                '<select class="form-control aprobar-det-select" style="width:100%; padding:8px; border-radius:6px; border:1px solid #ccc;" disabled>' +
                '<option value="">-- Primero seleccione Categoría --</option>' +
                '</select>' +
                '<div class="aprobar-validation-msg" style="color:#d9534f; font-size:0.8rem; margin-top:8px; display:none;"></div>' +
                '</div>';
            
            showConfirmAdvanced(
                messageHtml,
                function($dialog) {
                    // Validación: ambos campos requeridos
                    var catVal = $dialog.find('.aprobar-cat-select').val();
                    var detVal = $dialog.find('.aprobar-det-select').val();
                    var $msg = $dialog.find('.aprobar-validation-msg');
                    
                    if (!catVal) {
                        $msg.text('Debe seleccionar una Categoría.').show();
                        $dialog.find('.aprobar-cat-select').focus();
                        return false;
                    }
                    if (!detVal) {
                        $msg.text('Debe seleccionar un Detalle.').show();
                        $dialog.find('.aprobar-det-select').focus();
                        return false;
                    }
                    $msg.hide();
                    return true;
                },
                function() {
                    // Al confirmar, obtener los valores seleccionados del DOM (el diálogo ya se cerró pero tenemos las variables)
                    // Usamos el último diálogo creado
                    var catVal = $('.app-confirm-dialog').last().find('.aprobar-cat-select').val();
                    var detVal = $('.app-confirm-dialog').last().find('.aprobar-det-select').val();
                    
                    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                    
                    $.ajax({
                        url: 'api/solicitudes.php?action=aprobar',
                        method: 'POST',
                        data: { 
                            id: caseId, 
                            urgencia: urgencia,
                            caso_categoria_id: catVal,
                            caso_categoria_detalle_id: detVal
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                showToast(response.message, 'success');
                                $row.find('.badge').removeClass('badge-pending').addClass('badge-approved').text('Aprobado');
                                var $urgenciaTd = $row.find('.urgencia-select').closest('td');
                                var urgenciaLabel = urgencia == '1' ? 'Urgente' : 'Normal';
                                var urgenciaBadgeClass = urgencia == '1' ? 'badge-rejected' : 'badge-pending';
                                $urgenciaTd.html('<span class="badge ' + urgenciaBadgeClass + '" style="font-size:0.75rem;">' + urgenciaLabel + '</span>');
                                $row.find('.approve-case, .reject-case').remove();
                                if (onSuccess) onSuccess(response);
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
            
            // Evento: cambio de categoría → cargar detalles
            $(document).on('change', '.aprobar-cat-select', function() {
                var catId = $(this).val();
                var $detSelect = $(this).closest('.app-confirm-dialog').find('.aprobar-det-select');
                
                if (!catId) {
                    $detSelect.html('<option value="">-- Primero seleccione Categoría --</option>').prop('disabled', true);
                    return;
                }
                
                $detSelect.html('<option value="">Cargando...</option>').prop('disabled', true);
                
                $.ajax({
                    url: 'api/solicitudes.php?action=categorias&tipo=detalles&categoria_id=' + catId,
                    method: 'GET',
                    dataType: 'json',
                    success: function(detResponse) {
                        var options = '<option value="">-- Seleccione Detalle --</option>';
                        if (detResponse.success && detResponse.items && detResponse.items.length > 0) {
                            detResponse.items.forEach(function(det) {
                                options += '<option value="' + det.caso_categoria_detalle_id + '">' + det.caso_categoria_detalle_nombre + '</option>';
                            });
                        } else {
                            options = '<option value="">-- Sin detalles disponibles --</option>';
                        }
                        $detSelect.html(options).prop('disabled', false);
                    },
                    error: function() {
                        $detSelect.html('<option value="">Error al cargar</option>').prop('disabled', true);
                    }
                });
            });
        },
        error: function() {
            showToast('Error al cargar categorías', 'error');
        }
    });
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
        var urgencia = $row.find('.urgencia-select').val() || '0';
        console.log('Aprobar caso #' + caseId + ' con urgencia=' + urgencia);
        
        showAprobarConfirm(caseId, urgencia, $btn, $row);
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
                            // Reemplazar select de urgencia por badge
                            var $urgenciaTd = $row.find('.urgencia-select').closest('td');
                            $urgenciaTd.html('<span class="badge badge-pending" style="font-size:0.75rem;">Normal</span>');
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
        var currentStatus = $(this).data('current-status');
        
        // Si el estado actual ya es "aprobado", no permitir modificaciones
        // porque el caso ya fue enviado a la constructora (SIGRO)
        if (currentStatus === 'aprobado') {
            showToast('No se puede modificar un caso que ya fue aprobado y enviado a la constructora.', 'warning');
            $(this).val('aprobado'); // Revertir al valor original
            return;
        }
        
        // Mostrar/ocultar advertencia de envío a constructora
        if (newStatus === 'aprobado') {
            $('#aprobadoWarning').slideDown(200);
        } else {
            $('#aprobadoWarning').slideUp(200);
        }
        var statusLabels = {
            'pendiente': 'Pendiente',
            'aprobado': 'Aprobado',
            'no_corresponde': 'No Corresponde',
            'agendado': 'Agendado',
            'en_proceso': 'En Proceso',
            'resuelto': 'Resuelto'
        };
        if (newStatus === 'aprobado') {
            // Obtener el ID del caso del modal
            var caseId = $('#caseModal').data('current-case-id') || $(this).data('case-id') || 0;
            var $select = $(this);
            var $row = $('.case-row[data-id="' + caseId + '"]');
            var urgencia = $row.length ? ($row.find('.urgencia-select').val() || '0') : '0';
            
            if (caseId > 0) {
                showAprobarConfirm(caseId, urgencia, $('#saveStatusBtn'), $row, function() {
                    // Actualizar badge en el modal
                    $('#modalStatusBadge').removeClass().addClass('badge badge-approved').text('Aprobado');
                    $select.data('current-status', 'aprobado');
                });
            } else {
                // Fallback: sin ID de caso, mostrar confirmación simple
                showConfirm(
                    '<div style="text-align:center; margin-bottom:8px;">' +
                    '<i class="fas fa-exclamation-triangle" style="color:#f0ad4e; font-size:2rem; display:block; margin-bottom:10px;"></i>' +
                    '<strong style="font-size:1rem;">¿Cambiar estado a "Aprobado"?</strong>' +
                    '</div>' +
                    '<div style="background:#fff3cd; border:1px solid #ffc107; border-radius:8px; padding:12px; margin-top:8px; text-align:center;">' +
                    '<i class="fas fa-share-square" style="color:#d39e00;"></i> ' +
                    '<strong style="color:#856404;">Al aprobar, esta solicitud será enviada al sistema de la constructora (SIGRO).</strong>' +
                    '</div>',
                    function() {
                        $('#modalStatusBadge').removeClass().addClass('badge badge-approved').text('Aprobado');
                        $select.data('current-status', 'aprobado');
                    },
                    function() {
                        $select.val($select.data('current-status'));
                    }
                );
            }
        } else {
            if (confirm('¿Cambiar estado a "' + statusLabels[newStatus] + '"?')) {
                // Simulación
                alert('Estado actualizado correctamente.');
            } else {
                // Revertir
                $(this).val($(this).data('current-status'));
            }
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
            
            // Guardar ID del caso actual en el modal
            $('#caseModal').data('current-case-id', s.id);
            
            // Select de urgencia
            $('#modalUrgenciaSelect').val(s.urgencia || '0');
            
            // Si el estado es "aprobado", bloquear el select de estado y urgencia
            // porque el caso ya fue enviado a la constructora (SIGRO) y no se puede revertir
            if (s.estado === 'aprobado') {
                $('#modalStatusSelect').prop('disabled', true);
                $('#modalUrgenciaSelect').prop('disabled', true);
                $('#aprobadoWarning').show();
                $('#saveStatusBtn').prop('disabled', true).css('opacity', '0.5');
            } else {
                $('#modalStatusSelect').prop('disabled', false);
                $('#modalUrgenciaSelect').prop('disabled', false);
                $('#aprobadoWarning').hide();
                $('#saveStatusBtn').prop('disabled', false).css('opacity', '1');
            }
            
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
            
            // Renderizar historial de comunicaciones
            renderComunicaciones(seguimiento);
        },
        error: function() {
            showToast('Error de conexión al cargar el detalle.', 'error');
        }
    });
}

// Renderizar el historial de comunicaciones en el modal
function renderComunicaciones(seguimiento) {
    var $list = $('#comunicacionesList');
    $list.empty();
    
    // Filtrar solo las comunicaciones (tipo que empiece con "comunicacion:")
    var comunicaciones = [];
    if (seguimiento && seguimiento.length > 0) {
        comunicaciones = seguimiento.filter(function(item) {
            return item.tipo && item.tipo.indexOf('comunicacion:') === 0;
        });
    }
    
    if (comunicaciones.length === 0) {
        $list.html('<span class="text-muted" style="font-size:0.85rem;">Sin comunicaciones registradas aún.</span>');
        return;
    }
    
    var iconos = {
        'whatsapp': '💬',
        'llamada': '📞',
        'email': '📧',
        'presencial': '🏢',
        'otro': '📝'
    };
    
    var labels = {
        'whatsapp': 'WhatsApp',
        'llamada': 'Llamada telefónica',
        'email': 'Correo electrónico',
        'presencial': 'Visita presencial',
        'otro': 'Otro medio'
    };
    
    $.each(comunicaciones, function(i, c) {
        var subtipo = c.tipo.replace('comunicacion:', '');
        var icono = iconos[subtipo] || '📝';
        var label = labels[subtipo] || subtipo;
        var fecha = c.created_at || '';
        // Formatear fecha para mostrar más amigable
        if (fecha.length >= 16) {
            var partes = fecha.substring(0, 16).split(' ');
            if (partes.length === 2) {
                var d = partes[0].split('-');
                fecha = d[2] + '/' + d[1] + '/' + d[0] + ' ' + partes[1].substring(0, 5);
            }
        }
        
        var $item = $(
            '<div style="border-left:3px solid #0d6efd; padding:8px 12px; margin-bottom:8px; background:#fff; border-radius:0 6px 6px 0; font-size:0.82rem;">' +
            '<div style="display:flex; justify-content:space-between; margin-bottom:4px;">' +
            '<strong>' + icono + ' ' + label + '</strong>' +
            '<span style="color:#6c757d; font-size:0.75rem;">' + fecha + '</span>' +
            '</div>' +
            '<p style="margin:0; color:#495057;">' + c.comentario + '</p>' +
            '</div>'
        );
        $list.append($item);
    });
}

// Registrar nueva comunicación
$(document).on('click', '#registrarComunicacionBtn', function() {
    var caseId = $('#caseModal').data('current-case-id');
    var subtipo = $('#comunicacionSubtipo').val();
    var comentario = $('#comunicacionTexto').val().trim();
    
    if (!caseId) {
        showToast('Error: No se pudo identificar el caso.', 'error');
        return;
    }
    if (!comentario) {
        showToast('Debe escribir un comentario sobre la comunicación.', 'warning');
        return;
    }
    
    var $btn = $(this);
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Registrando...');
    
    $.ajax({
        url: 'api/solicitudes.php?action=comunicacion',
        method: 'POST',
        data: {
            id: caseId,
            subtipo: subtipo,
            comentario: comentario
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showToast('Comunicación registrada correctamente.', 'success');
                $('#comunicacionTexto').val('');
                // Recargar el detalle para actualizar el historial
                loadCaseDetail(caseId);
            } else {
                showToast(response.message || 'Error al registrar la comunicación.', 'error');
            }
        },
        error: function() {
            showToast('Error de conexión al registrar la comunicación.', 'error');
        },
        complete: function() {
            $btn.prop('disabled', false).html('<i class="fas fa-save"></i> Registrar');
        }
    });
});
