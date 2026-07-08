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
    var uploadedFiles = [];
    
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
        var files = e.dataTransfer.files;
        handleFiles(files);
    });
    
    // Click en zona de drop
    dropZone.on('click', function() {
        fileInput.click();
    });
    
    fileInput.on('change', function() {
        handleFiles(this.files);
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
            var allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'video/mp4', 'video/webm'];
            if (allowedTypes.indexOf(file.type) === -1) {
                alert('El archivo "' + file.name + '" no es un formato permitido (JPG, PNG, GIF, WEBP, MP4, WEBM).');
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
        if (!subcategoria) errors.push('Debe seleccionar una subcategoría');
        
        // Determinar ubicación según rol
        var ubicTipo = '';
        var ubicValor = '';
        if (rol === 'propietario') {
            var depto = $('#departamento').val();
            var estac = $('#estacionamiento').val();
            var bodega = $('#bodega').val();
            if (!depto && !estac && !bodega) {
                errors.push('Debe seleccionar su departamento, estacionamiento o bodega');
            } else {
                ubicTipo = 'departamento';
                ubicValor = $('#departamento option:selected').text();
                if (estac) {
                    ubicValor += ' | ' + $('#estacionamiento option:selected').text();
                    if (!ubicTipo) ubicTipo = 'estacionamiento';
                }
                if (bodega) {
                    ubicValor += ' | ' + $('#bodega option:selected').text();
                }
            }
        } else if (rol === 'administrador') {
            ubicTipo = 'area_comun';
            ubicValor = $('#area_comun option:selected').text();
            if (!$('#area_comun').val()) {
                errors.push('Debe seleccionar un área común');
            }
        }
        
        // Recopilar días seleccionados
        var dias = [];
        $('.day-check:checked').each(function() {
            var name = $(this).attr('name');
            var match = name.match(/dias\[(\w+)\]/);
            if (match) {
                var dia = match[1];
                var turno = $(this).val();
                dias.push(dia.charAt(0).toUpperCase() + dia.slice(1) + ' ' + turno);
            }
        });
        if (dias.length === 0) {
            errors.push('Debe seleccionar al menos un día disponible para visita');
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
        
        // Enviar a la API
        $.ajax({
            url: 'api/solicitudes.php?action=crear',
            method: 'POST',
            data: {
                rut: $('#rut').val(),
                nombre: $('#nombre').val(),
                email: $('#email').val(),
                telefono: $('#telefono').val(),
                rol: rol,
                ubicacion_tipo: ubicTipo,
                ubicacion_valor: ubicValor,
                categoria: categoria,
                subcategoria: subcategoria,
                detalle: $('#detalle').val(),
                dias: dias.join(', ')
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    $('#formErrors').html('<div class="alert alert-success"><i class="fas fa-check-circle"></i> ¡Solicitud enviada con éxito! Redirigiendo...</div>').show();
                    $('html, body').animate({scrollTop: 0}, 300);
                    setTimeout(function() {
                        window.location.href = response.redirect || 'dashboard.php?success=1';
                    }, 1500);
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
