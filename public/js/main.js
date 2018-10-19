$('.custom-file-input').on('change',function(){
    //get the file name
    var fileName = $(this).val();
    var parts = fileName.split("\\");
    fileName = parts[parts.length-1];
    //replace the "Choose a file" label
    $(this).next('.custom-file-label').html(fileName);
})
