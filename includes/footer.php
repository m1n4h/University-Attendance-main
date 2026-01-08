    <!-- Bootstrap Bundle with Popper -->
    <script src="<?php echo BASE_URL; ?>public/assets/js/bootstrap.bundle.min.js"></script>
    <!-- Custom Scripts -->
    <script src="<?php echo BASE_URL; ?>public/assets/js/custom-scripts.js"></script>
    
    <!-- Modal Fix - Clean Bootstrap Modal Handling -->
    <script>
    $(document).ready(function() {
        // Clean up any stale modal backdrops on page load
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open');
        
        // Ensure modals close properly and clean up backdrop
        $('.modal').on('hidden.bs.modal', function () {
            // Remove any leftover backdrops
            $('.modal-backdrop').remove();
            $('body').removeClass('modal-open');
            $('body').css('padding-right', '');
        });
        
        // Handle close button clicks explicitly
        $(document).on('click', '[data-bs-dismiss="modal"]', function(e) {
            var modal = $(this).closest('.modal');
            var bsModal = bootstrap.Modal.getInstance(modal[0]);
            if (bsModal) {
                bsModal.hide();
            } else {
                modal.modal('hide');
            }
            // Force cleanup after a short delay
            setTimeout(function() {
                $('.modal-backdrop').remove();
                $('body').removeClass('modal-open');
                $('body').css('padding-right', '');
            }, 300);
        });
    });
    </script>
    
</body>
</html>