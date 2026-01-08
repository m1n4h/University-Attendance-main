// public/assets/js/custom-scripts.js

$(document).ready(function () {

    // =======================================================
    // 1. Sidebar Toggle Functionality
    // =======================================================

    $('#sidebarCollapse').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        $('#wrapper').toggleClass('toggled');
    });

    // Close sidebar when clicking outside on mobile
    $(document).on('click', function (e) {
        // Check if we're on mobile (screen width < 768px)
        if ($(window).width() < 768) {
            // If sidebar is open (toggled class is present)
            if ($('#wrapper').hasClass('toggled')) {
                // If click is not on sidebar or hamburger button
                if (!$(e.target).closest('#sidebar').length &&
                    !$(e.target).closest('#sidebarCollapse').length) {
                    $('#wrapper').removeClass('toggled');
                }
            }
        }
    });

    // Close sidebar when clicking on a sidebar link on mobile
    $('#sidebar .components a').on('click', function () {
        if ($(window).width() < 768) {
            $('#wrapper').removeClass('toggled');
        }
    });

    // Prevent sidebar clicks from closing it
    $('#sidebar').on('click', function (e) {
        e.stopPropagation();
    });

    // =======================================================
    // 2. Form Submission Status Handling
    // =======================================================

    setTimeout(function () {
        $(".alert-success, .alert-danger").fadeTo(500, 0).slideUp(500, function () {
            $(this).remove();
        });
    }, 5000);

    // =======================================================
    // 3. Active Menu State
    // =======================================================

    var currentPath = window.location.pathname;

    $('#sidebar .components a').each(function () {
        var $this = $(this);
        var linkHref = $this.attr('href');

        // Check if current page matches this link
        if (linkHref && currentPath.indexOf(linkHref) !== -1) {
            $this.closest('li').addClass('active');
        }
    });

});