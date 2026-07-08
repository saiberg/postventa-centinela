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
