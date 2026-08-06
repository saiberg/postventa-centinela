/**
 * POSTVENTA CENTINELA - JS para Nueva Solicitud
 * Maneja drag & drop, selects dinámicos y validación
 */

$(document).ready(function() {
    
    // --- Inicializar visibilidad según rol pre-seleccionado ---
    var rolInicial = $('#rol').val();
    if (rolInicial === 'propietario') {
        $('#propietario-fields').show();
    } else if (rolInicial === 'administrador') {
        $('#admin-fields').show();
    }
    
    // --- Selector de Rol: muestra/oculta campos según rol ---
    $('#rol').on('change', function() {
        var rol = $(this).val();
        if (rol === 'propietario') {
            $('#propietario-fields').slideDown(200);
            $('#admin-fields').slideUp(200);
        } else if (rol === 'administrador') {
            $('#propietario-fields').slideUp(200);
            $('#admin-fields').slideDown(200);
        } else {
            $('#propietario-fields').slideUp(200);
            $('#admin-fields').slideUp(200);
        }
    });
    
    // --- Toggle checkboxes estacionamiento/bodega (mutuamente excluyentes) ---
    $('#tiene_estacionamiento').on('change', function() {
        if (this.checked) {
            $('#tiene_bodega').prop('checked', false);
            $('#bodega_num').prop('disabled', true).val('');
        }
        $('#estacionamiento_num').prop('disabled', !this.checked);
        if (!this.checked) $('#estacionamiento_num').val('');
    });
    $('#tiene_bodega').on('change', function() {
        if (this.checked) {
            $('#tiene_estacionamiento').prop('checked', false);
            $('#estacionamiento_num').prop('disabled', true).val('');
        }
        $('#bodega_num').prop('disabled', !this.checked);
        if (!this.checked) $('#bodega_num').val('');
    });
    
    // --- Cascada: Tipo Edificio → Obra → Departamento ---
    // Cargar tipos de edificio al iniciar
    $.ajax({
        url: 'api/solicitudes.php?action=cascada&tipo=tipos_edificio',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            console.log('Tipos edificio response:', response);
            if (response.success && response.items) {
                var $tipo = $('#tipo_edificio');
                $.each(response.items, function(i, item) {
                    $tipo.append('<option value="' + item.edificio_tipo_id + '">' + item.edificio_tipo_nombre + '</option>');
                });
            }
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.error('Error al cargar tipos de edificio:', textStatus, errorThrown);
        }
    });
    
    // Tipo Edificio → Obra
    $('#tipo_edificio').on('change', function() {
        var tipoEdificioId = $(this).val();
        var $obra = $('#obra');
        var $depto = $('#departamento');
        
        $obra.empty().append('<option value="">Cargando...</option>').prop('disabled', true);
        $depto.empty().append('<option value="">Primero seleccione un proyecto...</option>').prop('disabled', true);
        $('#piso_id_hidden').val('0');
        $('#edificio_id_hidden').val('0');
        
        if (!tipoEdificioId) {
            $obra.empty().append('<option value="">Primero seleccione un tipo de edificio...</option>');
            return;
        }
        
        $.ajax({
            url: 'api/solicitudes.php?action=cascada&tipo=obras&tipo_edificio_id=' + tipoEdificioId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $obra.empty();
                if (response.success && response.items && response.items.length > 0) {
                    $obra.append('<option value="">Seleccione un proyecto...</option>');
                    $.each(response.items, function(i, item) {
                        $obra.append('<option value="' + item.obra_id + '">' + item.obra_nombre + '</option>');
                    });
                    $obra.prop('disabled', false);
                } else {
                    $obra.append('<option value="">Sin proyectos disponibles para este tipo</option>');
                }
            },
            error: function() {
                $obra.empty().append('<option value="">Error al cargar</option>');
            }
        });
    });
    
    // Obra → Departamentos (todos los deptos de todos los edificios de la obra)
    $('#obra').on('change', function() {
        var obraId = $(this).val();
        var $depto = $('#departamento');
        
        $depto.empty().append('<option value="">Cargando...</option>').prop('disabled', true);
        $('#piso_id_hidden').val('0');
        $('#edificio_id_hidden').val('0');
        
        if (!obraId) {
            $depto.empty().append('<option value="">Primero seleccione un proyecto...</option>');
            return;
        }
        
        $.ajax({
            url: 'api/solicitudes.php?action=cascada&tipo=departamentos&obra_id=' + obraId,
            method: 'GET',
            dataType: 'json',
            success: function(response) {
                $depto.empty();
                if (response.success && response.items && response.items.length > 0) {
                    $depto.append('<option value="">Seleccione su departamento...</option>');
                    $.each(response.items, function(i, item) {
                        $depto.append('<option value="' + item.departamento_id + '" data-piso-id="' + (item.piso_id || '') + '" data-edificio-id="' + (item.edificio_id || '') + '">' + item.departamento_nombre + '</option>');
                    });
                    $depto.prop('disabled', false);
                } else {
                    $depto.append('<option value="">Sin departamentos disponibles</option>');
                }
            },
            error: function() {
                $depto.empty().append('<option value="">Error al cargar</option>');
            }
        });
    });

    // Al seleccionar departamento, guardar el piso_id y edificio_id correspondientes
    $('#departamento').on('change', function() {
        var $opt = $(this).find('option:selected');
        $('#piso_id_hidden').val($opt.data('piso-id') || '0');
        $('#edificio_id_hidden').val($opt.data('edificio-id') || '0');
    });
    
    // --- Selector de Categoría: subcategorías dinámicas ---
    var subcategorias = {
        'estructural': [
            {value: 'fisuras', label: 'Fisuras en muros o losa'},
            {value: 'pintura', label: 'Desprendimiento de pintura'},
            {value: 'desprendimientos', label: 'Desprendimientos de revestimiento'},
            {value: 'humedad', label: 'Humedad en muros o cielos'},
            {value: 'otro_estructural', label: 'Otra falla estructural'}
        ],
        'instalaciones': [
            {value: 'filtraciones', label: 'Filtraciones de agua'},
            {value: 'electricidad', label: 'Cortocircuitos / falla eléctrica'},
            {value: 'presion_agua', label: 'Falta de presión de agua'},
            {value: 'calefaccion', label: 'Problemas de calefacción'},
            {value: 'gas', label: 'Fuga o problema de gas'},
            {value: 'otro_instalaciones', label: 'Otra falla de instalaciones'}
        ],
        'terminaciones': [
            {value: 'puertas', label: 'Puertas descuadradas o que no cierran'},
            {value: 'ventanas', label: 'Ventanas que no cierran / filtran'},
            {value: 'pisos', label: 'Pisos flotantes levantados / dañados'},
            {value: 'ceramica', label: 'Cerámica suelta o quebrada'},
            {value: 'muebles', label: 'Muebles de cocina/baño dañados'},
            {value: 'otro_terminaciones', label: 'Otra falla de terminaciones'}
        ]
    };
    
    $('#categoria').on('change', function() {
        var cat = $(this).val();
        var $sub = $('#subcategoria');
        $sub.empty();
        $sub.append('<option value="">Seleccione subcategoría...</option>');
        
        if (cat && subcategorias[cat]) {
            $.each(subcategorias[cat], function(i, item) {
                $sub.append('<option value="' + item.value + '">' + item.label + '</option>');
            });
            $('#subcategoria-group').slideDown(200);
        } else {
            $('#subcategoria-group').slideUp(200);
        }
    });
    
    // --- Drag & Drop de archivos ---
    var dropZone = $('#dropZone');
    var fileInput = $('#fileInput');
    var fileList = $('#fileList');
    var uploadedFiles = [];  // Array global de archivos seleccionados
    
    // Prevenir comportamiento default
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(function(eventName) {
        dropZone[0].addEventListener(eventName, function(e) {
            e.preventDefault();
            e.stopPropagation();
        });
    });
    
    // Highlight al arrastrar
    ['dragenter', 'dragover'].forEach(function(eventName) {
        dropZone[0].addEventListener(eventName, function() {
            dropZone.addClass('dragover');
        });
    });
    
    ['dragleave', 'drop'].forEach(function(eventName) {
        dropZone[0].addEventListener(eventName, function() {
            dropZone.removeClass('dragover');
        });
    });
    
    // Manejar drop
    dropZone[0].addEventListener('drop', function(e) {
        handleFiles(e.dataTransfer.files);
    });
    
    // Click en zona de drop
    dropZone.on('click', function() {
        fileInput.click();
    });
    
    fileInput.on('change', function() {
        handleFiles(this.files);
        this.value = '';  // Reset para permitir re-seleccionar el mismo archivo
    });
    
    function handleFiles(files) {
        for (var i = 0; i < files.length; i++) {
            var file = files[i];
            
            // Validar tamaño (50MB)
            if (file.size > 50 * 1024 * 1024) {
                alert('El archivo "' + file.name + '" supera los 50MB permitidos.');
                continue;
            }
            
            // Validar tipo
            if (!APP_CONFIG.allowAllFormats && APP_CONFIG.allowedTypes.indexOf(file.type) === -1) {
                alert('El archivo "' + file.name + '" no es un formato permitido.');
                continue;
            }
            
            uploadedFiles.push(file);
            addFileToList(file);
        }
    }
    
    function addFileToList(file) {
        var sizeMB = (file.size / (1024 * 1024)).toFixed(2);
        var icon = file.type.startsWith('video') ? 'fa-video' : 'fa-image';
        
        var item = $('<div>', {class: 'file-item'});
        item.append('<i class="fas ' + icon + '"></i>');
        item.append('<span class="file-name">' + file.name + '</span>');
        item.append('<span class="file-size">' + sizeMB + ' MB</span>');
        item.append('<button type="button" class="file-remove" title="Eliminar"><i class="fas fa-times"></i></button>');
        
        item.find('.file-remove').on('click', function() {
            var index = item.index();
            uploadedFiles.splice(index, 1);
            item.remove();
            updateDropZoneText();
        });
        
        fileList.append(item);
        updateDropZoneText();
    }
    
    function updateDropZoneText() {
        if (uploadedFiles.length > 0) {
            dropZone.find('.drop-text').html('<i class="fas fa-check-circle"></i> ' + uploadedFiles.length + ' archivo(s) seleccionado(s)');
        } else {
            dropZone.find('.drop-text').html('<i class="fas fa-cloud-upload-alt"></i> Arrastra aquí tus archivos o haz clic para seleccionar');
        }
    }
    
    // --- Días disponibles (checkboxes) ---
    // Toggle para seleccionar mañana/tarde
    $('.day-toggle').on('click', function() {
        var day = $(this).data('day');
        $('#' + day + '-manana, #' + day + '-tarde').prop('checked', true);
    });
    
    // --- Validación del formulario ---
    $('#formSolicitud').on('submit', function(e) {
        e.preventDefault();
        
        var errors = [];
        // Tomar el valor del hidden si existe (rol fijo), sino del select (admin_sistema)
        var rol = $('input[name="rol"]').val() || $('#rol').val();
        var categoria = $('#categoria').val();
        var subcategoria = $('#subcategoria').val();
        
        if (!rol) errors.push('Debe seleccionar un rol');
        if (!categoria) errors.push('Debe seleccionar una categoría');
        if (categoria !== 'otro' && !subcategoria) errors.push('Debe seleccionar una subcategoría');
        if (categoria === 'otro' && !$('#detalle').val().trim()) errors.push('Debe describir detalladamente el problema cuando la categoría es "Otro"');
        
        // Determinar ubicación según rol
        var ubicValor = '';
        if (rol === 'propietario') {
            var depto = $('#departamento').val();
            var tieneEstac = $('#tiene_estacionamiento').is(':checked');
            var tieneBodega = $('#tiene_bodega').is(':checked');
            var estacNum = $('#estacionamiento_num').val().trim();
            var bodegaNum = $('#bodega_num').val().trim();
            
            if (!depto && !tieneEstac && !tieneBodega) {
                errors.push('Debe seleccionar su departamento o indicar si el problema está en estacionamiento o bodega');
            } else {
                // Construir ubicación: solo Depto + Estacionamiento/Bodega
                var deptoNombre = $('#departamento option:selected').text();
                
                if (depto) {
                    ubicValor = deptoNombre;
                }
                if (tieneEstac) {
                    ubicValor += (ubicValor ? ' | ' : '') + 'Estacionamiento' + (estacNum ? ' N° ' + estacNum : '');
                }
                if (tieneBodega) {
                    ubicValor += (ubicValor ? ' | ' : '') + 'Bodega' + (bodegaNum ? ' N° ' + bodegaNum : '');
                }
            }
        } else if (rol === 'administrador') {
            ubicValor = $('#area_comun option:selected').text();
            if (!$('#area_comun').val()) {
                errors.push('Debe seleccionar un área común');
            }
        }
        
        // Recopilar días seleccionados
        var dias = [];
        var diasUnicos = {};
        $('.day-check:checked').each(function() {
            var name = $(this).attr('name');
            var match = name.match(/dias\[(\w+)\]/);
            if (match) {
                var dia = match[1];
                var turno = $(this).val();
                dias.push(dia.charAt(0).toUpperCase() + dia.slice(1) + ' ' + turno);
                diasUnicos[dia] = true;
            }
        });
        
        // Validar: al menos 2 períodos en días distintos
        var cantidadDiasDistintos = Object.keys(diasUnicos).length;
        if (dias.length === 0) {
            errors.push('Debe seleccionar al menos un día disponible para visita');
        } else if (cantidadDiasDistintos < 2) {
            errors.push('Debe seleccionar al menos 2 períodos en días distintos');
        }
        
        if (errors.length > 0) {
            var errorHtml = '<ul>';
            $.each(errors, function(i, err) {
                errorHtml += '<li>' + err + '</li>';
            });
            errorHtml += '</ul>';
            $('#formErrors').html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + errorHtml + '</div>').show();
            $('html, body').animate({scrollTop: $('#formErrors').offset().top - 100}, 300);
            return;
        }
        
        // Deshabilitar botón para evitar doble envío
        var $btn = $(this).find('button[type="submit"]');
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Enviando...');
        
        // Construir FormData para enviar archivos y datos
        var formData = new FormData();
        formData.append('rut', $('#rut').val());
        formData.append('nombre', $('#nombre').val());
        formData.append('email', $('#email').val());
        formData.append('telefono', $('#telefono').val());
        formData.append('rol', rol);
        formData.append('ubicacion_valor', ubicValor);
        formData.append('obra_id', $('#obra').val() || '0');
        formData.append('edificio_id', $('#edificio_id_hidden').val() || '0');
        formData.append('piso_id', $('#piso_id_hidden').val() || '0');
        formData.append('departamento_id', $('#departamento').val() || '0');
        formData.append('categoria', categoria);
        formData.append('subcategoria', subcategoria);
        formData.append('detalle', $('#detalle').val());
        formData.append('dias', dias.join(', '));
        
        // Adjuntar archivos desde el array global
        for (var i = 0; i < uploadedFiles.length; i++) {
            formData.append('archivos[]', uploadedFiles[i]);
        }
        
        // Agregar token CSRF para seguridad
        formData.append('csrf_token', $('meta[name="csrf-token"]').attr('content') || '');
        
        // Enviar a la API
        $.ajax({
            url: 'api/solicitudes.php?action=crear',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                    if (response.success) {
                        var solicitudId = response.id || '---';
                        var archivosMsg = response.archivos > 0 ? '<br><small>' + response.archivos + ' archivo(s) adjunto(s)</small>' : '';
                        
                        // Ocultar mensajes de error previos
                        $('#formErrors').hide().empty();
                        
                        // Ocultar formulario y mostrar panel de éxito
                        $('#formSolicitud').fadeOut(300, function() {
                            var successHtml = '' +
                                '<div class="success-panel">' +
                                '  <div class="success-icon"><i class="fas fa-check-circle"></i></div>' +
                                '  <h2>¡Solicitud Ingresada con Éxito!</h2>' +
                                '  <div class="success-detail">' +
                                '    <p>Su solicitud ha sido registrada correctamente.</p>' +
                                '    <p>La inmobiliaria se contactará con usted a la brevedad.</p>' +
                                '    <div class="success-id">' +
                                '      <span class="success-label">N° de Solicitud</span>' +
                                '      <span class="success-number">#' + solicitudId + '</span>' +
                                '    </div>' +
                                '    <p class="success-info"><i class="fas fa-info-circle"></i> Guarde este número para hacer seguimiento.</p>' +
                                '    ' + archivosMsg +
                                '  </div>' +
                                '  <div class="success-actions">' +
                                '    <a href="dashboard.php" class="btn btn-primary btn-lg">' +
                                '      <i class="fas fa-list"></i> Ver Dashboard' +
                                '    </a>' +
                                '    <a href="nueva-solicitud.php" class="btn btn-secondary">' +
                                '      <i class="fas fa-plus"></i> Nueva Solicitud' +
                                '    </a>' +
                                '  </div>' +
                                '</div>';
                            
                            $('.solicitud-container').append(successHtml);
                            $('.success-panel').hide().fadeIn(500);
                            $('html, body').animate({scrollTop: 0}, 300);
                        });
                    } else {
                    $('#formErrors').html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> ' + response.message + '</div>').show();
                    $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Enviar Solicitud');
                    $('html, body').animate({scrollTop: $('#formErrors').offset().top - 100}, 300);
                }
            },
            error: function() {
                $('#formErrors').html('<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> Error de conexión. Intente nuevamente.</div>').show();
                $btn.prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Enviar Solicitud');
                $('html, body').animate({scrollTop: $('#formErrors').offset().top - 100}, 300);
            }
        });
    });
    
});
