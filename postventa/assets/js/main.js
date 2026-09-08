/**
 * POSTVENTA CENTINELA - JavaScript Principal
 */

$(document).ready(function() {
    
    // Toggle menú móvil
    $('#mobileMenuToggle').on('click', function() {
        $('#navMenu').toggleClass('show');
    });
    
    // Toggle dropdown de usuario
    $('#userDropdownToggle').on('click', function(e) {
        e.preventDefault();
        $('#userDropdown').toggleClass('show');
    });
    
    // Cerrar dropdowns al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#userDropdownToggle').length && !$(e.target).closest('#userDropdown').length) {
            $('#userDropdown').removeClass('show');
        }
        if (!$(e.target).closest('#mobileMenuToggle').length && !$(e.target).closest('#navMenu').length) {
            $('#navMenu').removeClass('show');
        }
    });
    
    // Auto-ocultar alertas después de 5 segundos
    setTimeout(function() {
        $('.alert').fadeOut(500);
    }, 5000);
    
    // Confirmación de acciones
    $('[data-confirm]').on('click', function(e) {
        var message = $(this).data('confirm') || '¿Estás seguro de realizar esta acción?';
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
    
});

// Sistema global de notificaciones Toast
if (typeof window.showToast !== 'function') {
    window.showToast = function(message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;
        
        var icons = { 
            success: 'fa-check-circle', 
            error: 'fa-exclamation-circle', 
            info: 'fa-info-circle', 
            warning: 'fa-exclamation-triangle' 
        };
        var icon = icons[type] || icons.info;
        
        var $toast = $('<div class="app-toast app-toast-' + type + '"><i class="fas ' + icon + '"></i><span>' + message + '</span></div>');
        $('body').append($toast);
        
        setTimeout(function() { $toast.addClass('show'); }, 10);
        setTimeout(function() {
            $toast.removeClass('show');
            setTimeout(function() { $toast.remove(); }, 300);
        }, duration);
    };
}

