$( document ).ready(function() {

    var processInvoice = (function(){
        var displayFileName = function(){
            $('.custom-file-input').on('change',function(){
                //get the file name
                var fileName = $(this).val();
                var parts = fileName.split("\\");
                fileName = parts[parts.length-1];
                //replace the "Choose a file" label
                $(this).next('.custom-file-label').html(fileName);
            });
        };

        var mapCompanyName = function() {
            $.noConflict();
            $('#table_id').DataTable();
            $('#frm-map-company-name').on('click', 'button', function(e){
                var thisButton = $(this);
                e.preventDefault();
                var formData = '';
                var action = $(this).attr("value");
                if (action === 'remove') {
                    formData = $('#frm-map-company-name').serialize() + "&action=" + action + '&removeId=' + $(this).data('id');
                } else if (action === 'save') {
                    formData = $('#frm-map-company-name').serialize() + "&action=" + action
                }

                $.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    }
                });
                $.ajax({
                    thisButton: thisButton,
                    url: '/map-company-name',
                    method: 'post',
                    data: formData,
                    success: function(response){
                        response = JSON.parse(response);
                        console.log(response);
                        if (response.message === 'removed') {
                            thisButton.parents("tr:first").remove();
                        } else if (response.message === 'saved') {
                            // $('#frm-map-company-name').trigger("reset");
                            $("#table_id").load(window.location + " #table_id");
                        }
                    },
                    error: function (data) {
                        console.log('Error:', data);
                    }
                });
                return false;
            });
        };

        return {
            displayFileName: displayFileName,
            mapCompanyName: mapCompanyName,
        };

    })();
    
    processInvoice.displayFileName();
    processInvoice.mapCompanyName();
});
